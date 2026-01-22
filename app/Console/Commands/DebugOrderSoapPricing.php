<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class DebugOrderSoapPricing extends Command
{
    protected $signature = 'orders:debug-soap-pricing {order_id}';
    protected $description = 'Debug SOAP XML pricing for a specific order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }

        $this->info("Order ID: {$order->id}");
        $this->info("Created: {$order->created_at}");
        $this->info("Status: {$order->status_id}");
        $this->line('');

        if (empty($order->request)) {
            $this->warn('No SOAP request XML found for this order');
            return 1;
        }

        $this->info('SOAP XML Length: ' . strlen($order->request) . ' bytes');
        $this->line('');

        // Parse SOAP XML
        try {
            $xml = simplexml_load_string($order->request);
            
            if ($xml === false) {
                $this->error('Failed to parse XML');
                $this->line('');
                $this->line('XML Preview:');
                $this->line(substr($order->request, 0, 500));
                return 1;
            }

            // Register namespace
            $xml->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
            
            // Extract all listDetails elements
            $listDetails = $xml->xpath('//dyn:listDetails');
            
            if (!$listDetails || count($listDetails) === 0) {
                $this->warn('No products found in SOAP XML using XPath //dyn:listDetails');
                $this->line('');
                $this->info('Trying alternative namespaces...');
                
                // Try without namespace
                $listDetails = $xml->xpath('//listDetails');
                if ($listDetails && count($listDetails) > 0) {
                    $this->info('Found products WITHOUT namespace!');
                }
            }

            if (!$listDetails || count($listDetails) === 0) {
                $this->error('No products found in SOAP XML');
                $this->line('');
                $this->line('XML Structure Preview:');
                $this->line(substr($order->request, 0, 1000));
                return 1;
            }

            $this->info('Found ' . count($listDetails) . ' products in SOAP');
            $this->line('');

            $suspiciousCount = 0;
            $table = [];

            foreach ($listDetails as $index => $detail) {
                $detail->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
                
                $sku = (string)($detail->xpath('dyn:itemId')[0] ?? '');
                $unitPrice = (float)($detail->xpath('dyn:unitPrice')[0] ?? 0);
                $qty = (float)($detail->xpath('dyn:qty')[0] ?? 0);
                $discount = (float)($detail->xpath('dyn:discount')[0] ?? 0);
                
                // Only flag if price is > $0 and < $500
                $isSuspicious = ($unitPrice > 0 && $unitPrice < 500) ? '⚠️  YES' : 'No';
                
                if ($unitPrice > 0 && $unitPrice < 500) {
                    $suspiciousCount++;
                }

                $table[] = [
                    $index + 1,
                    $sku,
                    '$' . number_format($unitPrice, 2),
                    $qty,
                    $discount . '%',
                    $isSuspicious,
                ];
            }

            $this->table(
                ['#', 'SKU', 'Unit Price', 'Qty', 'Discount', 'Suspicious (<$500)'],
                $table
            );

            $this->line('');
            $this->info("Total products: " . count($listDetails));
            $this->info("Suspicious prices: " . $suspiciousCount);

            if ($suspiciousCount > 0) {
                $this->line('');
                $this->warn("This order SHOULD be flagged as having suspicious pricing!");
            } else {
                $this->line('');
                $this->info("No suspicious pricing found (all prices >= $500)");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Error parsing SOAP XML: ' . $e->getMessage());
            $this->line('');
            $this->line('Trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
