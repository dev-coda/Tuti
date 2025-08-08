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
            'Authorization' => "Bearer {$token}"
        ])->send('POST', env('MICROSOFT_RESOURCE_URL', 'https://uattrx.sandbox.operations.dynamics.com/') . '/soap/services/DIITDWSSalesForceGroup', [
            'body' => $body
        ]);
        info($response);
        $data = $response->body();

        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        // libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if (!$xml) {
            throw new Exception('No se pudo cargar el XML limpio.');
        }

        $products = $xml->sBody->getPriceAndDiscountResponse->result->agetPriceAndDiscountResult->aListPriceDisc;

        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                // Filter by GroupId == TATNAC
                $groupId = (string) ($product->aGroupId ?? '');
                if (strtoupper($groupId) !== 'TATNAC') {
                    continue;
                }

                $itemId = (string) $product->aItemId;
                $amount = (string) $product->aAmount;

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
