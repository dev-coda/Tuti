<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Exception;

class UpdateProductPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = Setting::where('key', 'microsoft_token')->first();

        //check if updated_at is grander than 30 minutes
        if ($token->updated_at->diffInMinutes(now()) > 2) {
            //call command app:get-token
            Artisan::call('app:get-token');
            $token = Setting::where('key', 'microsoft_token')->first();
        }

        $token = $token->value;
        info($token);

        $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">
            <soapenv:Header>
                <dat:CallContext>
                    <!--Optional:-->
                    <dat:Company>trx</dat:Company>
                </dat:CallContext>
            </soapenv:Header>
            <soapenv:Body>
                <tem:getPriceAndDiscount>
                </tem:getPriceAndDiscount>
            </soapenv:Body>
        </soapenv:Envelope>';

        info($body);

        $response = Http::withHeaders([

            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getPriceAndDiscount',
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'text/xml; charset=utf-8',
            'Accept' => 'text/xml, application/xml'
        ])->send('POST', config('microsoft.resource') . '/soap/services/DIITDWSSalesForceGroup', [
            'body' => $body
        ]);

        // Check HTTP status and basic headers first
        $status = $response->status();
        $headers = $response->headers();
        $contentType = $headers['Content-Type'][0] ?? '';
        if (!$response->successful()) {
            $snippet = mb_substr($response->body(), 0, 500);
            info("SOAP request failed: status {$status}, content-type {$contentType}, body: {$snippet}");
            throw new Exception("Error en la solicitud SOAP: {$status}");
        }

        $data = $response->body();

        // Store last response for debugging purposes (overwritten each run)
        try {
            Storage::disk('local')->put('last_soap_response.xml', $data);
        } catch (Exception $e) {
            // non-fatal
        }

        // Enable internal error collection for better diagnostics
        libxml_use_internal_errors(true);

        // Sanitize XML: normalize encoding, fix stray ampersands, remove invalid control chars
        $detectedEncoding = mb_detect_encoding($data, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        $normalized = @mb_convert_encoding($data, 'UTF-8', $detectedEncoding);
        if ($normalized === false) {
            $normalized = @iconv($detectedEncoding, 'UTF-8//IGNORE', $data) ?: $data;
        }
        // Replace stray ampersands not part of an entity
        $normalized = preg_replace('/&(?!#?[a-zA-Z0-9]+;)/', '&amp;', $normalized);
        // Strip invalid XML 1.0 characters
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $normalized);

        // Try parsing the raw XML (keep namespaces)
        $xml = simplexml_load_string($normalized, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        if (!$xml) {
            // Fallback: strip namespace prefixes and try again
            $stripped = preg_replace('/<(\/)?([A-Za-z0-9_\-]+):/', '<$1', $normalized);
            $xml = simplexml_load_string($stripped, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE);
            if (!$xml) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMessages = [];
                foreach ($errors as $err) {
                    $errorMessages[] = trim($err->message);
                }
                info('XML parse errors: ' . implode(' | ', $errorMessages));
                throw new Exception('No se pudo cargar la respuesta XML.');
            }
        }

        // Extract products using namespace-agnostic XPath
        $products = $xml->xpath('//*[local-name()="Body"]//*[local-name()="getPriceAndDiscountResponse"]//*[local-name()="result"]//*[local-name()="getPriceAndDiscountResult"]//*[local-name()="ListPriceDisc"]');

        if (empty($products)) {
            info('No se encontraron productos en la respuesta SOAP.');
            info("Proceso completado.\n");
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                // Filter by GroupId == TATNAC
                $groupIdNode = $product->xpath('./*[local-name()="GroupId"]');
                $itemIdNode = $product->xpath('./*[local-name()="ItemId"]');
                $amountNode = $product->xpath('./*[local-name()="Amount"]');

                $groupId = (string) (($groupIdNode[0] ?? null));
                $itemId = (string) (($itemIdNode[0] ?? null));
                $amount = (string) (($amountNode[0] ?? null));

                if (strtoupper($groupId) !== 'TATNAC') {
                    continue;
                }

                if (empty($itemId)) {
                    continue;
                }

                $amount = str_replace('.', '', $amount);
                $amount = str_replace(',', '.', $amount);
                $cleanAmount = number_format((float) $amount, 2, '.', '');

                $existingProduct = Product::query()
                    ->where('sku', $itemId)
                    ->first();

                if ($existingProduct) {
                    // If "calcular precio por empaque" is disabled, divide by package quantity
                    $effectivePrice = $cleanAmount;
                    if (!$existingProduct->calculate_package_price) {
                        $packageQty = (float) ($existingProduct->package_quantity ?? 1);
                        $packageQty = $packageQty > 0 ? $packageQty : 1;
                        $effectivePrice = number_format(((float) $cleanAmount) / $packageQty, 2, '.', '');
                    }

                    if ((float) $existingProduct->price !== (float) $effectivePrice) {
                        $existingProduct->update(['price' => $effectivePrice]);
                        info("Precio actualizado para {$itemId}: {$effectivePrice} (group {$groupId})");
                    }
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            info('Error al actualizar precios: ' . $e->getMessage());
        }

        info("Proceso completado.\n");
    }
}
