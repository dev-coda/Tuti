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

    /**
     * Get rutero data from external service
     * If zone is provided and doesn't match, retries without zone parameter
     * 
     * @param string $document
     * @param string|null $zone Optional zone code. If provided and doesn't match, will retry without it
     * @return array|null Returns rutero data with routes and name, or null if not found
     */
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
        $originalZone = $zone;
        $zone = $zone ?? '';

        // Try with zone first if provided
        $result = self::fetchRuteroData($document, $zone, $token);
        
        // If zone was provided and result is null, retry without zone
        // This handles cases where zone no longer matches in external service
        if ($originalZone !== null && $result === null) {
            \Log::info('Rutero not found with zone, retrying without zone', [
                'document' => $document,
                'zone' => $originalZone,
            ]);
            $result = self::fetchRuteroData($document, '', $token);
        }

        return $result;
    }

    /**
     * Internal method to fetch rutero data from SOAP service
     * 
     * @param string $document
     * @param string $zone Zone code (empty string if not filtering by zone)
     * @param string $token Microsoft token
     * @return array|null
     */
    private static function fetchRuteroData($document, $zone, $token)
    {
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
        $xml = simplexml_load_string($xmlString);

        //convert $data into an object
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        try {
            info('try');
            $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;

            $json = json_encode($addresses);
            $array = json_decode($json, TRUE);

            $aListRuteros = $array['aListRuteros'];
            info('aListRuteros');
            info($aListRuteros);
        } catch (\Throwable $th) {
            info('catch' . $th->getMessage());
            return null;
        }

        if (!array_key_exists('aDetail', $aListRuteros)) {
            return null;
        }

        $items = [];
        $name = '';
        info('AlistRuteros');
        info(count($aListRuteros));

        foreach ($aListRuteros as $key => $rutero) {
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
    }

    /**
     * Sync rutero data for a user and update their zones
     * This ensures we have current rutero data before processing orders
     * 
     * @param \App\Models\User $user
     * @return bool True if sync was successful, false otherwise
     */
    public static function syncUserRuteroData($user)
    {
        if (!$user || !$user->document) {
            return false;
        }

        try {
            $data = self::getCustomRuteroId($user->document);
            
            if ($data && isset($data['routes'])) {
                $existingZones = $user->zones()->get()->keyBy('id');
                $newRoutes = $data['routes'];

                foreach ($newRoutes as $index => $route) {
                    $zoneToUpdate = $existingZones->values()->get($index) ?? null;

                    if ($zoneToUpdate) {
                        // Update existing zone with fresh data
                        $zoneToUpdate->update([
                            'route' => $route['route'],
                            'zone' => $route['zone'],
                            'day' => $route['day'],
                            'address' => $route['address'],
                            'code' => $route['code'],
                        ]);
                    } else {
                        // Create new zone with fresh data
                        $user->zones()->create([
                            'route' => $route['route'],
                            'zone' => $route['zone'],
                            'day' => $route['day'],
                            'address' => $route['address'],
                            'code' => $route['code'],
                        ]);
                    }
                }

                // Update user name if available
                if (isset($data['name']) && $data['name'] !== 'Sin Nombre') {
                    $user->name = $data['name'];
                    $user->save();
                }

                $user->refresh();
                \Log::info('Rutero data synced successfully', [
                    'user_id' => $user->id,
                    'document' => $user->document,
                    'zones_count' => $user->zones()->count(),
                ]);

                return true;
            } else {
                \Log::warning('Rutero data sync returned no routes', [
                    'user_id' => $user->id,
                    'document' => $user->document,
                ]);
                return false;
            }
        } catch (\Throwable $th) {
            \Log::error('Failed to sync rutero data', [
                'user_id' => $user->id,
                'document' => $user->document,
                'error' => $th->getMessage(),
            ]);
            return false;
        }
    }
}
