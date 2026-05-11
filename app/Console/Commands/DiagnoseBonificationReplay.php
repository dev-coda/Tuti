<?php

namespace App\Console\Commands;

use App\Models\Bonification;
use App\Models\Order;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Services\BonificationCheckoutService;
use Illuminate\Console\Command;

/**
 * Read-only replay of the bonification planner used in CartController::process.
 *
 * Reproduces the exact sequence of decisions the live checkout would make for a
 * given cart spec without writing to the DB or calling SOAP. Useful to debug
 * "bonification did not trigger" reports without re-submitting the order.
 *
 * Two input modes:
 *   --order=<id>                 use the order_products of an existing order
 *   --cart='<json>' --user=<id> --zone=<id>
 *                                replay an arbitrary cart spec
 *
 * Cart JSON format (array of rows):
 *   [{"product_id": 10, "variation_id": 22, "quantity": 12}, ...]
 */
class DiagnoseBonificationReplay extends Command
{
    protected $signature = 'diagnose:bonification-replay
        {--order= : Order id to replay (uses its order_products as the cart)}
        {--cart= : JSON array of cart rows when not replaying an order}
        {--user= : User id (required when --cart is used)}
        {--zone= : Zone id (required when --cart is used; defaults to the order zone when --order is used)}';

    protected $description = 'Replay the bonification planner against an order or cart spec without side effects';

    public function handle(): int
    {
        $orderId = $this->option('order');
        $cartJson = $this->option('cart');

        if (! $orderId && ! $cartJson) {
            $this->error('Provide either --order=<id> or --cart=<json>.');

            return 1;
        }

        if ($orderId) {
            return $this->replayFromOrder((int) $orderId);
        }

        $userId = (int) $this->option('user');
        $zoneId = (int) $this->option('zone');
        if (! $userId || ! $zoneId) {
            $this->error('--user=<id> and --zone=<id> are required with --cart=<json>.');

            return 1;
        }

        $cart = json_decode($cartJson, true);
        if (! is_array($cart)) {
            $this->error('Could not parse --cart JSON.');

            return 1;
        }

        return $this->replayFromCartSpec($cart, $userId, $zoneId, null);
    }

    private function replayFromOrder(int $orderId): int
    {
        $order = Order::with(['products', 'user', 'zone'])->find($orderId);
        if (! $order) {
            $this->error("Order #{$orderId} not found.");

            return 1;
        }

        $cart = $order->products->map(fn ($op) => [
            'product_id' => (int) $op->product_id,
            'variation_id' => $op->variation_item_id ? (int) $op->variation_item_id : null,
            'quantity' => (int) $op->quantity,
        ])->all();

        $this->info("Replaying planner using order #{$orderId} as cart source.");
        $this->newLine();

        return $this->replayFromCartSpec(
            $cart,
            (int) $order->user_id,
            (int) $order->zone_id,
            $orderId
        );
    }

    /**
     * @param array<int, array{product_id:int, variation_id:?int, quantity:int}> $cart
     */
    private function replayFromCartSpec(array $cart, int $userId, int $zoneId, ?int $existingOrderId): int
    {
        $user = User::find($userId);
        $zone = Zone::find($zoneId);
        if (! $user || ! $zone) {
            $this->error('User or Zone not found.');

            return 1;
        }

        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $isInventoryEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        $globalMin = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);
        $bodega = $isInventoryEnabled ? ZoneWarehouse::getBodegaForZone($zone->zone ?? $user->zone) : null;

        $this->section('Context');
        $this->keyValue([
            'Inventory enabled' => $isInventoryEnabled ? 'yes' : 'no',
            'Global minimum' => (string) $globalMin,
            'User' => "#{$user->id} ({$user->name})",
            'Zone' => "#{$zone->id} (zone={$zone->zone}, code={$zone->code})",
            'Bodega' => $bodega ?? '(none mapped)',
        ]);

        $this->section('Cart input');
        if (empty($cart)) {
            $this->warn('Cart is empty.');

            return 0;
        }
        foreach ($cart as $k => $row) {
            $p = Product::find($row['product_id'] ?? 0);
            $name = $p?->name ?? '(missing)';
            $vid = $row['variation_id'] ?? null;
            $this->line(sprintf(
                '  [%s] product_id=%s "%s" variation_id=%s quantity=%s',
                $k,
                $row['product_id'] ?? 'null',
                $name,
                $vid === null ? 'null' : $vid,
                $row['quantity'] ?? 'null'
            ));
        }

