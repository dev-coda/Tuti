<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderAsync;
use App\Models\Order;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use App\Services\Shipping\CoordinadoraOrderProcessingService;
use App\Services\Shipping\FvDynamicsService;
use App\Support\QueueConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;

/**
 * Manually transmit specific orders, routing each one through the same system
 * the queued job would have used (FV + Coordinadora guide for express orders,
 * legacy Tronex presales XML for everything else).
 *
 * Exists because an order whose ProcessOrderAsync job never ran has no other
 * operator-facing way to be transmitted.
 */
class TransmitOrders extends Command
{
    protected $signature = 'orders:transmit
                            {ids?* : Order IDs to transmit}
                            {--stuck : Select every order still awaiting transmission instead of passing IDs}
                            {--limit=25 : Cap on how many orders --stuck selects}
                            {--dry-run : Report what would happen, including preflight checks, without transmitting}
                            {--queue : Re-dispatch through ProcessOrderAsync instead of transmitting inline}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Transmit orders to FV/Coordinadora or Tronex, or diagnose why they are stuck';

    public function handle(): int
    {
        $orders = $this->resolveOrders();

        if ($orders->isEmpty()) {
            $this->info('No matching orders found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Status', 'Method', 'Provider', 'Route', 'FV', 'Guide', 'Attempts', 'Created'],
            $orders->map(fn (Order $o) => [
                $o->id,
                $o->status_id . ' (' . Order::getStatusSlug($o->status_id) . ')',
                $o->delivery_method ?? '-',
                $o->shipping_provider ?? '-',
                $o->usesFvFulfillment() ? 'FV + Coordinadora' : 'Tronex XML',
                $o->fv_number ?: '-',
                $o->coordinadora_guide_number ?: '-',
                $o->processing_attempts ?? 0,
                optional($o->created_at)->format('Y-m-d H:i'),
            ])->all()
        );

        foreach ($orders as $order) {
            $this->preflight($order);
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN: nothing was transmitted.');

            return self::SUCCESS;
        }

        $mode = $this->option('queue') ? 're-queue' : 'transmit inline (real API calls)';

        if (! $this->option('force') && ! $this->confirm("Proceed to {$mode} {$orders->count()} order(s)?", false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $this->newLine();
            $this->line("<comment>Order #{$order->id}</comment>");

            try {
                $this->option('queue')
                    ? $this->redispatch($order)
                    : $this->transmit($order);

                $succeeded++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ {$e->getMessage()}");

                Log::error('orders:transmit failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Succeeded: {$succeeded}" . ($failed ? ", Failed: {$failed}" : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Order>
     */
    private function resolveOrders()
    {
        $ids = array_filter((array) $this->argument('ids'));

        if (! $ids && ! $this->option('stuck')) {
            $this->error('Pass at least one order ID, or use --stuck.');

            return collect();
        }

        $query = Order::with(['user', 'zone', 'products.product']);

        if ($ids) {
            $found = $query->whereIn('id', $ids)->orderBy('id')->get();

            foreach (array_diff($ids, $found->pluck('id')->map('strval')->all()) as $missing) {
                $this->warn("Order #{$missing} not found.");
            }

            return $found;
        }

        return $query
            ->whereIn('status_id', [
                Order::STATUS_PENDING,
                Order::STATUS_ERROR,
                Order::STATUS_ERROR_WEBSERVICE,
            ])
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();
    }

    /**
     * Surface the prerequisites that make transmission fail, so a dry run is
     * useful on its own.
     */
    private function preflight(Order $order): void
    {
        $this->newLine();
        $this->line("<comment>Preflight #{$order->id}</comment>");

        if ($order->status_id === Order::STATUS_PROCESSED) {
            $this->warn('  ! Already processed; transmitting again may duplicate the order.');
        }

        if ($order->status_id === Order::STATUS_DRAFT) {
            $this->warn('  ! Draft order (client not yet in Dynamics); resolve the client first.');
        }

        if (! $order->zone) {
            $this->error('  ✗ No zone; transmission will fail.');

            return;
        }

        if (! $order->usesFvFulfillment()) {
            $this->line("  · Route: Tronex presales XML (delivery_method={$order->delivery_method}, provider=" . ($order->shipping_provider ?? 'null') . ')');

            return;
        }

        $this->line('  · Route: FV + Coordinadora guide');

        $custId = trim((string) ($order->user?->account_num ?: $order->user?->document));
        $custId === ''
            ? $this->error('  ✗ Customer has no account_num/document; FV will be rejected.')
            : $this->line("  · Customer account: {$custId}");

        $zoneNumber = trim((string) ($order->zone_snapshot['zone'] ?? $order->zone->zone ?? ''));
        $warehouse = $zoneNumber !== '' ? ZoneWarehouse::getBodegaForZone($zoneNumber) : null;
        $warehouse = $warehouse ?: trim((string) config('services.fv.default_warehouse'));

        $warehouse === ''
            ? $this->error("  ✗ No warehouse for zone '{$zoneNumber}' and FV_DEFAULT_WAREHOUSE is unset.")
            : $this->line("  · Warehouse: {$warehouse} (zone {$zoneNumber})");

        try {
            $this->line('  · FV endpoint: ' . app(FvDynamicsService::class)->resolveEndpoint());
        } catch (\Throwable $e) {
            $this->error('  ✗ FV endpoint: ' . $e->getMessage());
        }

        foreach (['origin_address' => 'COORDINADORA_ORIGIN_ADDRESS', 'origin_phone' => 'COORDINADORA_ORIGIN_PHONE', 'usuario' => 'COORDINADORA_USUARIO'] as $key => $env) {
            if (config('services.coordinadora.create_guides') && trim((string) config("services.coordinadora.{$key}")) === '') {
                $this->warn("  ! {$env} is empty; the Coordinadora guide call may be rejected.");
            }
        }

        if (! $order->zone->coordinadoraDaneCode()) {
            $this->error('  ✗ No destination DANE code resolvable for the zone.');
        }

        // Building the envelope exercises pricing, lines and warehouse lookup
        // without calling Dynamics.
        try {
            $build = new ReflectionMethod(FvDynamicsService::class, 'buildRequestXml');
            $build->setAccessible(true);
            $xml = $build->invoke(app(FvDynamicsService::class), $order);
            $this->line('  · FV payload builds OK (' . strlen($xml) . ' bytes)');
        } catch (\Throwable $e) {
            $this->error('  ✗ FV payload build fails: ' . $e->getMessage());
        }
    }

    private function transmit(Order $order): void
    {
        if ($order->usesFvFulfillment()) {
            app(CoordinadoraOrderProcessingService::class)->process($order);
            $order->refresh();

            $this->info("  ✓ FV: {$order->fv_number}, Guía: " . ($order->coordinadora_guide_number ?: '-'));

            return;
        }

        OrderRepository::presalesOrder($order);
        $order->refresh();

        $this->info('  ✓ Transmitted via Tronex XML; status now ' . $order->status_id);
    }

    private function redispatch(Order $order): void
    {
        $connection = QueueConnection::forBackgroundWork();

        // A job still inside its backoff window holds the unique lock and would
        // otherwise make this dispatch a silent no-op.
        Cache::forget('laravel_unique_job:' . ProcessOrderAsync::class . 'order-' . $order->id);

        $order->markAsManuallyRetried();

        ProcessOrderAsync::dispatch($order)->onConnection($connection);

        $this->info("  ✓ Queued on connection \"{$connection}\"; a worker must be consuming it.");
    }
}
