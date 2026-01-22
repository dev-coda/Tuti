<?php

namespace App\Console\Commands;

use App\Exports\OrdersDailyAuditExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class GenerateDailyAuditReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:daily-audit {date? : The date to audit (Y-m-d format). Defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily audit report for orders with package quantities, bonifications, and suspicious pricing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? now()->subDay()->format('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2026-01-21)');
            return 1;
        }

        $this->info("Generating daily audit report for: {$date}");

        try {
            // Generate filename
            $filename = 'reports/orders_audit_' . str_replace('-', '', $date) . '_' . time() . '.xlsx';
            
            // Export to storage
            Excel::store(new OrdersDailyAuditExport($date), $filename, 'local');
            
            $fullPath = Storage::disk('local')->path($filename);
            
            $this->info('✓ Report generated successfully!');
            $this->line('');
            $this->line('File path: ' . $fullPath);
            $this->line('');
            
            // Show summary
            $this->showSummary($date);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to generate report: ' . $e->getMessage());
            \Log::error('Daily audit report generation failed', [
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function showSummary($date)
    {
        $orders = \App\Models\Order::whereDate('created_at', $date)->get();
        $totalOrders = $orders->count();
        
        if ($totalOrders === 0) {
            $this->warn('No orders found for this date.');
            return;
        }

        $ordersWithPackageQty = 0;
        $ordersWithBonification = 0;
        $ordersWithSuspiciousPricing = 0;

        foreach ($orders as $order) {
            if ($order->products->contains(fn($p) => !empty($p->package_quantity) && $p->package_quantity > 0)) {
                $ordersWithPackageQty++;
            }
            if ($order->products->contains(fn($p) => $p->is_bonification == 1)) {
                $ordersWithBonification++;
            }
            if ($order->products->contains(fn($p) => $p->price < 500)) {
                $ordersWithSuspiciousPricing++;
            }
        }

        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Orders', $totalOrders, '100%'],
                ['With Package Quantity', $ordersWithPackageQty, $this->percentage($ordersWithPackageQty, $totalOrders)],
                ['With Bonification', $ordersWithBonification, $this->percentage($ordersWithBonification, $totalOrders)],
                ['With Suspicious Pricing (<$500)', $ordersWithSuspiciousPricing, $this->percentage($ordersWithSuspiciousPricing, $totalOrders)],
            ]
        );
    }

    private function percentage($count, $total)
    {
        if ($total == 0) return '0%';
        return round(($count / $total) * 100, 1) . '%';
    }
}
