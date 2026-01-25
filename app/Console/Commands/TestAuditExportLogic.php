<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Exports\OrdersDailyAuditExport;
use Illuminate\Console\Command;

class TestAuditExportLogic extends Command
{
    protected $signature = 'orders:test-audit-export {order_id}';
    protected $description = 'Test the audit export logic for a specific order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::with(['products.product', 'user', 'seller', 'zone'])->find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }

        $this->info("Testing audit export logic for Order #{$order->id}");
        $this->line('');

        // Create instance of export to access the parseSoapPrices method
        $export = new OrdersDailyAuditExport();
        
        // Use reflection to call private method
        $reflection = new \ReflectionClass($export);
        $parseSoapMethod = $reflection->getMethod('parseSoapPrices');
        $parseSoapMethod->setAccessible(true);
        
        // Parse SOAP prices
        $soapPrices = $parseSoapMethod->invoke($export, $order->request);
        
        $this->info('SOAP Prices Parsed:');
        $this->line(json_encode($soapPrices, JSON_PRETTY_PRINT));
        $this->line('');
        
        // Check for suspicious pricing
        $hasSuspiciousPricing = false;
        $suspiciousProducts = [];
        
        if (!empty($soapPrices)) {
            foreach ($soapPrices as $soapProduct) {
                $unitPrice = (float) $soapProduct['unitPrice'];
                
                $this->line("Checking: SKU={$soapProduct['sku']}, Price={$unitPrice}");
                
                if ($unitPrice < 500) {
                    $hasSuspiciousPricing = true;
                    $suspiciousProducts[] = sprintf(
                        'SKU: %s ($%s x %s)',
                        $soapProduct['sku'] ?: 'N/A',
                        number_format($unitPrice, 2),
                        $soapProduct['qty']
                    );
                    $this->warn("  ^^ SUSPICIOUS! Price < 500");
                }
            }
        } else {
            $this->warn('soapPrices is EMPTY!');
        }
        
        $this->line('');
        $this->info('Results:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Has Suspicious Pricing', $hasSuspiciousPricing ? 'YES ⚠️' : 'NO'],
                ['Suspicious Products Count', count($suspiciousProducts)],
                ['Suspicious Products', implode('; ', $suspiciousProducts) ?: 'None'],
            ]
        );
        
        $this->line('');
        
        if ($hasSuspiciousPricing) {
            $this->info('✅ Order SHOULD be flagged in the report');
        } else {
            $this->warn('❌ Order will NOT be flagged in the report');
        }
        
        // Test the actual map method
        $this->line('');
        $this->info('Testing actual map() method output...');
        
        $mapMethod = $reflection->getMethod('map');
        $mapMethod->setAccessible(true);
        
        $mappedData = $mapMethod->invoke($export, $order);
        
        $this->line('');
        $this->info('Mapped row data:');
        $this->table(
            ['Column', 'Value'],
            [
                ['ID', $mappedData[0]],
                ['Created', $mappedData[1]],
                ['Customer', $mappedData[2]],
                ['Email', $mappedData[3]],
                ['Status', $mappedData[4]],
                ['Total', $mappedData[5]],
                ['Discount', $mappedData[6]],
                ['Product Count', $mappedData[7]],
                ['Has Package Qty', $mappedData[8]],
                ['Has Bonification', $mappedData[9]],
                ['Suspicious Pricing (<$500)', $mappedData[10]],
                ['Suspicious Products', $mappedData[11]],
            ]
        );

        return 0;
    }
}
