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
    protected $signature = 'orders:daily-audit {from_date? : Start date to audit from (Y-m-d format). Defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate audit report for orders from a date until now, showing package quantities, bonifications, and suspicious SOAP pricing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromDate = $this->argument('from_date') ?? now()->subDay()->format('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2026-01-21)');
            return 1;
        }

        $now = now()->format('Y-m-d H:i:s');
        $this->info("Generating audit report from: {$fromDate} until now ({$now})");

        try {
            // Generate filename
            $todayStr = now()->format('Ymd');
            $fromDateStr = str_replace('-', '', $fromDate);
            $filename = 'reports/orders_audit_' . $fromDateStr . '_to_' . $todayStr . '_' . time() . '.xlsx';
            
            // Export to storage
            Excel::store(new OrdersDailyAuditExport($fromDate), $filename, 'local');
            
            $fullPath = Storage::disk('local')->path($filename);
            
            $this->info('✓ Report generated successfully!');
            $this->line('');
            $this->line('File path: ' . $fullPath);
            $this->line('');
            
            // Show summary
            $this->showSummary($fromDate);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to generate report: ' . $e->getMessage());
            \Log::error('Daily audit report generation failed', [
                'from_date' => $fromDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function showSummary($fromDate)
    {
        $orders = \App\Models\Order::where('created_at', '>=', $fromDate . ' 00:00:00')
            ->where('created_at', '<=', now())
            ->get();
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
            
            // Check SOAP prices for suspicious pricing
            // Only flag prices > $0 and < $500
            $soapPrices = $this->parseSoapPrices($order->request);
            foreach ($soapPrices as $soapProduct) {
                $price = (float) $soapProduct['unitPrice'];
                if ($price > 0 && $price < 500) {
                    $ordersWithSuspiciousPricing++;
                    break;
                }
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

    /**
     * Parse SOAP XML to extract product prices
     */
    private function parseSoapPrices($soapXml): array
    {
        if (empty($soapXml)) {
            return [];
        }

        $products = [];
        
        try {
            $xml = simplexml_load_string($soapXml);
            
            if ($xml === false) {
                return [];
            }

            $xml->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
            $listDetails = $xml->xpath('//dyn:listDetails');
            
            // Fallback: Try without namespace
            if (!$listDetails || count($listDetails) === 0) {
                $listDetails = $xml->xpath('//listDetails');
            }
            
            if (!$listDetails || count($listDetails) === 0) {
                return [];
            }

            foreach ($listDetails as $detail) {
                $detail->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
                
                $priceNodes = $detail->xpath('dyn:unitPrice');
                if (empty($priceNodes)) $priceNodes = $detail->xpath('unitPrice');
                
                $unitPrice = (float)($priceNodes[0] ?? 0);
                
                $products[] = [
                    'unitPrice' => $unitPrice,
                ];
            }
        } catch (\Exception $e) {
            // Silent fail for summary
        }

        return $products;
    }

    private function percentage($count, $total)
    {
        if ($total == 0) return '0%';
        return round(($count / $total) * 100, 1) . '%';
    }
}
