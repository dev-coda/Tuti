<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\error;

class UserRepository
{

    private static function processData($aListDetailsRuteros, $data)
    {



        $aZona = $data['aZona'];
        $aRoute = $data['aRoute'];
        $aDiaRecorrido = $data['aDiaRecorrido'];

        preg_match('/^\d+/', $aDiaRecorrido, $matches);
        $day = $matches[0];


        $aCustRuteroID = $aListDetailsRuteros['aCustRuteroID'];

        $aAddress = $aListDetailsRuteros['aAddress'];
        $aName = $aListDetailsRuteros['aName'];



        return [
            'zone' => $aZona,
            'route' => $aRoute,
            'code' => $aCustRuteroID,
            'day' => $day,
            'address' => $aAddress,
            'name' => $aName
        ];
    }

    public static function getCustomRuteroId($document, $zone = null)
    {

        $token = Setting::where('key', 'microsoft_token')->first();

        //check if updated_at is grander than 30 minutes
        if ($token->updated_at->diffInMinutes(now()) > 25) {
            //call command app:get-token
            Artisan::call('app:get-token');
            $token = Setting::where('key', 'microsoft_token')->first();
        }

        $token = $token->value;
        $zone = $zone ?? '';

        //901703447
        $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">
            <soapenv:Header>
                <dat:CallContext>
                    <!--Optional:-->
                    <dat:Company>TRX</dat:Company>
                    
                    <!--Optional:-->
                </dat:CallContext>
            </soapenv:Header>
            <soapenv:Body>
                <tem:getRuteros>
                    <!--Optional:-->
                    <tem:_getRuteros>
                        <!--Optional:-->
                        <dyn:IdentificationNum>' . $document . '</dyn:IdentificationNum>
                        <!--Optional:-->
                        <dyn:ruteroId></dyn:ruteroId>
                        <!--Optional:-->
                        <dyn:zona>' . $zone . '</dyn:zona>
                    </tem:_getRuteros>
                </tem:getRuteros>
            </soapenv:Body>
            </soapenv:Envelope>';

        info($body);


        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
            'Authorization' => "Bearer {$token}"
        ])->send('POST', env('MICROSOFT_RESOURCE_URL', 'https://uattrx.sandbox.operations.dynamixs.com/').'/soap/services/DIITDWSSalesForceGroup', [
                    'body' => $body
                ]);
        info($response);
        $data = $response->body();

        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        // libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);


        //info('xml ' . $xml);



        //convert $data into an object
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);


        try {
            // $aListRuteros = $xml->sBody->getRuterosResponse->result->agetRuterosResult->aListRuteros;
            info('try');
            $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;

            $json = json_encode($addresses);

            $array = json_decode($json, TRUE);

            // info('array ' . $array);
            $aListRuteros = $array['aListRuteros'];
            info('aListRuteros');
            info($aListRuteros);
            // info('Alist ' . $aListRuteros);
        } catch (\Throwable $th) {
            info('catch' . $th->getMessage());
            return null;
        }

        if (!array_key_exists('aDetail', $aListRuteros)) {
            return null;
        }


        $items = [];
        $name = '';
        //info($aListRuteros->length());
        info('AlistRuteros');
        info(count($aListRuteros));

        foreach ($aListRuteros as $key => $rutero) {


            //    try {

            $aListDetailsRuteros = $aListRuteros['aDetail']['aListDetailsRuteros'];

            $data = [
                'aDiaRecorrido' => $aListRuteros['aDiaRecorrido'],
                'aRoute' => $aListRuteros['aRoute'],
                'aZona' => $aListRuteros['aZona'],
            ];

            //check if exist key 0
            if (array_key_exists(0, $aListDetailsRuteros)) {
                foreach ($aListDetailsRuteros as $i) {
                    $items[] = self::processData($i, $data);
                }
            } else if ('aDetail' === $key) {
                $items[] = self::processData($aListDetailsRuteros, $data);
            }




            //   } catch (\Throwable $th) {
            //     info('error '.$key);
            //     info($th->getMessage());
            //     info($rutero);

            //     continue;
            //   }
        }

        $items = collect($items);


        if ($items->count()) {

            $name = $items->first()['name'] ?? 'Sin Nombre';

            $data = [
                'routes' => $items,
                'name' => $name
            ];

            return $data;
        } else {
            return null;
        }




        // if(!empty($aListRuteros->aRoute)){
        //     $address = $aListRuteros->aDetail->aListDetailsRuteros->aAddress->__toString();
        //     $name = $aListRuteros->aDetail->aListDetailsRuteros->aName->__toString();
        //     $route = $aListRuteros->aRoute->__toString();
        //     $zone = $aListRuteros->aZona->__toString();
        //     $day = $aListRuteros->aDiaRecorrido->__toString();
        //     $aCustRuteroID = $aListRuteros->aDetail->aListDetailsRuteros-> aCustRuteroID->__toString();
        //     $day = explode('- ', $day)[0];

        //     return [
        //         'zone' => $zone,
        //         'route' => $route,
        //         'code' => $aCustRuteroID,
        //         'day' => $day,
        //         'address' => $address,
        //         'name' => $name
        //     ];

        // }else{
        //     return null;
        // }





    }
}
