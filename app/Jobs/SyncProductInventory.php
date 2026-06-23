<?php

namespace App\Jobs;

use App\Models\InventorySyncLog;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\ZoneWarehouse;
use App\Services\MicrosoftTokenService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        Log::info('=== INVENTORY SYNC JOB STARTED ===', [
            'timestamp' => now()->toDateTimeString(),
            'job_id' => $this->job ? $this->job->getJobId() : 'N/A',
        ]);

        $bodegas = collect();
        $processedBodegas = 0;

        // Respect inventory enabled setting
        $inventoryEnabled = Setting::getByKeyWithDefault('inventory_enabled', '1');
        if (! ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true)) {
            Log::info('Inventory sync skipped - inventory is disabled');
            $this->updateSyncProgress('skipped', 'Inventario deshabilitado; sincronización omitida.');

            return;
        }

        try {
            $token = MicrosoftTokenService::currentOrRefresh();
        } catch (\Throwable $e) {
            Log::error('Inventory sync failed - Token refresh error: '.$e->getMessage());
            $this->logToDatabase(null, 'error', 'Token refresh error: '.$e->getMessage());
            $this->updateSyncProgress('error', 'Error refrescando token: '.$e->getMessage());

            return;
        }

        $tokenSetting = Setting::where('key', 'microsoft_token')->first();
        Log::info('Using Microsoft token for inventory sync', [
            'token_updated_at' => $tokenSetting?->updated_at?->toDateTimeString(),
            'minutes_since_update' => $tokenSetting?->updated_at?->diffInMinutes(now()),
        ]);

        $bodegas = ZoneWarehouse::query()->select('bodega_code')->distinct()->pluck('bodega_code');
        if ($bodegas->isEmpty()) {
            Log::warning('Inventory sync failed - No bodegas found for inventory sync');
            $this->logToDatabase(null, 'error', 'No bodegas configured in zone_warehouses table');
            $this->updateSyncProgress('error', 'No hay bodegas configuradas para sincronizar.');

            return;
        }

        $this->updateSyncProgress('running', 'Sincronización iniciada.', null, 0, $bodegas->count());

        Log::info('Starting inventory sync for bodegas', [
            'bodegas' => $bodegas->toArray(),
            'count' => $bodegas->count(),
        ]);

        foreach ($bodegas as $bodega) {
            $this->updateSyncProgress('running', "Procesando bodega {$bodega}.", (string) $bodega, $processedBodegas, $bodegas->count());
            Log::info("Processing bodega: {$bodega}");

            try {
                [$response, $token] = $this->fetchInventorySoap($bodega, $token);

                Log::info("SOAP response received for bodega {$bodega}", [
                    'status' => $response->status(),
                    'body_length' => strlen($response->body()),
                ]);

                if (! $response->successful()) {
                    $errorMsg = "HTTP request failed with status {$response->status()}";
                    Log::error("Inventory sync error for bodega {$bodega}: {$errorMsg}", [
                        'response_body' => substr($response->body(), 0, 1000),
                    ]);

                    $this->logToDatabase($bodega, 'error', $errorMsg, null, $response->body());
                    $processedBodegas++;
                    $this->updateSyncProgress('running', "Error HTTP en bodega {$bodega}; continuando.", (string) $bodega, $processedBodegas, $bodegas->count(), $errorMsg);

                    continue;
                }

                $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $response->body());
                $xml = @simplexml_load_string($xmlString);
                if (! $xml) {
                    $errorMsg = 'Failed to parse SOAP XML response';
                    Log::warning("Inventory sync error for bodega {$bodega}: {$errorMsg}", [
                        'response_preview' => substr($response->body(), 0, 500),
                    ]);

                    $this->logToDatabase($bodega, 'error', $errorMsg, null, $response->body());
                    $processedBodegas++;
                    $this->updateSyncProgress('running', "Error leyendo XML de bodega {$bodega}; continuando.", (string) $bodega, $processedBodegas, $bodegas->count(), $errorMsg);

                    continue;
                }

                Log::info("SOAP XML parsed successfully for bodega {$bodega}");
            } catch (Exception $e) {
                $errorMsg = 'HTTP/Connection error: '.$e->getMessage();
                Log::error("Inventory sync error for bodega {$bodega}: {$errorMsg}");

                $this->logToDatabase($bodega, 'error', $errorMsg);
                $processedBodegas++;
                $this->updateSyncProgress('running', "Error de conexión en bodega {$bodega}; continuando.", (string) $bodega, $processedBodegas, $bodegas->count(), $errorMsg);

                continue;
            }

            $items = $xml->sBody->obtenerExistenciaDeInventarioEspecificaResponse->result->aobtenerExistenciaDeInventarioEspecificaResult->aListItemExists ?? [];
            $debugSkus = $this->debugSkus();

            // Aggregate totals per SKU for this bodega, excluding specific WMS locations
            $aggregatedBySku = [];
            $excludedByLocation = [];
            $itemCount = is_array($items) || $items instanceof \Traversable ? count($items) : 0;

            Log::info("Found {$itemCount} inventory items in SOAP response for bodega {$bodega}");

            if (empty($items)) {
                Log::info("No inventory items found for bodega {$bodega} - this may indicate all products should be set to 0");
                $this->logToDatabase($bodega, 'warning', 'No inventory items in SOAP response', [
                    'skus_received' => 0,
                    'products_updated' => 0,
                    'products_set_to_zero' => 0,
                ], $response->body());

                continue;
            }

            foreach ($items as $item) {
                $sku = trim((string) ($item->aItemId ?? ''));
                if ($sku === '') {
                    continue;
                }

                $wmsLocation = strtoupper(trim((string) ($item->aWMSLocation ?? $item->awMSLocation ?? $item->aWmsLocation ?? '')));
                if ($wmsLocation === 'EMPAQUE' || $wmsLocation === 'SALIDA') {
                    $normalizedSku = $this->normalizeSku($sku);
                    $excludedByLocation[$normalizedSku] ??= [];
                    $excludedByLocation[$normalizedSku][] = $wmsLocation;
                    // Skip excluded locations
                    continue;
                }

                $avail = (int) ((string) ($item->aAvailPhysical ?? '0'));
                $phys = (int) ((string) ($item->aPhysicalInvent ?? '0'));
                $resv = (int) ((string) ($item->aReservPhysical ?? '0'));

                if (! isset($aggregatedBySku[$sku])) {
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
                $updatedVariationKeysForBodega = [];
                $matchedProductSkus = [];
                $matchedVariationSkus = [];
                $unmatchedResponseSkus = [];
                $skippedVariableParentSkus = [];

                foreach ($aggregatedBySku as $sku => $totals) {
                    $normalizedSku = $this->normalizeSku($sku);
                    // Find ALL simple products with this SKU. Variable parent SKUs are placeholders
                    // and must not create parent-level inventory rows.
                    $products = Product::query()
                        ->matchingSku($sku)
                        ->whereNull('variation_id')
                        ->whereDoesntHave('items')
                        ->get();

                    $variableParentProducts = Product::query()
                        ->matchingSku($sku)
                        ->where(function ($query) {
                            $query->whereNotNull('variation_id')
                                ->orWhereHas('items');
                        })
                        ->get();
                    if ($variableParentProducts->isNotEmpty()) {
                        $skippedVariableParentSkus[$normalizedSku] = $variableParentProducts
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->all();
                    }

                    // Update inventory for each product with the same SKU
                    foreach ($products as $product) {
                        ProductInventory::updateOrCreate([
                            'product_id' => $product->id,
                            'variation_item_id' => null,
                            'bodega_code' => $bodega,
                        ], [
                            'source_sku' => $sku,
                            'available' => (int) ($totals['available'] ?? 0),
                            'physical' => (int) ($totals['physical'] ?? 0),
                            'reserved' => (int) ($totals['reserved'] ?? 0),
                        ]);

                        // Track that this product was updated for THIS bodega
                        $updatedProductIdsForBodega[] = $product->id;
                        $matchedProductSkus[$normalizedSku] = true;
                    }

                    // Variable catalog products store the Dynamics SKU on the variation pivot.
                    // Sync those rows directly on the parent product plus variation item.
                    $variationRows = DB::table('product_item_variation')
                        ->join('products', 'product_item_variation.product_id', '=', 'products.id')
                        ->whereRaw('UPPER(TRIM(product_item_variation.sku)) = ?', [mb_strtoupper(trim($sku))])
                        ->where(function ($query) {
                            $query->where('products.inventory_opt_out', false)
                                ->orWhereNull('products.inventory_opt_out');
                        })
                        ->select('product_item_variation.product_id', 'product_item_variation.variation_item_id')
                        ->get();

                    if (Schema::hasTable('product_variations')) {
                        $legacyVariationRows = DB::table('product_variations')
                            ->join('products', 'product_variations.product_id', '=', 'products.id')
                            ->whereRaw('UPPER(TRIM(product_variations.sku)) = ?', [mb_strtoupper(trim($sku))])
                            ->where(function ($query) {
                                $query->where('products.inventory_opt_out', false)
                                    ->orWhereNull('products.inventory_opt_out');
                            })
                            ->select('product_variations.product_id', 'product_variations.variation_items_id as variation_item_id')
                            ->get();

                        $variationRows = $variationRows
                            ->merge($legacyVariationRows)
                            ->filter(fn ($row) => ! empty($row->variation_item_id))
                            ->unique(fn ($row) => ((int) $row->product_id).':'.((int) $row->variation_item_id))
                            ->values();
                    }

                    foreach ($variationRows as $variationRow) {
                        ProductInventory::updateOrCreate([
                            'product_id' => (int) $variationRow->product_id,
                            'variation_item_id' => (int) $variationRow->variation_item_id,
                            'bodega_code' => $bodega,
                        ], [
                            'source_sku' => $sku,
                            'available' => (int) ($totals['available'] ?? 0),
                            'physical' => (int) ($totals['physical'] ?? 0),
                            'reserved' => (int) ($totals['reserved'] ?? 0),
                        ]);

                        $updatedVariationKeysForBodega[] = ((int) $variationRow->product_id).':'.((int) $variationRow->variation_item_id);
                        $matchedVariationSkus[$normalizedSku] = true;
                    }

                    if ($products->isEmpty() && $variationRows->isEmpty()) {
                        $unmatchedResponseSkus[] = $sku;
                    }
                }

                // Now set inventory to 0 for products NOT in this bodega's SOAP response
                // Only for products that have inventory management enabled
                $managedProducts = Product::where(function ($query) {
                    $query->where('inventory_opt_out', false)
                        ->orWhereNull('inventory_opt_out');
                })
                    ->whereNull('variation_id')
                    ->whereDoesntHave('items')
                    ->get();

                $removedVariableParentInventoryRows = ProductInventory::query()
                    ->where('bodega_code', $bodega)
                    ->whereNull('variation_item_id')
                    ->whereHas('product', function ($query) {
                        $query->whereNotNull('variation_id')
                            ->orWhereHas('items');
                    })
                    ->delete();

                $setToZeroCount = 0;
                foreach ($managedProducts as $product) {
                    // If this product was NOT in the SOAP response for this bodega, set to 0
                    if (! in_array($product->id, $updatedProductIdsForBodega)) {
                        ProductInventory::updateOrCreate([
                            'product_id' => $product->id,
                            'variation_item_id' => null,
                            'bodega_code' => $bodega,
                        ], [
                            'available' => 0,
                            'physical' => 0,
                            'reserved' => 0,
                        ]);
                        $setToZeroCount++;
                    }
                }

                $variationRowsToZero = DB::table('product_item_variation')
                    ->join('products', 'product_item_variation.product_id', '=', 'products.id')
                    ->whereNotNull('product_item_variation.sku')
                    ->where('product_item_variation.sku', '!=', '')
                    ->where(function ($query) {
                        $query->where('products.inventory_opt_out', false)
                            ->orWhereNull('products.inventory_opt_out');
                    })
                    ->select('product_item_variation.product_id', 'product_item_variation.variation_item_id', 'product_item_variation.sku')
                    ->get();

                if (Schema::hasTable('product_variations')) {
                    $legacyVariationRowsToZero = DB::table('product_variations')
                        ->join('products', 'product_variations.product_id', '=', 'products.id')
                        ->whereNotNull('product_variations.sku')
                        ->where('product_variations.sku', '!=', '')
                        ->where(function ($query) {
                            $query->where('products.inventory_opt_out', false)
                                ->orWhereNull('products.inventory_opt_out');
                        })
                        ->select('product_variations.product_id', 'product_variations.variation_items_id as variation_item_id', 'product_variations.sku')
                        ->get();

                    $variationRowsToZero = $variationRowsToZero
                        ->merge($legacyVariationRowsToZero)
                        ->filter(fn ($row) => ! empty($row->variation_item_id))
                        ->unique(fn ($row) => ((int) $row->product_id).':'.((int) $row->variation_item_id))
                        ->values();
                }

                foreach ($variationRowsToZero as $variationRow) {
                    $variationKey = ((int) $variationRow->product_id).':'.((int) $variationRow->variation_item_id);
                    if (in_array($variationKey, $updatedVariationKeysForBodega, true)) {
                        continue;
                    }

                    ProductInventory::updateOrCreate([
                        'product_id' => (int) $variationRow->product_id,
                        'variation_item_id' => (int) $variationRow->variation_item_id,
                        'bodega_code' => $bodega,
                    ], [
                        'source_sku' => trim((string) $variationRow->sku),
                        'available' => 0,
                        'physical' => 0,
                        'reserved' => 0,
                    ]);
                    $setToZeroCount++;
                }

                Log::info("Inventory sync completed for bodega {$bodega}", [
                    'bodega' => $bodega,
                    'skus_received' => count($aggregatedBySku),
                    'products_updated' => count($updatedProductIdsForBodega) + count($updatedVariationKeysForBodega),
                    'products_set_to_zero' => $setToZeroCount,
                    'total_managed_products' => $managedProducts->count(),
                    'variable_parent_inventory_rows_removed' => $removedVariableParentInventoryRows,
                ]);

                $configuredVariationSkus = $this->configuredVariationSkus();
                $responseSkuKeys = collect(array_keys($aggregatedBySku))
                    ->map(fn (string $sku) => $this->normalizeSku($sku))
                    ->filter()
                    ->values()
                    ->all();
                $responseSkuLookup = array_fill_keys($responseSkuKeys, true);
                $missingConfiguredVariationSkus = collect(array_keys($configuredVariationSkus))
                    ->reject(fn (string $sku) => isset($responseSkuLookup[$sku]) || isset($excludedByLocation[$sku]))
                    ->values()
                    ->take(50)
                    ->all();

                Log::info("Inventory variation sync diagnostics for bodega {$bodega}", [
                    'bodega' => $bodega,
                    'configured_variation_skus' => count($configuredVariationSkus),
                    'variation_skus_matched_from_response' => count($matchedVariationSkus),
                    'response_skus_without_product_or_variation_match_count' => count($unmatchedResponseSkus),
                    'response_skus_without_product_or_variation_match_sample' => array_slice($unmatchedResponseSkus, 0, 50),
                    'skipped_variable_parent_skus' => $skippedVariableParentSkus,
                    'configured_variation_skus_missing_from_response_sample' => $missingConfiguredVariationSkus,
                    'excluded_by_location_sample' => array_slice($excludedByLocation, 0, 50, true),
                    'debug_skus' => $this->buildDebugSkuReport(
                        debugSkus: $debugSkus,
                        aggregatedBySku: $aggregatedBySku,
                        excludedByLocation: $excludedByLocation,
                        configuredVariationSkus: $configuredVariationSkus,
                        matchedProductSkus: $matchedProductSkus,
                        matchedVariationSkus: $matchedVariationSkus,
                        skippedVariableParentSkus: $skippedVariableParentSkus
                    ),
                ]);

                // Store sync log with full SOAP response
                $this->logToDatabase($bodega, 'success', null, [
                    'skus_received' => count($aggregatedBySku),
                    'products_updated' => count($updatedProductIdsForBodega) + count($updatedVariationKeysForBodega),
                    'products_set_to_zero' => $setToZeroCount,
                    'skus_in_response' => array_keys($aggregatedBySku),
                ], isset($response) ? $response->body() : null);

                DB::commit();
                $processedBodegas++;
                $this->updateSyncProgress('running', "Bodega {$bodega} completada.", (string) $bodega, $processedBodegas, $bodegas->count());
            } catch (Exception $e) {
                DB::rollBack();
                $errorMsg = 'Database error during inventory update: '.$e->getMessage();
                Log::error("Inventory sync error for bodega {$bodega}: {$errorMsg}", [
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->logToDatabase($bodega, 'error', $errorMsg);
                $processedBodegas++;
                $this->updateSyncProgress('running', "Error guardando bodega {$bodega}; continuando.", (string) $bodega, $processedBodegas, $bodegas->count(), $errorMsg);
            }
        }

        // Update last sync timestamp setting
        try {
            Setting::updateOrCreate(
                ['key' => 'inventory_last_synced_at'],
                ['name' => 'Inventario - última sincronización', 'value' => now()->toDateTimeString(), 'show' => true]
            );
            Log::info('=== INVENTORY SYNC JOB COMPLETED ===', [
                'timestamp' => now()->toDateTimeString(),
            ]);
            $this->updateSyncProgress('completed', 'Sincronización completada.', null, $processedBodegas, $bodegas->count(), null, now()->toDateTimeString());
        } catch (Exception $e) {
            Log::error('Failed to update inventory sync timestamp: '.$e->getMessage());
            $this->updateSyncProgress('error', 'Sincronización terminó, pero no se pudo guardar la fecha final: '.$e->getMessage(), null, $processedBodegas, $bodegas->count(), $e->getMessage());
        }
    }

    /**
     * Helper method to log sync results to database (with error handling)
     */
    private function logToDatabase(?string $bodega, string $status, ?string $errorMessage = null, ?array $stats = null, ?string $soapResponse = null): void
    {
        try {
            InventorySyncLog::create([
                'bodega_code' => $bodega ?? 'GENERAL',
                'skus_received' => $stats['skus_received'] ?? 0,
                'products_updated' => $stats['products_updated'] ?? 0,
                'products_set_to_zero' => $stats['products_set_to_zero'] ?? 0,
                'skus_in_response' => $stats['skus_in_response'] ?? null,
                'soap_response' => $soapResponse,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (Exception $e) {
            // If DB logging fails (e.g., table doesn't exist), log to file instead
            Log::warning('Could not write to inventory_sync_logs table: '.$e->getMessage());
            Log::info('Inventory sync log (DB write failed)', [
                'bodega_code' => $bodega,
                'status' => $status,
                'error_message' => $errorMessage,
                'skus_received' => $stats['skus_received'] ?? 0,
                'products_updated' => $stats['products_updated'] ?? 0,
            ]);
        }
    }

    private function updateSyncProgress(
        string $status,
        string $message,
        ?string $currentBodega = null,
        ?int $processedBodegas = null,
        ?int $totalBodegas = null,
        ?string $errorMessage = null,
        ?string $finishedAt = null
    ): void {
        $processed = max(0, (int) ($processedBodegas ?? 0));
        $total = max(0, (int) ($totalBodegas ?? 0));
        $percentage = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 0;

        $payload = [
            'status' => $status,
            'message' => $message,
            'current_bodega' => $currentBodega,
            'processed_bodegas' => $processed,
            'total_bodegas' => $total,
            'percentage' => $status === 'completed' ? 100 : $percentage,
            'error_message' => $errorMessage,
            'started_at' => data_get($this->currentSyncProgress(), 'started_at') ?? now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'finished_at' => $finishedAt,
        ];

        Setting::updateOrCreate(
            ['key' => 'inventory_sync_progress'],
            ['name' => 'Inventario - progreso de sincronización', 'value' => json_encode($payload), 'show' => false]
        );
    }

    private function currentSyncProgress(): array
    {
        $raw = Setting::getByKey('inventory_sync_progress');
        if (! $raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * SKUs we always call out while diagnosing variation sync issues.
     */
    private function debugSkus(): array
    {
        $configured = Setting::getByKey('inventory_sync_debug_skus');
        $skus = $configured
            ? preg_split('/[\s,;]+/', (string) $configured)
            : ['S4PANIAMAX10', 'LYGUANT7AMX2', 'LYGUANT9AMX2'];

        return collect($skus)
            ->map(fn ($sku) => $this->normalizeSku((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeSku(string $sku): string
    {
        return mb_strtoupper(trim($sku));
    }

    private function configuredVariationSkus(): array
    {
        $rows = DB::table('product_item_variation')
            ->join('products', 'product_item_variation.product_id', '=', 'products.id')
            ->whereNotNull('product_item_variation.sku')
            ->where('product_item_variation.sku', '!=', '')
            ->where(function ($query) {
                $query->where('products.inventory_opt_out', false)
                    ->orWhereNull('products.inventory_opt_out');
            })
            ->select('product_item_variation.product_id', 'product_item_variation.variation_item_id', 'product_item_variation.sku')
            ->get();

        if (Schema::hasTable('product_variations')) {
            $legacyRows = DB::table('product_variations')
                ->join('products', 'product_variations.product_id', '=', 'products.id')
                ->whereNotNull('product_variations.sku')
                ->where('product_variations.sku', '!=', '')
                ->where(function ($query) {
                    $query->where('products.inventory_opt_out', false)
                        ->orWhereNull('products.inventory_opt_out');
                })
                ->select('product_variations.product_id', 'product_variations.variation_items_id as variation_item_id', 'product_variations.sku')
                ->get();

            $rows = $rows->merge($legacyRows);
        }

        $result = [];
        foreach ($rows as $row) {
            $normalizedSku = $this->normalizeSku((string) $row->sku);
            if ($normalizedSku === '' || empty($row->variation_item_id)) {
                continue;
            }

            $result[$normalizedSku][] = [
                'product_id' => (int) $row->product_id,
                'variation_item_id' => (int) $row->variation_item_id,
            ];
        }

        return $result;
    }

    private function buildDebugSkuReport(
        array $debugSkus,
        array $aggregatedBySku,
        array $excludedByLocation,
        array $configuredVariationSkus,
        array $matchedProductSkus,
        array $matchedVariationSkus,
        array $skippedVariableParentSkus
    ): array {
        $responseSkuLookup = collect(array_keys($aggregatedBySku))
            ->mapWithKeys(fn (string $sku) => [$this->normalizeSku($sku) => $sku])
            ->all();

        $report = [];
        foreach ($debugSkus as $sku) {
            $responseSku = $responseSkuLookup[$sku] ?? null;
            $report[$sku] = [
                'in_soap_response' => $responseSku !== null,
                'excluded_locations' => $excludedByLocation[$sku] ?? [],
                'configured_as_variation_sku' => isset($configuredVariationSkus[$sku]),
                'variation_targets' => $configuredVariationSkus[$sku] ?? [],
                'matched_product_sku' => isset($matchedProductSkus[$sku]),
                'matched_variation_sku' => isset($matchedVariationSkus[$sku]),
                'skipped_variable_parent_product_ids' => $skippedVariableParentSkus[$sku] ?? [],
                'totals' => $responseSku ? ($aggregatedBySku[$responseSku] ?? null) : null,
            ];
        }

        return $report;
    }

    /**
     * @return array{0: \Illuminate\Http\Client\Response, 1: string}
     */
    private function fetchInventorySoap(string $bodega, string $token): array
    {
        $body = $this->buildSoapBody($bodega);
        $url = config('microsoft.resource').'/soap/services/DIITDWSSalesForceGroup';
        $headers = [
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/obtenerExistenciaDeInventarioEspecifica',
        ];

        $response = Http::withHeaders(array_merge($headers, [
            'Authorization' => "Bearer {$token}",
        ]))->timeout(30)->send('POST', $url, [
            'body' => $body,
        ]);

        if ($response->status() === 401) {
            Log::warning('Inventory sync: Microsoft token rejected, refreshing and retrying', [
                'bodega' => $bodega,
            ]);
            $token = MicrosoftTokenService::currentOrRefresh(forceRefresh: true);
            $response = Http::withHeaders(array_merge($headers, [
                'Authorization' => "Bearer {$token}",
            ]))->timeout(30)->send('POST', $url, [
                'body' => $body,
            ]);
        }

        return [$response, $token];
    }

    private function buildSoapBody(string $bodega): string
    {
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">'
            .'<soapenv:Header>'
            .'<dat:CallContext>'
            .'<dat:Company>TRX</dat:Company>'
            .'</dat:CallContext>'
            .'</soapenv:Header>'
            .'<soapenv:Body>'
            .'<tem:obtenerExistenciaDeInventarioEspecifica>'
            .'<tem:_obtenerExistenciaDeInventarioEspecifica>'
            .'<dyn:inventBatchId></dyn:inventBatchId>'
            .'<dyn:inventLocationId>'.htmlspecialchars($bodega).'</dyn:inventLocationId>'
            .'<dyn:inventSerialId></dyn:inventSerialId>'
            .'<dyn:itemBarCode></dyn:itemBarCode>'
            .'<dyn:itemId></dyn:itemId>'
            .'<dyn:wMSLocation></dyn:wMSLocation>'
            .'</tem:_obtenerExistenciaDeInventarioEspecifica>'
            .'</tem:obtenerExistenciaDeInventarioEspecifica>'
            .'</soapenv:Body>'
            .'</soapenv:Envelope>';
    }
}