        $productQuantities = $this->printAggregation($cart);
        $lastCartKeyByProductId = BonificationCheckoutService::lastCartKeyByProductId($cart);
        $this->section('Last cart key per product (only this row triggers the planner)');
        foreach ($lastCartKeyByProductId as $pid => $key) {
            $this->line("  product {$pid} → cart key {$key}");
        }

        $plannedBonifications = [];
        $skipped = [];

        $this->section('Planner walk');
        foreach ($cart as $key => $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $p = Product::with(['brand.vendor', 'bonifications', 'tax', 'items', 'categories'])->find($pid);
            if (! $p) {
                $this->warn("  [cart {$key}] product #{$pid} NOT FOUND — would block order processing in checkout.");
                $skipped[] = ['kind' => 'product_missing', 'cart_key' => $key, 'product_id' => $pid];

                continue;
            }

            $isLastForProduct = ($lastCartKeyByProductId[$pid] ?? $key) === $key;
            $this->line(sprintf(
                "  Cart row [%s] product=%s \"%s\" variation=%s qty=%s %s",
                $key,
                $pid,
                $p->name,
                $row['variation_id'] ?? 'null',
                $row['quantity'] ?? 'null',
                $isLastForProduct ? '[LAST-KEY for product]' : '[skipped: not last-key]'
            ));

            if (! $isLastForProduct) {
                continue;
            }

            $bonifications = $p->bonifications;
            if ($bonifications->isEmpty()) {
                $this->line('     • No bonifications attached to this product. Decision: not_qualified_no_bonifications.');

                continue;
            }

            $aggregated = (int) ($productQuantities[$pid] ?? (((int) ($row['quantity'] ?? 0)) * ((int) ($p->package_quantity ?? 1))));

            foreach ($bonifications as $bonification) {
                $verdict = $this->evaluateBonification(
                    $bonification,
                    $aggregated,
                    $bodega,
                    $isInventoryEnabled,
                    $existingOrderId
                );
                $verdict['cart_key'] = $key;
                $verdict['trigger_product_id'] = $pid;
                if ($verdict['decision'] === 'planned') {
                    $plannedBonifications[] = $verdict;
                } else {
                    $skipped[] = $verdict;
                }
            }
        }

        $stockPlan = $this->printStockPlan($plannedBonifications, $bodega, $isInventoryEnabled);
        $expectedRows = $this->printExpectedRows($plannedBonifications, $skipped);

        if ($existingOrderId) {
            $this->printDiffAgainstOrder($existingOrderId, $expectedRows);
        }

