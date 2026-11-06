<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SuggestedOrderService
{
    /**
     * The suggestion always starts from the client's own past orders.
     * The gap up to (average order × overorder) is filled by heuristic weight:
     * more of what they already buy, products bought with those, then bestsellers.
     */
    public const SETTING_KEYS = [
        'enabled' => 'suggested_order_enabled',
        'weight_history' => 'suggested_order_weight_history',
        'weight_together' => 'suggested_order_weight_together',
        'weight_bestseller' => 'suggested_order_weight_bestseller',
        'overorder_percent' => 'suggested_order_overorder_percent',
        'history_limit' => 'suggested_order_history_limit',
        'max_lines' => 'suggested_order_max_lines',
    ];

    private const EXCLUDED_STATUSES = [
        Order::STATUS_CANCELLED,
        Order::STATUS_DRAFT,
    ];

    /**
     * @return array{
     *     enabled: bool,
     *     weight_history: int,
     *     weight_together: int,
     *     weight_bestseller: int,
     *     overorder_percent: float,
     *     history_limit: int,
     *     max_lines: int
     * }
     */
    public function config(): array
    {
        $history = $this->intSetting(self::SETTING_KEYS['weight_history'], 60, 0, 100);
        $together = $this->intSetting(self::SETTING_KEYS['weight_together'], 25, 0, 100);
        $bestseller = $this->intSetting(self::SETTING_KEYS['weight_bestseller'], 15, 0, 100);

        if ($history + $together + $bestseller === 0) {
            $history = 60;
            $together = 25;
            $bestseller = 15;
        }

        return [
            'enabled' => $this->boolSetting(self::SETTING_KEYS['enabled'], true),
            'weight_history' => $history,
            'weight_together' => $together,
            'weight_bestseller' => $bestseller,
            'overorder_percent' => $this->floatSetting(self::SETTING_KEYS['overorder_percent'], 15, 0, 200),
            'history_limit' => $this->intSetting(self::SETTING_KEYS['history_limit'], 12, 1, 50),
            'max_lines' => $this->intSetting(self::SETTING_KEYS['max_lines'], 20, 1, 40),
        ];
    }

    public static function forgetConfigCache(): void
    {
        foreach (self::SETTING_KEYS as $key) {
            Cache::forget('setting_'.$key);
        }
    }

    /**
     * @return array{
     *     lines: list<array{product_id: int, quantity: int, variation_id: int|null}>,
     *     meta: array<string, mixed>
     * }
     */
    public function build(User $client): array
    {
        $config = $this->config();
        $history = $this->clientHistory($client, $config['history_limit']);
        $orderCount = $history['order_count'];
        $average = $history['average'];
        $target = $orderCount > 0
            ? round($average * (1 + ($config['overorder_percent'] / 100)), 2)
            : 0.0;

        $historyStats = $history['products'];
        $stapleIds = array_keys($historyStats);

        $together = $stapleIds === [] ? [] : $this->togetherCounts($stapleIds);
        $bestsellers = $this->bestsellerQuantities();

        $candidateIds = array_values(array_unique(array_merge(
            $stapleIds,
            array_keys($together),
            array_keys($bestsellers)
        )));

        $catalog = $candidateIds === []
            ? collect()
            : Product::query()
                ->with(['tax', 'brand.vendor', 'bonifications', 'items'])
                ->whereIn('id', $candidateIds)
                ->get()
                ->keyBy('id');

        $maxTogether = max(1, (int) (collect($together)->max() ?? 0));
        $maxSold = max(1, (int) (collect($bestsellers)->max() ?? 0));

        $scored = [];
        foreach ($candidateIds as $productId) {
            $product = $catalog->get($productId);
            if (! $product instanceof Product) {
                continue;
            }

            $appearances = (int) ($historyStats[$productId]['appearances'] ?? 0);
            $histNorm = $orderCount > 0 ? $appearances / $orderCount : 0.0;
            $togNorm = ((int) ($together[$productId] ?? 0)) / $maxTogether;
            $bestNorm = ((int) ($bestsellers[$productId] ?? 0)) / $maxSold;
            $score = ($config['weight_history'] * $histNorm)
                + ($config['weight_together'] * $togNorm)
                + ($config['weight_bestseller'] * $bestNorm);

            $scored[$productId] = [
                'product' => $product,
                'score' => $score,
                'hist_norm' => $histNorm,
                'typical_qty' => (int) ($historyStats[$productId]['typical_qty'] ?? 0),
                'variation_id' => $historyStats[$productId]['variation_id'] ?? null,
                'appearances' => $appearances,
            ];
        }

        $built = $orderCount === 0
            ? $this->starterBasket($scored, min(8, $config['max_lines']))
            : $this->fill($scored, $config, $orderCount, $target);

        return [
            'lines' => $built['lines'],
            'meta' => [
                'client_id' => $client->id,
                'client_name' => $client->business_name ?: $client->name,
                'order_count' => $orderCount,
                'average_order' => $average,
                'overorder_percent' => $config['overorder_percent'],
                'target' => $target,
                'estimated_total' => $built['estimated'],
                'weights' => [
                    'history' => $config['weight_history'],
                    'together' => $config['weight_together'],
                    'bestseller' => $config['weight_bestseller'],
                ],
            ],
        ];
    }

    /**
     * @return array{order_count: int, average: float, products: array<int, array{appearances: int, typical_qty: int, variation_id: int|null}>}
     */
    private function clientHistory(User $client, int $limit): array
    {
        $orders = Order::query()
            ->where('user_id', $client->id)
            ->where('total', '>', 0)
            ->whereNotIn('status_id', self::EXCLUDED_STATUSES)
            ->with(['products' => function ($query) {
                $query->where('is_bonification', false)->where('quantity', '>', 0);
            }])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $stats = [];
        foreach ($orders as $order) {
            $seenInOrder = [];
            foreach ($order->products as $line) {
                $productId = (int) $line->product_id;
                if (! isset($stats[$productId])) {
                    $stats[$productId] = [
                        'quantities' => [],
                        'variations' => [],
                    ];
                }
                $stats[$productId]['quantities'][] = (int) $line->quantity;
                $variationKey = $line->variation_item_id === null ? '' : (string) $line->variation_item_id;
                $stats[$productId]['variations'][$variationKey] = ($stats[$productId]['variations'][$variationKey] ?? 0) + 1;
                $seenInOrder[$productId] = true;
            }
            foreach (array_keys($seenInOrder) as $productId) {
                $stats[$productId]['appearances'] = ($stats[$productId]['appearances'] ?? 0) + 1;
            }
        }

        $products = [];
        foreach ($stats as $productId => $stat) {
            arsort($stat['variations']);
            $topVariation = array_key_first($stat['variations']);
            $products[(int) $productId] = [
                'appearances' => (int) ($stat['appearances'] ?? 0),
                'typical_qty' => $this->medianQuantity($stat['quantities']),
                'variation_id' => $topVariation === null || $topVariation === '' ? null : (int) $topVariation,
            ];
        }

        $count = $orders->count();
        $average = $count > 0 ? round((float) $orders->avg('total'), 2) : 0.0;

        return [
            'order_count' => $count,
            'average' => $average,
            'products' => $products,
        ];
    }

    /**
     * How often each product shows up in the same orders as the client's staples,
     * plus the curated "comprados juntos" combinations.
     *
     * @param  list<int>  $stapleIds
     * @return array<int, int>
     */
    private function togetherCounts(array $stapleIds): array
    {
        $counts = [];

        $orderIds = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->whereIn('order_products.product_id', $stapleIds)
            ->where('orders.total', '>', 0)
            ->whereNotIn('orders.status_id', self::EXCLUDED_STATUSES)
            ->where('order_products.is_bonification', false)
            ->orderByDesc('orders.id')
            ->limit(3000)
            ->pluck('orders.id')
            ->unique()
            ->values();

        if ($orderIds->isNotEmpty()) {
            $rows = DB::table('order_products')
                ->whereIn('order_id', $orderIds)
                ->where('is_bonification', false)
                ->where('quantity', '>', 0)
                ->select('product_id', DB::raw('COUNT(DISTINCT order_id) as orders_together'))
                ->groupBy('product_id')
                ->get();

            foreach ($rows as $row) {
                $counts[(int) $row->product_id] = (int) $row->orders_together;
            }
        }

        $combinations = DB::table('product_combination')
            ->whereIn('parent_id', $stapleIds)
            ->get(['parent_id', 'product_id']);

        foreach ($combinations as $combination) {
            $productId = (int) $combination->product_id;
            $counts[$productId] = ($counts[$productId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function bestsellerQuantities(): array
    {
        $rows = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->where('orders.total', '>', 0)
            ->whereNotIn('orders.status_id', self::EXCLUDED_STATUSES)
            ->where('order_products.is_bonification', false)
            ->where('order_products.quantity', '>', 0)
            ->groupBy('order_products.product_id')
            ->orderByDesc(DB::raw('SUM(order_products.quantity)'))
            ->limit(40)
            ->select('order_products.product_id', DB::raw('SUM(order_products.quantity) as sold'))
            ->get();

        $sold = [];
        foreach ($rows as $row) {
            $sold[(int) $row->product_id] = (int) $row->sold;
        }

        return $sold;
    }

    /**
     * @param  array<int, array{product: Product, score: float, hist_norm: float, typical_qty: int, variation_id: int|null, appearances: int}>  $scored
     * @param  array{weight_history: int, weight_together: int, weight_bestseller: int, max_lines: int}  $config
     * @return array{lines: list<array{product_id: int, quantity: int, variation_id: int|null}>, estimated: float}
     */
    private function fill(array $scored, array $config, int $orderCount, float $target): array
    {
        $coreIds = $this->coreProductIds($scored, $orderCount, $config['max_lines']);
        $lines = [];
        $estimated = 0.0;

        foreach ($coreIds as $productId) {
            $entry = $scored[$productId];
            $qty = $this->snapQuantity($entry['product'], max(1, $entry['typical_qty']));
            $price = $this->unitPrice($entry['product'], $entry['variation_id']);
            if ($qty < 1 || $price <= 0) {
                continue;
            }

            $lines[$productId] = $this->makeLine($entry, $qty, $price, $config['weight_history'] * $entry['hist_norm']);
            $estimated += $price * $qty;
        }

        $extras = [];
        foreach ($scored as $productId => $entry) {
            if (isset($lines[$productId]) || $entry['score'] <= 0) {
                continue;
            }
            $extras[] = ['id' => (int) $productId, 'score' => $entry['score']];
        }
        usort($extras, function (array $a, array $b) {
            if ($a['score'] === $b['score']) {
                return $a['id'] <=> $b['id'];
            }

            return $b['score'] <=> $a['score'];
        });

        $extraIndex = 0;
        $safety = 0;
        while ($estimated + 0.01 < $target && $safety < 300) {
            $safety++;
            $before = $estimated;
            $bumpId = $this->bestBumpId($lines, true);
            $bumpScore = $bumpId !== null ? $lines[$bumpId]['bump_score'] : -1.0;
            $added = false;

            while ($extraIndex < count($extras) && count($lines) < $config['max_lines']) {
                $extraId = $extras[$extraIndex]['id'];
                if ($extras[$extraIndex]['score'] <= $bumpScore && $bumpId !== null) {
                    break;
                }
                $extraIndex++;
                if ($this->addLine($lines, $scored[$extraId], $estimated)) {
                    $added = true;
                    break;
                }
            }

            if ($added) {
                continue;
            }
            if ($bumpId !== null && $this->bumpLine($lines[$bumpId], $estimated)) {
                continue;
            }

            $fallbackId = $this->bestBumpId($lines, false);
            if ($fallbackId !== null && $this->bumpLine($lines[$fallbackId], $estimated)) {
                continue;
            }

            if ($estimated <= $before + 0.001) {
                break;
            }
        }

        $cartLines = [];
        foreach ($lines as $line) {
            $cartLines[] = [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'variation_id' => $line['variation_id'],
            ];
        }

        return [
            'lines' => $cartLines,
            'estimated' => round($estimated, 2),
        ];
    }

    /**
     * Clients with no past orders get a short list of the strongest remaining signal,
     * which with the default weights is the bestseller list.
     *
     * @param  array<int, array{product: Product, score: float, variation_id: int|null}>  $scored
     * @return array{lines: list<array{product_id: int, quantity: int, variation_id: int|null}>, estimated: float}
     */
    private function starterBasket(array $scored, int $limit): array
    {
        $ranked = [];
        foreach ($scored as $productId => $entry) {
            if ($entry['score'] <= 0) {
                continue;
            }
            $ranked[] = ['id' => (int) $productId, 'score' => $entry['score']];
        }
        usort($ranked, function (array $a, array $b) {
            if ($a['score'] === $b['score']) {
                return $a['id'] <=> $b['id'];
            }

            return $b['score'] <=> $a['score'];
        });

        $lines = [];
        $estimated = 0.0;
        foreach (array_slice($ranked, 0, $limit) as $row) {
            $this->addLine($lines, $scored[$row['id']], $estimated);
        }

        $cartLines = [];
        foreach ($lines as $line) {
            $cartLines[] = [
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'variation_id' => $line['variation_id'],
            ];
        }

        return [
            'lines' => $cartLines,
            'estimated' => round($estimated, 2),
        ];
    }

    /**
     * Products the client buys on at least half of the sampled orders.
     * With no history, there is no core basket.
     *
     * @param  array<int, array{appearances: int}>  $scored
     * @return list<int>
     */
    private function coreProductIds(array $scored, int $orderCount, int $maxLines): array
    {
        if ($orderCount === 0) {
            return [];
        }

        $minimum = max(1, (int) ceil($orderCount * 0.5));
        $core = [];
        foreach ($scored as $productId => $entry) {
            if ($entry['appearances'] >= $minimum) {
                $core[$productId] = $entry['appearances'];
            }
        }

        if ($core === []) {
            $topId = null;
            $topAppearances = 0;
            foreach ($scored as $productId => $entry) {
                if ($entry['appearances'] > $topAppearances) {
                    $topId = $productId;
                    $topAppearances = $entry['appearances'];
                }
            }
            if ($topId !== null) {
                $core[$topId] = $topAppearances;
            }
        }

        arsort($core);

        return array_slice(array_keys($core), 0, $maxLines);
    }

    /**
     * @param  array<int, array{bump_score: float, product: Product, quantity: int}>  $lines
     */
    private function bestBumpId(array $lines, bool $requireScore): ?int
    {
        $bestId = null;
        $bestScore = $requireScore ? 0.0 : -1.0;

        foreach ($lines as $productId => $line) {
            if ($requireScore && $line['bump_score'] <= 0) {
                continue;
            }
            if ($this->increasedQuantity($line['product'], $line['quantity']) === null) {
                continue;
            }
            if ($bestId === null || $line['bump_score'] > $bestScore) {
                $bestId = (int) $productId;
                $bestScore = $line['bump_score'];
            }
        }

        return $bestId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array{product: Product, score: float, variation_id: int|null}  $entry
     */
    private function addLine(array &$lines, array $entry, float &$estimated): bool
    {
        $product = $entry['product'];
        $productId = (int) $product->id;
        if (isset($lines[$productId])) {
            return false;
        }

        $qty = $this->snapQuantity($product, max(1, (int) ($product->quantity_min ?: 1)));
        $price = $this->unitPrice($product, $entry['variation_id']);
        if ($qty < 1 || $price <= 0) {
            return false;
        }

        $lines[$productId] = $this->makeLine($entry, $qty, $price, $entry['score']);
        $estimated += $price * $qty;

        return true;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function bumpLine(array &$line, float &$estimated): bool
    {
        $next = $this->increasedQuantity($line['product'], $line['quantity']);
        if ($next === null) {
            return false;
        }

        $added = $next - $line['quantity'];
        $line['quantity'] = $next;
        $estimated += $line['unit_price'] * $added;

        return $added > 0;
    }

    /**
     * @param  array{product: Product, variation_id: int|null}  $entry
     * @return array{product_id: int, variation_id: int|null, quantity: int, unit_price: float, bump_score: float, product: Product}
     */
    private function makeLine(array $entry, int $qty, float $price, float $bumpScore): array
    {
        return [
            'product_id' => (int) $entry['product']->id,
            'variation_id' => $entry['variation_id'],
            'quantity' => $qty,
            'unit_price' => $price,
            'bump_score' => $bumpScore,
            'product' => $entry['product'],
        ];
    }

    private function unitPrice(Product $product, ?int $variationId): float
    {
        if (! $product->active || ! $product->brand || ! $product->brand->vendor || ! $product->tax) {
            return 0.0;
        }

        $priced = $product->getFinalPriceForUser(true, 0, $variationId);

        return round((float) ($priced['price'] ?? 0), 2);
    }

    private function snapQuantity(Product $product, int $desired): int
    {
        $min = max(1, (int) ($product->quantity_min ?: 1));
        $step = max(1, (int) ($product->step ?: 1));
        $max = (int) ($product->quantity_max ?: 0);

        if ($desired < $min) {
            $desired = $min;
        }

        $stepsFromMin = (int) round(($desired - $min) / $step);
        $qty = $min + ($stepsFromMin * $step);
        if ($qty < $min) {
            $qty = $min;
        }

        if ($max > 0 && $qty > $max) {
            if ($max < $min) {
                return 0;
            }
            $stepsFromMin = intdiv($max - $min, $step);
            $qty = $min + ($stepsFromMin * $step);
        }

        return $qty;
    }

    private function increasedQuantity(Product $product, int $current): ?int
    {
        $step = max(1, (int) ($product->step ?: 1));
        $next = $this->snapQuantity($product, $current + $step);
        if ($next <= $current) {
            return null;
        }

        return $next;
    }

    /**
     * @param  list<int>  $quantities
     */
    private function medianQuantity(array $quantities): int
    {
        if ($quantities === []) {
            return 1;
        }

        sort($quantities);
        $count = count($quantities);
        $mid = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (int) $quantities[$mid];
        }

        return (int) round(($quantities[$mid - 1] + $quantities[$mid]) / 2);
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = Setting::getByKeyWithDefault($key, $default ? '1' : '0');

        return $value === '1' || $value === 1 || $value === true;
    }

    private function intSetting(string $key, int $default, int $min, int $max): int
    {
        $value = (int) Setting::getByKeyWithDefault($key, (string) $default);

        return max($min, min($max, $value));
    }

    private function floatSetting(string $key, float $default, float $min, float $max): float
    {
        $value = (float) Setting::getByKeyWithDefault($key, (string) $default);

        return max($min, min($max, $value));
    }
}
