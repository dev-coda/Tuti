<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UserRepository
{
    /**
     * Pick a valid email from Dynamics getRuteros detail payload (field names vary by version).
     */
    private static function extractDynamicsEmailFromDetail(array $detail): ?string
    {
        $keys = [
            'aEmail',
            'aElectronicMail',
            'aCustEmail',
            'aPrimaryEmail',
            'aContactEmail',
            'aCommercialEmail',
            'aInvoiceEmail',
        ];

        foreach ($keys as $key) {
            if (empty($detail[$key]) || !is_string($detail[$key])) {
                continue;
            }
            $normalized = self::normalizeDynamicsEmail($detail[$key]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private static function normalizeDynamicsEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw));
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * True if the stored user value already matches the incoming rutero value (avoids noisy updates / updated_at bumps).
     */
    private static function ruteroScalarUnchanged(User $user, string $field, $incoming): bool
    {
        $current = $user->getAttribute($field);

        if ($field === 'is_locked') {
            return (bool) $current === (bool) $incoming;
        }

        if (in_array($field, ['balance', 'quota_value', 'line_discount'], true)) {
            return abs((float) $current - (float) $incoming) < 0.00001;
        }

        if ($field === 'order_sequence') {
            return (int) $current === (int) $incoming;
        }

        $c = $current === null ? '' : trim((string) $current);
        $i = $incoming === null ? '' : trim((string) $incoming);
        if ($field === 'email') {
            return strtolower($c) === strtolower($i);
        }

        return $c === $i;
    }

    private static function processData($aListDetailsRuteros, $data)
    {



        $aZona = $data['aZona'];
        $aRoute = $data['aRoute'];
        $aDiaRecorrido = (string) ($data['aDiaRecorrido'] ?? '');

        $day = '0';
        if (preg_match('/^\d+/', $aDiaRecorrido, $matches)) {
            $day = $matches[0];
        }


        $aCustRuteroID = $aListDetailsRuteros['aCustRuteroID'];

        $aAddress = $aListDetailsRuteros['aAddress'];
        $aName = $aListDetailsRuteros['aName'];



        if (config('microsoft.log_rutero_soap_payload')) {
            \Log::debug('Rutero SOAP detail row (full payload)', [
                'all_fields' => array_keys($aListDetailsRuteros),
                'sample_data' => $aListDetailsRuteros,
            ]);
        }

        return [
            'zone' => $aZona,
            'route' => $aRoute,
            'code' => $aCustRuteroID,
            'day' => $day,
            'address' => $aAddress,
            'name' => $aName,
            // Additional customer data from getRuteros API
            'phone' => $aListDetailsRuteros['aPhone'] ?? null,
            'mobile_phone' => !empty($aListDetailsRuteros['aPhoneMobile']) ? $aListDetailsRuteros['aPhoneMobile'] : null,
            'whatsapp' => !empty($aListDetailsRuteros['aWhatsapp']) ? $aListDetailsRuteros['aWhatsapp'] : null,
            'business_name' => $aListDetailsRuteros['aRazonSocial'] ?? null,
            'account_num' => $aListDetailsRuteros['aAccountNum'] ?? null,
            'city_code' => $aListDetailsRuteros['aCity'] ?? null,
            'county_id' => $aListDetailsRuteros['aCountyId'] ?? null,
            'customer_type' => $aListDetailsRuteros['aTypeCustomer'] ?? null,
            'price_group' => $aListDetailsRuteros['aPriceGroup'] ?? null,
            'tax_group' => $aListDetailsRuteros['aTaxGroup'] ?? null,
            'line_discount' => $aListDetailsRuteros['aLineDisc'] ?? null,
            'balance' => $aListDetailsRuteros['aBalance'] ?? 0,
            'quota_value' => $aListDetailsRuteros['aQuotaValue'] ?? 0,
            'customer_status' => $aListDetailsRuteros['aCustStatus'] ?? null,
            'is_locked' => ($aListDetailsRuteros['aLocked'] ?? 'No') === 'Yes',
            'order_sequence' => $aListDetailsRuteros['aOrden'] ?? null,
            'dynamics_contact_email' => self::extractDynamicsEmailFromDetail($aListDetailsRuteros),
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
     * Fetch every rutero registered in a zone (getRuteros with no document filter).
     * Unlike getCustomRuteroId, this never retries without the zone: an empty result
     * for a zone must stay empty instead of pulling the entire customer base.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>|null Route rows (zone, route, day, code, ...) or null when the zone returned nothing.
     */
    public static function getRuterosForZone(string $zone): ?\Illuminate\Support\Collection
    {
        $token = Setting::where('key', 'microsoft_token')->first();

        if ($token->updated_at->diffInMinutes(now()) > 25) {
            Artisan::call('app:get-token');
            $token = Setting::where('key', 'microsoft_token')->first();
        }

        $result = self::fetchRuteroData('', $zone, $token->value);

        return $result ? collect($result['routes']) : null;
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

        $resourceUrl = config('microsoft.resource');

        if (empty($resourceUrl)) {
            \Log::error('CRITICAL: Microsoft resource URL is not configured in UserRepository::fetchRuteroData');
            return null;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
            'Authorization' => "Bearer {$token}"
        ])->send('POST', $resourceUrl . '/soap/services/DIITDWSSalesForceGroup', [
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

        $items = [];
        $name = '';
        
        // Detect if we have multiple routes (indexed array) or single route (associative array)
        $hasMultipleRoutes = array_key_exists(0, $aListRuteros);
        
        info('AlistRuteros structure', [
            'count' => count($aListRuteros),
            'has_multiple_routes' => $hasMultipleRoutes
        ]);

        if ($hasMultipleRoutes) {
            // Multiple routes: iterate through each route
            foreach ($aListRuteros as $rutero) {
                if (!isset($rutero['aDetail'])) {
                    continue;
                }

                $aListDetailsRuteros = $rutero['aDetail']['aListDetailsRuteros'];
                
                $data = [
                    'aDiaRecorrido' => $rutero['aDiaRecorrido'] ?? null,
                    'aRoute' => $rutero['aRoute'] ?? null,
                    'aZona' => $rutero['aZona'] ?? null,
                ];

                // Check if aListDetailsRuteros is an array of details or a single detail
                if (array_key_exists(0, $aListDetailsRuteros)) {
                    foreach ($aListDetailsRuteros as $detail) {
                        $items[] = self::processData($detail, $data);
                    }
                } else {
                    $items[] = self::processData($aListDetailsRuteros, $data);
                }
            }
        } else {
            // Single route: use old logic
            if (!array_key_exists('aDetail', $aListRuteros)) {
                return null;
            }

            $aListDetailsRuteros = $aListRuteros['aDetail']['aListDetailsRuteros'];

            $data = [
                'aDiaRecorrido' => $aListRuteros['aDiaRecorrido'] ?? null,
                'aRoute' => $aListRuteros['aRoute'] ?? null,
                'aZona' => $aListRuteros['aZona'] ?? null,
            ];

            // Check if aListDetailsRuteros is an array of details or a single detail
            if (array_key_exists(0, $aListDetailsRuteros)) {
                foreach ($aListDetailsRuteros as $detail) {
                    $items[] = self::processData($detail, $data);
                }
            } else {
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
     * Reconcile a user's zone rows against fresh rutero routes by stable sucursal identity.
     *
     * Each route is matched to an existing row by `sucursal_uid` (CustRuteroID, else address),
     * with a code-equality fallback so legacy address-keyed rows that later gain a CustRuteroID
     * still match. A matched row only has its mutable logistics attributes refreshed — its frozen
     * `sucursal_uid` is never changed, so a row (and any order pointing at it) can never be
     * silently repurposed to a different sucursal. Routes with no match create a new row; existing
     * rows absent from the payload are deleted only when not referenced by an order.
     *
     * @param  \App\Models\User  $user
     * @param  iterable<int, array<string, mixed>>  $routes
     * @param  bool  $pruneMissing  Delete order-unreferenced rows absent from the payload.
     * @return array<int, array{id: int, code: ?string, zone: ?string, route: ?string}>
     */
    public static function applyRoutesToZones($user, $routes, bool $pruneMissing = true): array
    {
        return DB::transaction(function () use ($user, $routes, $pruneMissing) {
            $existingZones = $user->zones()->orderBy('id')->get();

            $matchedIds = [];
            $processedIds = [];
            $syncedZones = [];

            foreach ($routes as $route) {
                $code = isset($route['code']) ? trim((string) $route['code']) : '';
                $uid = \App\Models\Zone::makeSucursalUid($code !== '' ? $code : null, $route['address'] ?? null);
                // Address identity this row would have had before Dynamics returned a CustRuteroID.
                $addressUid = \App\Models\Zone::makeSucursalUid(null, $route['address'] ?? null);

                $candidates = $existingZones->whereNotIn('id', $matchedIds);

                // Prioritized match: exact frozen identity, then CustRuteroID equality (legacy rows
                // that gained a code), then the address identity (legacy address-keyed rows).
                $match = $candidates->first(fn ($zone) => $zone->sucursal_uid !== null && $zone->sucursal_uid === $uid)
                    ?? ($code !== '' ? $candidates->first(fn ($zone) => trim((string) $zone->code) === $code) : null)
                    ?? $candidates->first(fn ($zone) => $zone->sucursal_uid !== null && $zone->sucursal_uid === $addressUid);

                $attributes = [
                    'route' => $route['route'] ?? null,
                    'zone' => $route['zone'] ?? null,
                    'day' => $route['day'] ?? null,
                    'address' => $route['address'] ?? null,
                    'code' => $route['code'] ?? null,
                ];

                if ($match) {
                    $matchedIds[] = $match->id;
                    // Identity (sucursal_uid) is intentionally left untouched.
                    $match->update($attributes);
                    $zone = $match;
                } else {
                    $zone = $user->zones()->create($attributes);
                }

                $processedIds[] = $zone->id;
                $syncedZones[] = [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'zone' => $zone->zone,
                    'route' => $zone->route,
                ];
            }

            // Remove zones that disappeared from the rutero, but only when no order references them.
            if ($pruneMissing) {
                foreach ($existingZones->whereNotIn('id', $processedIds) as $stale) {
                    if (! \App\Models\Order::where('zone_id', $stale->id)->exists()) {
                        $stale->delete();
                    }
                }
            }

            return $syncedZones;
        });
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

            \Log::info('Rutero sync - data received', [
                'user_id' => $user->id,
                'document' => $user->document,
                'has_data' => !is_null($data),
                'has_routes' => isset($data['routes']),
                'routes_count' => isset($data['routes']) ? count($data['routes']) : 0,
                'routes_sample' => isset($data['routes']) && count($data['routes']) > 0 ? [
                    'first_route' => $data['routes'][0] ?? null,
                ] : null,
            ]);

            if ($data && isset($data['routes'])) {
                $newRoutes = $data['routes'];

                // Reconcile zone rows by stable sucursal identity (no index-based repurposing).
                $syncedZones = self::applyRoutesToZones($user, $newRoutes);

                $routeCount = is_countable($newRoutes) ? count($newRoutes) : 0;
                if ($routeCount > 1) {
                    $missingCustId = 0;
                    foreach ($newRoutes as $route) {
                        $c = $route['code'] ?? null;
                        if ($c === null || $c === '') {
                            $missingCustId++;
                        }
                    }
                    if ($missingCustId > 0) {
                        \Log::warning('Rutero sync: multiple sucursales but at least one route is missing CustRuteroID (zones.code)', [
                            'user_id' => $user->id,
                            'routes_count' => $routeCount,
                            'routes_missing_code' => $missingCustId,
                        ]);
                    }
                }

                $routes = $data['routes'] instanceof \Illuminate\Support\Collection
                    ? $data['routes']
                    : collect($data['routes']);

                $profilePayload = [];
                $firstRoute = $routes->first();

                if ($firstRoute) {
                    if (isset($firstRoute['name']) && $firstRoute['name'] !== '' && $firstRoute['name'] !== 'Sin Nombre') {
                        $profilePayload['name'] = $firstRoute['name'];
                    }

                    $fieldsToUpdate = [
                        'phone',
                        'mobile_phone',
                        'whatsapp',
                        'business_name',
                        'account_num',
                        'city_code',
                        'county_id',
                        'customer_type',
                        'price_group',
                        'tax_group',
                        'line_discount',
                        'balance',
                        'quota_value',
                        'customer_status',
                        'is_locked',
                        'order_sequence',
                    ];

                    foreach ($fieldsToUpdate as $field) {
                        if (!array_key_exists($field, $firstRoute)) {
                            continue;
                        }
                        $value = $firstRoute[$field];
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $profilePayload[$field] = $value;
                    }
                }

                $emailFromDynamics = null;
                foreach ($routes as $route) {
                    $candidate = $route['dynamics_contact_email'] ?? null;
                    if (!is_string($candidate) || trim($candidate) === '') {
                        continue;
                    }
                    $normalized = self::normalizeDynamicsEmail($candidate);
                    if ($normalized !== null) {
                        $emailFromDynamics = $normalized;
                        break;
                    }
                }

                if ($emailFromDynamics !== null) {
                    $emailTaken = User::where('email', $emailFromDynamics)
                        ->where('id', '!=', $user->id)
                        ->exists();
                    if (!$emailTaken) {
                        $profilePayload['email'] = $emailFromDynamics;
                    } else {
                        \Log::warning('Rutero sync skipped email: already used by another user', [
                            'user_id' => $user->id,
                            'document' => $user->document,
                            'email' => $emailFromDynamics,
                        ]);
                    }
                }

                $user->refresh();

                $toApply = [];
                foreach ($profilePayload as $field => $value) {
                    if (!self::ruteroScalarUnchanged($user, $field, $value)) {
                        $toApply[$field] = $value;
                    }
                }

                $syncedAt = now();

                if ($toApply === []) {
                    DB::table('users')->where('id', $user->id)->update([
                        'rutero_synced_at' => $syncedAt,
                    ]);
                } else {
                    $toApply['rutero_synced_at'] = $syncedAt;
                    $user->update($toApply);
                }

                $logPayload = $toApply;
                unset($logPayload['rutero_synced_at']);
                if (!empty($logPayload)) {
                    \Log::info('User data updated from rutero sync', [
                        'user_id' => $user->id,
                        'updated_fields' => array_keys($logPayload),
                        'sample_data' => $logPayload,
                    ]);
                }

                $user->refresh();
                $user->load('zones');

                \Log::info('Rutero data synced successfully', [
                    'user_id' => $user->id,
                    'document' => $user->document,
                    'zones_count' => $user->zones()->count(),
                    'synced_zones' => $syncedZones,
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