        return 0;
    }

    /**
     * @return array<int,int> productId => aggregated individual unit total
     */
    private function printAggregation(array $cart): array
    {
        $productQuantities = [];
        $this->section('Aggregation (productQuantities = qty * parent package_quantity)');
        foreach ($cart as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $p = Product::find($pid);
            if (! $p) {
                $this->warn("  product #{$pid} missing → silently dropped from aggregate (would happen in checkout).");

                continue;
            }
            $pkg = (int) ($p->package_quantity ?? 1);
            $qty = (int) ($row['quantity'] ?? 0);
            $productQuantities[$pid] = ($productQuantities[$pid] ?? 0) + ($qty * $pkg);
        }
        foreach ($productQuantities as $pid => $total) {
            $p = Product::find($pid);
            $this->line(sprintf(
                '  product %s "%s" → aggregated=%d (package_quantity=%d)',
                $pid,
                $p?->name ?? '?',
                $total,
                (int) ($p?->package_quantity ?? 1)
            ));
        }

        return $productQuantities;
    }

    /**
     * @return array{decision:string, bonification:Bonification, gift_product:?Product, variation_item_id:?int, quantity:int, reason:?string}
     */
    private function evaluateBonification(
        Bonification $bonification,
        int $aggregated,
        ?string $bodega,
        bool $isInventoryEnabled,
        ?int $existingOrderId
    ): array {
        $buy = (int) $bonification->buy;
        $get = (int) $bonification->get;
        $max = (int) $bonification->max;
        $rawQty = $buy > 0 ? (int) floor($aggregated / $buy * $get) : 0;

        $this->line(sprintf(
            '     • Bonification #%d "%s" buy=%d get=%d max=%d  aggregated=%d → raw=%d',
            $bonification->id,
            $bonification->name,
            $buy,
            $get,
            $max,
            $aggregated,
            $rawQty
        ));

        if ($rawQty <= 0) {
            $this->line('       decision: not_qualified (aggregated < buy or get=0)');

            return [
                'decision' => 'not_qualified',
                'bonification' => $bonification,
                'gift_product' => null,
                'variation_item_id' => null,
                'quantity' => 0,
                'reason' => 'aggregated_below_buy',
            ];
        }

        $effective = $rawQty;
        if ($rawQty > $max) {
            $effective = $max;
            $this->line("       clamp: raw {$rawQty} → max {$max}");
        }
        if ($effective <= 0) {
            $this->line('       decision: max_zero_skip (max <= 0; current code would still create row with quantity=0 — bug)');

            return [
                'decision' => 'max_zero_skip',
                'bonification' => $bonification,
                'gift_product' => null,
                'variation_item_id' => null,
                'quantity' => 0,
                'reason' => 'max_clamped_to_zero',
            ];
        }

        $gift = Product::with(['items', 'categories'])->find($bonification->product_id);
        if (! $gift) {
            $this->line("       decision: gift_missing (Product #{$bonification->product_id} not found)");

            return [
                'decision' => 'gift_missing',
                'bonification' => $bonification,
                'gift_product' => null,
                'variation_item_id' => null,
                'quantity' => $effective,
                'reason' => 'gift_product_not_found',
            ];
        }

        $variationItemId = BonificationCheckoutService::resolveGiftVariationItemId(
            $gift,
            $existingOrderId ?? 0,
            $isInventoryEnabled ? $bodega : null,
            $effective
        );

        $this->line(sprintf(
            '       gift: #%d "%s" parent_sku=%s items=%d',
            $gift->id,
            $gift->name,
            $this->fmtSku($gift->sku),
            $gift->items->count()
        ));
        if ($gift->items->count() > 0) {
            foreach ($gift->items as $item) {
                $available = $bodega
                    ? (int) $gift->getInventoryForBodega($bodega, (int) $item->id)
                    : -1;
                $this->line(sprintf(
                    '         · item #%d "%s" pivot_sku=%s enabled=%s available@bodega=%s',
                    $item->id,
                    $item->name,
                    $this->fmtSku($item->pivot->sku ?? ''),
                    ((bool) ($item->pivot->enabled ?? false)) ? 'yes' : 'no',
                    $available < 0 ? 'n/a' : (string) $available
                ));
            }
        }
        if ($bodega && $isInventoryEnabled && $gift->isInventoryManaged()) {
            $parentAvail = (int) $gift->getInventoryForBodega($bodega, null);
            $floor = BonificationCheckoutService::effectiveInventoryFloor($gift);
            $this->line(sprintf(
                '         parent_available@bodega=%d, floor=%d, requested=%d',
                $parentAvail,
                $floor,
                $effective
            ));
        }
        $this->line(sprintf(
            '       resolveGiftVariationItemId → %s',
            $variationItemId === null ? 'null (use parent SKU)' : "variation_item_id={$variationItemId}"
        ));
        $this->line(sprintf(
            '       decision: planned (gift_product_id=%d, variation_item_id=%s, quantity=%d)',
            $gift->id,
            $variationItemId ?? 'null',
            $effective
        ));

        return [
            'decision' => 'planned',
            'bonification' => $bonification,
            'gift_product' => $gift,
            'variation_item_id' => $variationItemId,
            'quantity' => $effective,
            'reason' => null,
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $planned
     * @return array<string, array<string,mixed>>
     */
    private function printStockPlan(array $planned, ?string $bodega, bool $isInventoryEnabled): array
    {
        $this->section('Stock plan (gift_product:variation → requested_total, available)');
        $plan = [];
        foreach ($planned as $p) {
            $gift = $p['gift_product'];
            $key = $gift->id.':'.($p['variation_item_id'] ?? 'base');
            if (! isset($plan[$key])) {
                $available = null;
                if ($isInventoryEnabled && $bodega && $gift->isInventoryManaged()) {
                    $available = (int) $gift->getInventoryForBodega($bodega, $p['variation_item_id']);
                }
                $plan[$key] = [
                    'gift_id' => $gift->id,
                    'gift_name' => $gift->name,
                    'variation_item_id' => $p['variation_item_id'],
                    'requested_total' => 0,
                    'available' => $available,
                    'floor' => BonificationCheckoutService::effectiveInventoryFloor($gift),
                ];
            }
            $plan[$key]['requested_total'] += (int) $p['quantity'];
        }
        if (empty($plan)) {
            $this->line('  (no planned gifts)');

            return $plan;
        }
        foreach ($plan as $key => $entry) {
            $availableStr = $entry['available'] === null ? 'n/a' : (string) $entry['available'];
            $fits = $entry['available'] === null
                ? 'n/a (inventory not managed)'
                : ((($entry['available'] - $entry['floor']) >= $entry['requested_total']) ? 'yes' : 'NO — would roll back order');
            $this->line(sprintf(
                '  %s "%s" → requested_total=%d, available=%s, floor=%d, fits=%s',
                $key,
                $entry['gift_name'],
                $entry['requested_total'],
                $availableStr,
                $entry['floor'],
                $fits
            ));
        }

        return $plan;
    }

    /**
     * @param array<int, array<string,mixed>> $planned
     * @param array<int, array<string,mixed>> $skipped
     * @return array<int, array{product_id:int, variation_item_id:?int, quantity:int, bonification_id:int}>
     */
    private function printExpectedRows(array $planned, array $skipped): array
    {
        $this->section('Expected order_product_bonifications (what the planner WOULD insert)');
        if (empty($planned)) {
            $this->line('  (none)');
        }
        $expected = [];
        foreach ($planned as $p) {
            $row = [
                'product_id' => (int) $p['gift_product']->id,
                'variation_item_id' => $p['variation_item_id'] === null ? null : (int) $p['variation_item_id'],
                'quantity' => (int) $p['quantity'],
                'bonification_id' => (int) $p['bonification']->id,
            ];
            $expected[] = $row;
            $this->line(sprintf(
                '  + product_id=%d variation_item_id=%s quantity=%d bonification_id=%d',
                $row['product_id'],
                $row['variation_item_id'] ?? 'null',
                $row['quantity'],
                $row['bonification_id']
            ));
        }

        if (! empty($skipped)) {
            $this->section('Silently or visibly skipped bonifications');
            foreach ($skipped as $s) {
                $bid = isset($s['bonification']) ? "#{$s['bonification']->id} \"{$s['bonification']->name}\"" : '(no id)';
                $this->warn(sprintf(
                    '  ✘ %s — decision=%s reason=%s',
                    $bid,
                    $s['decision'] ?? '?',
                    $s['reason'] ?? '?'
                ));
            }
        }

        return $expected;
    }

    /**
     * @param array<int, array{product_id:int, variation_item_id:?int, quantity:int, bonification_id:int}> $expected
     */
    private function printDiffAgainstOrder(int $orderId, array $expected): void
    {
        $this->section("Diff vs existing order_product_bonifications on order #{$orderId}");
        $actual = OrderProductBonification::query()->where('order_id', $orderId)->get()
            ->map(fn ($r) => [
                'product_id' => (int) $r->product_id,
                'variation_item_id' => $r->variation_item_id === null ? null : (int) $r->variation_item_id,
                'quantity' => (int) $r->quantity,
                'bonification_id' => (int) $r->bonification_id,
            ])->all();

        $hash = fn ($r) => $r['bonification_id'].'|'.$r['product_id'].'|'.($r['variation_item_id'] ?? 'null').'|'.$r['quantity'];

        $expIdx = collect($expected)->keyBy($hash);
        $actIdx = collect($actual)->keyBy($hash);

        foreach ($expIdx as $h => $row) {
            if (isset($actIdx[$h])) {
                $this->info("  ✔ match: ".$this->describeRow($row));
            } else {
                $this->warn("  ✘ missing in DB (planner expects it): ".$this->describeRow($row));
            }
        }
        foreach ($actIdx as $h => $row) {
            if (! isset($expIdx[$h])) {
                $this->warn("  ⚠ extra in DB (planner would NOT produce it now): ".$this->describeRow($row));
            }
        }
        if ($expIdx->isEmpty() && $actIdx->isEmpty()) {
            $this->line('  No bonification rows on either side. Nothing to diff.');
        }
    }

    /** @param array<string,mixed> $row */
    private function describeRow(array $row): string
    {
        return sprintf(
            'product_id=%d variation_item_id=%s quantity=%d bonification_id=%d',
            $row['product_id'],
            $row['variation_item_id'] ?? 'null',
            $row['quantity'],
            $row['bonification_id']
        );
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info("=== {$title} ===");
    }

    /** @param array<string,string> $rows */
    private function keyValue(array $rows): void
    {
        foreach ($rows as $k => $v) {
            $this->line(sprintf('  %-22s %s', $k.':', $v));
        }
    }

    private function fmtSku(?string $sku): string
    {
        $sku = (string) $sku;
        return $sku === '' ? '(empty)' : '"'.$sku.'"';
    }
}
