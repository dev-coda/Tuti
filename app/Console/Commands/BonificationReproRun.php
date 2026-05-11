<?php

namespace App\Console\Commands;

use App\Http\Controllers\CartController;
use App\Models\Order;
use App\Models\OrderProductBonification;
use App\Repositories\OrderRepository;
use App\Services\BonificationReproService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Submits the cart of a previously-set-up reproduction scenario through the
 * real {@see CartController::processOrder()} entrypoint and prints what
 * happened: order id, bonification rows, would-be XML body. SOAP transmission
 * is disabled by default; opt in via --actually-transmit.
 */
class BonificationReproRun extends Command
{
    protected $signature = 'bonification:repro:run
        {--scenario= : Scenario key, or --all to run every scenario}
        {--all : Run every scenario sequentially}
        {--setup : Run setup for the scenario before running}
        {--teardown-before : Teardown all repro data before setup}
        {--actually-transmit : Actually call OrderRepository::presalesOrder (may hit SOAP)}';

    protected $description = 'Submit a real cart through CartController for one or all repro scenarios and print results';

    public function handle(BonificationReproService $service): int
    {
        if ($this->option('teardown-before')) {
            $service->teardown();
        }

        $keys = $this->resolveKeys($service);
        if (empty($keys)) {
            $this->error('Provide --scenario=<key> or --all.');

            return 1;
        }

        $failed = 0;
        foreach ($keys as $key) {
            $this->line('');
            $this->info(str_repeat('═', 100));
            $this->info("Scenario: {$key}");
            $this->info(str_repeat('═', 100));
            try {
                // Always re-seed so each scenario starts from a known clean state. The
                // --setup flag is implicit; keeping it for backward-compat in scripts.
                $result = $service->setup($key);
                $this->describeAndRun($service, $key, $result);
            } catch (\Throwable $e) {
                $this->error("✗ Scenario {$key} crashed: {$e->getMessage()}");
                $this->line($e->getTraceAsString());
                $failed++;
            }
        }
        $this->line('');
        if ($failed === 0) {
            $this->info("All {$this->scenariosCount($keys)} scenarios executed.");
        } else {
            $this->warn("{$failed} scenario(s) crashed; see above.");
        }

        return $failed === 0 ? 0 : 1;
    }

    /** @return array<int, string> */
    private function resolveKeys(BonificationReproService $service): array
    {
        if ($this->option('all')) {
            return array_column($service->scenarios(), 'key');
        }
        $key = $this->option('scenario');

        return $key ? [$key] : [];
    }

    /** @param array<string,mixed> $result */
    private function describeAndRun(BonificationReproService $service, string $key, array $result): void
    {
        $user = $result['user'];
        $zone = $result['zone'];
        $cart = $result['cart'];

        $this->line("User: #{$user->id}  Zone: #{$zone->id} ({$zone->zone})");
        $this->line('Cart:');
        foreach ($cart as $row) {
            $this->line(sprintf(
                '  product_id=%s variation_id=%s quantity=%s',
                $row['product_id'],
                $row['variation_id'] ?? 'null',
                $row['quantity']
            ));
        }

        $this->newLine();
        $this->info('-- Replay (read-only planner) --');
        $this->call('diagnose:bonification-replay', [
            '--cart' => json_encode($cart),
            '--user' => (string) $user->id,
            '--zone' => (string) $zone->id,
        ]);

        $this->newLine();
        $this->info('-- Live submission via CartController::processOrder() --');

        auth()->login($user);
        session()->forget('cart');
        session()->put('cart', $cart);

        $request = Request::create('/carrito', 'POST', [
            'zone_id' => $zone->id,
            'observations' => $result['observations'] ?? 'repro '.$key,
        ]);
        $request->setLaravelSession(app('session.store'));

        $orderCountBefore = Order::query()->count();
        $bonifCountBefore = OrderProductBonification::query()->count();

        try {
            /** @var \Illuminate\Http\RedirectResponse $response */
            $response = app(CartController::class)->processOrder($request);

            $error = session('error');
            $success = session('success');
            $cartUpdated = session('cart_updated');

            $orderCountAfter = Order::query()->count();
            $bonifCountAfter = OrderProductBonification::query()->count();

            $newOrder = Order::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->first();

            $this->line('HTTP-like response status: '.$response->getStatusCode());
            if ($error) {
                $this->warn("Flash error: {$error}");
            }
            if ($success) {
                $this->info("Flash success: {$success}");
            }
            if ($cartUpdated) {
                $this->line('Flash cart_updated: true');
            }
            $this->line('Orders before/after: '.$orderCountBefore.' → '.$orderCountAfter);
            $this->line('Bonification rows before/after: '.$bonifCountBefore.' → '.$bonifCountAfter);

            if ($newOrder && $orderCountAfter > $orderCountBefore) {
                $this->describeOrder($newOrder, $this->option('actually-transmit'));
            }
        } catch (\Throwable $e) {
            $this->error('processOrder threw: '.$e->getMessage());
            $this->line($e->getFile().':'.$e->getLine());
        } finally {
            auth()->logout();
            session()->forget('cart');
        }
    }

    private function describeOrder(Order $order, bool $actuallyTransmit): void
    {
        $order->load(['products', 'bonifications', 'zone', 'user']);
        $this->info("Created order #{$order->id} status_id={$order->status_id} total={$order->total}");
        $this->line('Products:');
        foreach ($order->products as $op) {
            $this->line(sprintf(
                '  - product_id=%d variation_item_id=%s qty=%d price=%s',
                $op->product_id,
                $op->variation_item_id ?? 'null',
                $op->quantity,
                $op->price
            ));
        }
        $this->line('Bonifications (order_product_bonifications):');
        if ($order->bonifications->isEmpty()) {
            $this->warn('  (none)');
        }
        foreach ($order->bonifications as $b) {
            $this->line(sprintf(
                '  - bonification_id=%d product_id=%d variation_item_id=%s qty=%d',
                $b->bonification_id,
                $b->product_id,
                $b->variation_item_id ?? 'null',
                $b->quantity
            ));
        }

        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, true);
        if ($xml) {
            $this->info('Would-be XML (built via buildOrderXmlForDiagnostic, no SOAP):');
            $this->line('  ─── start XML ───');
            foreach (preg_split('/\r?\n/', $xml) as $line) {
                $this->line('  '.$line);
            }
            $this->line('  ─── end XML ───');
        } else {
            $this->warn('XML build returned null (likely no zone on order).');
        }

        if ($actuallyTransmit) {
            $this->warn('--actually-transmit set: calling OrderRepository::presalesOrder() now (will attempt SOAP).');
            OrderRepository::presalesOrder($order->fresh(['products', 'bonifications', 'zone']));
        }
    }

    /** @param array<int, string> $keys */
    private function scenariosCount(array $keys): int
    {
        return count($keys);
    }
}
