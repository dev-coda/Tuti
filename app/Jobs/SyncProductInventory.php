<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\ZoneWarehouse;
use App\Models\InventorySyncLog;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncProductInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // Increased to 10 minutes for large syncs

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [60, 300, 600]; // 1 min, 5 min, 10 min

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Force Redis queue connection for async processing with Horizon
        // This ensures the job runs async even if default queue is 'sync'
        $this->onConnection('redis');
        $this->onQueue('inventory');
    }

    public function handle(): void
    {
        // Respect inventory enabled setting
        $inventoryEnabled = Setting::getByKeyWithDefault('inventory_enabled', '1');
        if (!($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true)) {
            return;
        }
        $tokenSetting = Setting::where('key', 'microsoft_token')->first();
        if (!$tokenSetting) {
            Log::warning('Missing microsoft_token setting.');
            return;
        }

        if ($tokenSetting->updated_at->diffInMinutes(now()) > 2) {
            try {
                Artisan::call('app:get-token');
                $tokenSetting = Setting::where('key', 'microsoft_token')->first();
                if (!$tokenSetting) {
                    Log::error('Failed to refresh microsoft token - token setting not found after refresh');
                    return;
                }
            } catch (Exception $e) {
                Log::error('Failed to refresh microsoft token: ' . $e->getMessage());
                return;
            }
        }

        $token = $tokenSetting->value;

        $bodegas = ZoneWarehouse::query()->select('bodega_code')->distinct()->pluck('bodega_code');
        if ($bodegas->isEmpty()) {
            Log::warning('No bodegas found for inventory sync');
            return;
        }

        foreach ($bodegas as $bodega) {
            $body = $this->buildSoapBody($bodega);

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'text/xml;charset=UTF-8',
                    'SOAPAction' => 'http://tempuri.org/DWSSalesForce/obtenerExistenciaDeInventarioEspecifica',
                    'Authorization' => "Bearer {$token}",
                ])->timeout(30)->send('POST', env('MICROSOFT_RESOURCE_URL', 'https://uattrx.sandbox.operations.dynamics.com/') . '/soap/services/DIITDWSSalesForceGroup', [
                    'body' => $body,
                ]);

                if (!$response->successful()) {
                    Log::error("Inventory sync HTTP request failed for bodega {$bodega}: " . $response->status() . " - " . $response->body());
                    
                    // Log the failed request
                    InventorySyncLog::create([
                        'bodega_code' => $bodega,
                        'status' => 'error',
                        'error_message' => 'HTTP request failed: ' . $response->status(),
                    ]);
                    
                    continue;
                }

                $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $response->body());
                $xml = @simplexml_load_string($xmlString);
                if (!$xml) {
                    Log::warning("Inventory SOAP parse failed for bodega {$bodega}. Response: " . substr($response->body(), 0, 500));
                    
                    // Log the failed parse
                    InventorySyncLog::create([
                        'bodega_code' => $bodega,
                        'soap_response' => $response->body(),
                        'status' => 'error',
                        'error_message' => 'Failed to parse SOAP XML response',
                    ]);
                    
                    continue;
                }
            } catch (Exception $e) {
                Log::error("Inventory sync HTTP error for bodega {$bodega}: " . $e->getMessage());
                
                // Log the HTTP error
                InventorySyncLog::create([
                    'bodega_code' => $bodega,
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);
                
                continue;
            }

            $items = $xml->sBody->obtenerExistenciaDeInventarioEspecificaResponse->result->aobtenerExistenciaDeInventarioEspecificaResult->aListItemExists ?? [];

            // Aggregate totals per SKU for this bodega, excluding specific WMS locations
            $aggregatedBySku = [];
            if (empty($items)) {
                Log::info("No inventory items found for bodega {$bodega}");
                continue;
            }
            
            foreach ($items as $item) {
                $sku = trim((string) ($item->aItemId ?? ''));
                if ($sku === '') {
                    continue;
                }

                $wmsLocation = strtoupper(trim((string) ($item->aWMSLocation ?? $item->awMSLocation ?? $item->aWmsLocation ?? '')));
                if ($wmsLocation === 'EMPAQUE' || $wmsLocation === 'SALIDA') {
                    // Skip excluded locations
                    continue;
                }

                $avail = (int) ((string) ($item->aAvailPhysical ?? '0'));
                $phys = (int) ((string) ($item->aPhysicalInvent ?? '0'));
                $resv = (int) ((string) ($item->aReservPhysical ?? '0'));

                if (!isset($aggregatedBySku[$sku])) {
                    $aggregatedBySku[$sku] = [
                        'available' => 0,
                        'physical' => 0,
                        'reserved' => 0,
                    ];
                }

                $aggregatedBySku[$sku]['available'] += $avail;
                $aggregatedBySku[$sku]['physical'] += $phys;
                $aggregatedBySku[$sku]['reserved'] += $resv;
            }

            if (empty($aggregatedBySku)) {
                Log::info("No valid inventory data to sync for bodega {$bodega}");
                continue;
            }

            DB::beginTransaction();
            try {
                // Track which product IDs were updated for THIS specific bodega
                $updatedProductIdsForBodega = [];
                
                foreach ($aggregatedBySku as $sku => $totals) {
                    // Find ALL products with this SKU (handles duplicate SKUs)
                    $products = Product::where('sku', $sku)->get();
                    if ($products->isEmpty()) {
                        continue;
                    }

                    // Update inventory for each product with the same SKU
                    foreach ($products as $product) {
                        ProductInventory::updateOrCreate([
                            'product_id' => $product->id,
                            'bodega_code' => $bodega,
                        ], [
                            'available' => (int) ($totals['available'] ?? 0),
                            'physical' => (int) ($totals['physical'] ?? 0),
                            'reserved' => (int) ($totals['reserved'] ?? 0),
                        ]);
                        
                        // Track that this product was updated for THIS bodega
                        $updatedProductIdsForBodega[] = $product->id;
                    }
                }
                
                // Now set inventory to 0 for products NOT in this bodega's SOAP response
                // Only for products that have inventory management enabled
                $managedProducts = Product::where(function($query) {
                    $query->where('inventory_opt_out', false)
                          ->orWhereNull('inventory_opt_out');
                })->get();
                
                $setToZeroCount = 0;
                foreach ($managedProducts as $product) {
                    // If this product was NOT in the SOAP response for this bodega, set to 0
                    if (!in_array($product->id, $updatedProductIdsForBodega)) {
                        ProductInventory::updateOrCreate([
                            'product_id' => $product->id,
                            'bodega_code' => $bodega,
                        ], [
                            'available' => 0,
                            'physical' => 0,
                            'reserved' => 0,
                        ]);
                        $setToZeroCount++;
                    }
                }
                
                Log::info("Inventory sync completed for bodega {$bodega}", [
                    'bodega' => $bodega,
                    'skus_received' => count($aggregatedBySku),
                    'products_updated' => count($updatedProductIdsForBodega),
                    'products_set_to_zero' => $setToZeroCount,
                    'total_managed_products' => $managedProducts->count(),
                    'skus_in_response' => array_keys($aggregatedBySku),
                ]);
                
                // Store sync log with full SOAP response
                InventorySyncLog::create([
                    'bodega_code' => $bodega,
                    'skus_received' => count($aggregatedBySku),
                    'products_updated' => count($updatedProductIdsForBodega),
                    'products_set_to_zero' => $setToZeroCount,
                    'skus_in_response' => array_keys($aggregatedBySku),
                    'soap_response' => isset($response) ? $response->body() : null, // Store full XML response
                    'status' => 'success',
                ]);
                
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Inventory sync error for bodega {$bodega}: " . $e->getMessage());
                
                // Log the error in database
                try {
                    InventorySyncLog::create([
                        'bodega_code' => $bodega,
                        'status' => 'error',
                        'error_message' => $e->getMessage(),
                    ]);
                } catch (Exception $logException) {
                    Log::error("Failed to log inventory sync error: " . $logException->getMessage());
                }
            }
        }

        // Update last sync timestamp setting
        try {
            Setting::updateOrCreate(
                ['key' => 'inventory_last_synced_at'],
                ['name' => 'Inventario - última sincronización', 'value' => now()->toDateTimeString(), 'show' => true]
            );
        } catch (Exception $e) {
            Log::error('Failed to update inventory sync timestamp: ' . $e->getMessage());
        }
    }

    private function buildSoapBody(string $bodega): string
    {
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">'
            . '<soapenv:Header>'
            . '<dat:CallContext>'
            . '<dat:Company>TRX</dat:Company>'
            . '</dat:CallContext>'
            . '</soapenv:Header>'
            . '<soapenv:Body>'
            . '<tem:obtenerExistenciaDeInventarioEspecifica>'
            . '<tem:_obtenerExistenciaDeInventarioEspecifica>'
            . '<dyn:inventBatchId></dyn:inventBatchId>'
            . '<dyn:inventLocationId>' . htmlspecialchars($bodega) . '</dyn:inventLocationId>'
            . '<dyn:inventSerialId></dyn:inventSerialId>'
            . '<dyn:itemBarCode></dyn:itemBarCode>'
            . '<dyn:itemId></dyn:itemId>'
            . '<dyn:wMSLocation></dyn:wMSLocation>'
            . '</tem:_obtenerExistenciaDeInventarioEspecifica>'
            . '</tem:obtenerExistenciaDeInventarioEspecifica>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }
}
