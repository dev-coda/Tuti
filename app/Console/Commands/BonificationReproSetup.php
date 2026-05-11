<?php

namespace App\Console\Commands;

use App\Services\BonificationReproService;
use Illuminate\Console\Command;

/**
 * Seeds DB state for one or all reproduction scenarios so the matching
 * `bonification:repro:run` invocation can submit a real cart against it.
 */
class BonificationReproSetup extends Command
{
    protected $signature = 'bonification:repro:setup
        {--scenario= : Scenario key (e.g. s06_gift_var_parent_sku_variation_inv)}
        {--all : Seed every scenario}
        {--list : Just list available scenarios}
        {--teardown : Remove all repro records before seeding}';

    protected $description = 'Seed the DB with one or all bonification reproduction scenarios';

    public function handle(BonificationReproService $service): int
    {
        if ($this->option('list')) {
            return $this->printList($service);
        }

        if ($this->option('teardown')) {
            $this->info('Tearing down previous repro data...');
            $service->teardown();
            $this->info('Done.');
        }

        $keys = $this->resolveKeys($service);
        if (empty($keys)) {
            $this->error('Provide --scenario=<key>, --all or --list.');

            return 1;
        }

        foreach ($keys as $key) {
            $this->line('');
            $this->info("→ Setting up scenario: {$key}");
            try {
                $result = $service->setup($key);
                $this->describeSetup($result);
            } catch (\Throwable $e) {
                $this->error("  ✗ Setup failed: {$e->getMessage()}");

                return 1;
            }
        }

        $this->line('');
        $this->info('Setup complete. Next: php artisan bonification:repro:run --scenario=<key>');

        return 0;
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

    private function printList(BonificationReproService $service): int
    {
        $rows = [];
        foreach ($service->scenarios() as $s) {
            $rows[] = [$s['key'], $s['title'], $s['expect']];
        }
        $this->table(['Key', 'Title', 'Expected outcome'], $rows);

        return 0;
    }

    private function describeSetup(array $result): void
    {
        $this->line("  User: #{$result['user']->id}  Zone: #{$result['zone']->id} ({$result['zone']->zone})");
        $this->line('  Cart:');
        foreach ($result['cart'] as $row) {
            $this->line(sprintf(
                '    product_id=%s variation_id=%s quantity=%s',
                $row['product_id'],
                $row['variation_id'] ?? 'null',
                $row['quantity']
            ));
        }
    }
}
