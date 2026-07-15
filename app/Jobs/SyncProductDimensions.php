<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductDimensionSyncLog;
use App\Models\Setting;
use App\Services\MicrosoftTokenService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Syncs product physical attributes (weight, width, height, depth) from the
 * Dynamics 365 F&O ObtenerArticulos SOAP webservice into the products table
 * (coordinadora_* columns), so Coordinadora quoting/packaging always works
 * with up to date sizing. Documented in "docs/dimensiones y peso.pdf".
 */
class SyncProductDimensions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    public $backoff = [60, 300, 600];

    /**
     * @param string|null $itemId Optional Dynamics ItemId (_itemId filter) to sync a single article.
     */
    public function __construct(
        public readonly ?string $itemId = null
    ) {
        $this->onConnection('redis');
        $this->onQueue('inventory');
    }

    public function handle(): void
    {
        Log::info('=== PRODUCT DIMENSION SYNC STARTED ===', [
            'item_id_filter' => $this->itemId,
        ]);

        $enabled = Setting::getByKeyWithDefault('dimension_sync_enabled', '1');
        if (! ($enabled === '1' || $enabled === 1 || $enabled === true)) {
            Log::info('Product dimension sync skipped - disabled by setting dimension_sync_enabled');

            return;
        }

        try {
            $token = MicrosoftTokenService::currentOrRefresh();
        } catch (\Throwable $e) {
            Log::error('Product dimension sync failed - token error: ' . $e->getMessage());
            $this->logToDatabase('error', errorMessage: 'Token error: ' . $e->getMessage());

            return;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction' => 'http://tempuri.org/DWSSalesForce/ObtenerArticulos',
                'Authorization' => "Bearer {$token}",
            ])
                ->timeout(120)
                ->connectTimeout(10)
                ->withOptions(['verify' => false, 'http_errors' => false])
                ->send('POST', $this->endpoint(), ['body' => $this->buildSoapBody()]);
        } catch (Exception $e) {
            Log::error('Product dimension sync failed - connection error: ' . $e->getMessage());
            $this->logToDatabase('error', errorMessage: 'Connection error: ' . $e->getMessage());

            throw $e;
        }

        if (! $response->successful()) {
            $errorMsg = "ObtenerArticulos failed with HTTP {$response->status()}";
            Log::error('Product dimension sync failed - ' . $errorMsg, [
                'body_preview' => substr($response->body(), 0, 1000),
            ]);
            $this->logToDatabase('error', errorMessage: $errorMsg);

            throw new Exception($errorMsg);
        }

        $items = $this->parseItems($response->body());
        if ($items === null) {
            $errorMsg = 'Failed to parse ObtenerArticulos SOAP XML response';
            Log::error('Product dimension sync failed - ' . $errorMsg, [
                'body_preview' => substr($response->body(), 0, 500),
            ]);
            $this->logToDatabase('error', errorMessage: $errorMsg);

            throw new Exception($errorMsg);
        }

        Log::info('Product dimension sync parsed articles', ['count' => count($items)]);

        $itemsWithDimensions = 0;
        $productsUpdated = 0;
        $unmatchedSkus = [];

        foreach ($items as $item) {
            if ($item['weight'] <= 0 && $item['width'] <= 0 && $item['height'] <= 0 && $item['depth'] <= 0) {
                continue;
            }
            $itemsWithDimensions++;

            $products = Product::query()->matchingSku($item['sku'])->get();
            if ($products->isEmpty()) {
                if (count($unmatchedSkus) < 100) {
                    $unmatchedSkus[] = $item['sku'];
                }

                continue;
            }

            foreach ($products as $product) {
                // Never overwrite a stored dimension with zero: Dynamics returns
                // 0.00 for articles whose physical attributes are not maintained.
                $updates = [];
                if ($item['weight'] > 0) {
                    $updates['coordinadora_weight_kg'] = $item['weight'];
                }
                if ($item['height'] > 0) {
                    $updates['coordinadora_height_cm'] = $item['height'];
                }
                if ($item['width'] > 0) {
                    $updates['coordinadora_width_cm'] = $item['width'];
                }
                if ($item['depth'] > 0) {
                    $updates['coordinadora_length_cm'] = $item['depth'];
                }

                if (!empty($updates)) {
                    $product->update($updates);
                    $productsUpdated++;
                }
            }
        }

        $this->logToDatabase('success', stats: [
            'items_received' => count($items),
            'items_with_dimensions' => $itemsWithDimensions,
            'products_updated' => $productsUpdated,
            'unmatched_skus' => $unmatchedSkus,
        ]);

        // Only a full (unfiltered) run counts as a complete sync.
        if ($this->itemId === null) {
            Setting::updateOrCreate(
                ['key' => 'product_dimensions_last_synced_at'],
                ['name' => 'Dimensiones de productos - última sincronización', 'value' => now()->toDateTimeString(), 'show' => false]
            );
        }

        Log::info('=== PRODUCT DIMENSION SYNC COMPLETED ===', [
            'items_received' => count($items),
            'items_with_dimensions' => $itemsWithDimensions,
            'products_updated' => $productsUpdated,
            'unmatched_skus_count' => count($unmatchedSkus),
        ]);
    }

    /**
     * @return array<int, array{sku: string, weight: float, width: float, height: float, depth: float}>|null
     */
    private function parseItems(string $responseBody): ?array
    {
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('a', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
        $listItems = $xml->xpath('//a:ListItem');
        if ($listItems === false) {
            return null;
        }

        $items = [];
        foreach ($listItems as $listItem) {
            $node = $listItem->children('http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
            $sku = trim((string) ($node->ItemId ?? ''));
            if ($sku === '') {
                continue;
            }

            $items[] = [
                'sku' => $sku,
                'weight' => $this->parseDecimal((string) ($node->weight ?? '0')),
                'width' => $this->parseDecimal((string) ($node->width ?? '0')),
                'height' => $this->parseDecimal((string) ($node->height ?? '0')),
                'depth' => $this->parseDecimal((string) ($node->depth ?? '0')),
            ];
        }

        return $items;
    }

    /**
     * Dynamics may format decimals with comma (e.g. TaxPercent "19,00").
     */
    private function parseDecimal(string $value): float
    {
        return (float) str_replace(',', '.', trim($value));
    }

    private function endpoint(): string
    {
        return rtrim((string) config('microsoft.resource'), '/') . '/soap/services/DYNPRODWSSalesForceGroup';
    }

    private function buildSoapBody(): string
    {
        $request = $this->itemId === null
            ? '<tem:ObtenerArticulos/>'
            : '<tem:ObtenerArticulos><tem:_itemId>' . htmlspecialchars($this->itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</tem:_itemId></tem:ObtenerArticulos>';

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org">'
            . '<soapenv:Header>'
            . '<dat:CallContext>'
            . '<dat:Company>trx</dat:Company>'
            . '</dat:CallContext>'
            . '</soapenv:Header>'
            . '<soapenv:Body>'
            . $request
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function logToDatabase(string $status, ?array $stats = null, ?string $errorMessage = null): void
    {
        try {
            ProductDimensionSyncLog::create([
                'status' => $status,
                'item_id_filter' => $this->itemId,
                'items_received' => $stats['items_received'] ?? 0,
                'items_with_dimensions' => $stats['items_with_dimensions'] ?? 0,
                'products_updated' => $stats['products_updated'] ?? 0,
                'unmatched_skus' => $stats['unmatched_skus'] ?? null,
                'error_message' => $errorMessage,
            ]);
        } catch (Exception $e) {
            Log::warning('Could not write to product_dimension_sync_logs table: ' . $e->getMessage());
        }
    }
}
