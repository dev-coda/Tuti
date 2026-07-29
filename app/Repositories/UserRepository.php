<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\User;
use App\Services\MicrosoftTokenService;
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

    /**
     * Normalize and validate an email string from Dynamics.
     */
    public static function normalizeDynamicsEmail(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

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
        if (is_array($incoming)) {
            return false;
        }

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

        $c = $current === null || is_array($current) ? '' : trim((string) $current);
        $i = $incoming === null ? '' : trim((string) $incoming);
        if ($field === 'email') {
            return strtolower($c) === strtolower($i);
        }

        return $c === $i;
    }

    /**
     * Coerce Dynamics SOAP scalars: empty XML nodes become [] via json_encode(SimpleXML),
     * which must not be written into string columns (phone, name, etc.).
     */
    private static function soapScalar($value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        if (is_bool($value) || is_object($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<string, mixed>  $aListDetailsRuteros
     * @param  array{aZona?: mixed, aRoute?: mixed, aDiaRecorrido?: mixed}  $data
     * @return array<string, mixed>|null Null when the detail row lacks usable identity fields.
     */
    private static function processData($aListDetailsRuteros, $data)
    {
        if (! is_array($aListDetailsRuteros)) {
            return null;
        }

        $aZona = $data['aZona'] ?? null;
        $aRoute = $data['aRoute'] ?? null;
        $aDiaRecorrido = (string) ($data['aDiaRecorrido'] ?? '');

        $day = '0';
        if (preg_match('/^\d+/', $aDiaRecorrido, $matches)) {
            $day = $matches[0];
        }

        $aCustRuteroID = self::soapScalar($aListDetailsRuteros['aCustRuteroID'] ?? null);
        $aAddress = self::soapScalar($aListDetailsRuteros['aAddress'] ?? null);
        $aName = self::soapScalar($aListDetailsRuteros['aName'] ?? null);

        // Need at least an address or CustRuteroID to form a stable sucursal identity.
        if ($aCustRuteroID === null && $aAddress === null) {
            \Log::warning('Rutero SOAP detail row skipped: missing CustRuteroID and address', [
                'keys' => array_keys($aListDetailsRuteros),
            ]);

            return null;
        }

        if (config('microsoft.log_rutero_soap_payload')) {
            \Log::debug('Rutero SOAP detail row (full payload)', [
                'all_fields' => array_keys($aListDetailsRuteros),
                'sample_data' => $aListDetailsRuteros,
            ]);
        }

        $rawDocument = $aListDetailsRuteros['aIdentificationNum'] ?? null;
        $document = self::soapScalar($rawDocument);
        if ($document !== null) {
            $document = preg_replace('/\D+/', '', $document) ?: null;
        }

        return [
            'zone' => self::soapScalar($aZona),
            'route' => self::soapScalar($aRoute),
            'code' => $aCustRuteroID,
            'day' => $day,
            'address' => $aAddress,
            'name' => $aName ?? 'Sin Nombre',
            // NIT/CC — required to create missing Tuti clients during zone-walk sync.
            'document' => $document,
            // Additional customer data from getRuteros API
            'phone' => self::soapScalar($aListDetailsRuteros['aPhone'] ?? null),
            'mobile_phone' => self::soapScalar($aListDetailsRuteros['aPhoneMobile'] ?? null),
            'whatsapp' => self::soapScalar($aListDetailsRuteros['aWhatsapp'] ?? null),
            'business_name' => self::soapScalar($aListDetailsRuteros['aRazonSocial'] ?? null),
            'account_num' => self::soapScalar($aListDetailsRuteros['aAccountNum'] ?? null),
            'city_code' => self::soapScalar($aListDetailsRuteros['aCity'] ?? null),
            'county_id' => self::soapScalar($aListDetailsRuteros['aCountyId'] ?? null),
            'customer_type' => self::soapScalar($aListDetailsRuteros['aTypeCustomer'] ?? null),
            'price_group' => self::soapScalar($aListDetailsRuteros['aPriceGroup'] ?? null),
            'tax_group' => self::soapScalar($aListDetailsRuteros['aTaxGroup'] ?? null),
            'line_discount' => self::soapScalar($aListDetailsRuteros['aLineDisc'] ?? null),
            'balance' => self::soapScalar($aListDetailsRuteros['aBalance'] ?? null) ?? 0,
            'quota_value' => self::soapScalar($aListDetailsRuteros['aQuotaValue'] ?? null) ?? 0,
            'customer_status' => self::soapScalar($aListDetailsRuteros['aCustStatus'] ?? null),
            'is_locked' => (self::soapScalar($aListDetailsRuteros['aLocked'] ?? null) ?? 'No') === 'Yes',
            'order_sequence' => self::soapScalar($aListDetailsRuteros['aOrden'] ?? null),
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
        try {
            return self::getCustomRuteroIdOrFail($document, $zone);
        } catch (\RuntimeException $e) {
            // Callers (registration, seller setclient, etc.) historically treated Dynamics
            // outages as "not found". Keep that UX; syncUserRuteroData uses OrFail for
            // query_failed messaging.
            \Log::error('getCustomRuteroId Dynamics query failed', [
                'document' => $document,
                'zone' => $zone,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Same as getCustomRuteroId but propagates Dynamics transport/parse failures.
     *
     * @return array{routes: mixed, name: string}|null
     *
     * @throws \RuntimeException When Dynamics HTTP/XML transport fails.
     */
    private static function getCustomRuteroIdOrFail($document, $zone = null)
    {
        $token = self::freshMicrosoftToken();
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
        // Let RuntimeException propagate so RuteroZoneSyncService can count zones_failed
        // (returning null would be miscounted as an empty zone).
        $result = self::fetchRuteroData('', $zone, self::freshMicrosoftToken());

        return $result ? collect($result['routes']) : null;
    }

    /**
     * Current Microsoft token value, refreshed when missing or older than 25 minutes.
     */
    private static function freshMicrosoftToken(): string
    {
        $token = Setting::where('key', 'microsoft_token')->first();

        if ($token && filled($token->value) && $token->updated_at->diffInMinutes(now()) <= 25) {
            return $token->value;
        }

        return MicrosoftTokenService::refresh();
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

        $resourceUrl = config('microsoft.resource');

        if (empty($resourceUrl)) {
            \Log::error('CRITICAL: Microsoft resource URL is not configured in UserRepository::fetchRuteroData');
            throw new \RuntimeException('Microsoft resource URL is not configured.');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
            'Authorization' => "Bearer {$token}"
        ])->send('POST', $resourceUrl . '/soap/services/DIITDWSSalesForceGroup', [
            'body' => $body
        ]);

        if (! $response->successful()) {
            \Log::error('getRuteros HTTP failure', [
                'document' => $document,
                'zone' => $zone,
                'status' => $response->status(),
                'body_preview' => substr(trim((string) $response->body()), 0, 300),
            ]);

            throw new \RuntimeException(
                'Dynamics getRuteros respondió HTTP '.$response->status().'.'
            );
        }

        $data = $response->body();

        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            \Log::warning('getRuteros returned non-XML body', [
                'document' => $document,
                'zone' => $zone,
                'body_preview' => substr(trim((string) $data), 0, 300),
            ]);

            throw new \RuntimeException('Dynamics getRuteros devolvió una respuesta no parseable.');
        }

        try {
            $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;

            $json = json_encode($addresses);
            $array = json_decode($json, true);

            if (! is_array($array)) {
                \Log::warning('getRuteros result could not be decoded to array', [
                    'document' => $document,
                    'zone' => $zone,
                ]);

                return null;
            }

            $aListRuteros = $array['aListRuteros'] ?? null;
        } catch (\Throwable $th) {
            \Log::warning('getRuteros XML parse failed', [
                'document' => $document,
                'zone' => $zone,
                'error' => $th->getMessage(),
            ]);

            return null;
        }

        // Empty / nil ListRuteros (i:nil="true" → null) means no rutero for this filter.
        if ($aListRuteros === null || $aListRuteros === '' || $aListRuteros === []) {
            return null;
        }

        if (! is_array($aListRuteros)) {
            \Log::warning('getRuteros aListRuteros has unexpected type', [
                'document' => $document,
                'zone' => $zone,
                'type' => gettype($aListRuteros),
            ]);

            return null;
        }

        $items = [];

        // Detect if we have multiple routes (indexed array) or single route (associative array)
        $hasMultipleRoutes = array_key_exists(0, $aListRuteros);

        if ($hasMultipleRoutes) {
            foreach ($aListRuteros as $rutero) {
                if (! is_array($rutero)) {
                    continue;
                }
                self::appendProcessedRouteDetails($items, $rutero);
            }
        } else {
            self::appendProcessedRouteDetails($items, $aListRuteros);
        }

        $items = collect($items)->filter()->values();

        if ($items->count()) {
            return [
                'routes' => $items,
                'name' => $items->first()['name'] ?? 'Sin Nombre',
            ];
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $rutero
     */
    private static function appendProcessedRouteDetails(array &$items, array $rutero): void
    {
        if (! array_key_exists('aDetail', $rutero) || ! is_array($rutero['aDetail'] ?? null)) {
            return;
        }

        $aListDetailsRuteros = $rutero['aDetail']['aListDetailsRuteros'] ?? null;
        if ($aListDetailsRuteros === null || $aListDetailsRuteros === [] || ! is_array($aListDetailsRuteros)) {
            return;
        }

        $routeMeta = [
            'aDiaRecorrido' => $rutero['aDiaRecorrido'] ?? null,
            'aRoute' => $rutero['aRoute'] ?? null,
            'aZona' => $rutero['aZona'] ?? null,
        ];

        if (array_key_exists(0, $aListDetailsRuteros)) {
            foreach ($aListDetailsRuteros as $detail) {
                $processed = self::processData(is_array($detail) ? $detail : [], $routeMeta);
                if ($processed !== null) {
                    $items[] = $processed;
                }
            }

            return;
        }

        $processed = self::processData($aListDetailsRuteros, $routeMeta);
        if ($processed !== null) {
            $items[] = $processed;
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
     * Profile fields the periodic (bulk) sync is allowed to touch: contact data only.
     */
    public const CONTACT_SYNC_FIELDS = ['phone', 'mobile_phone', 'whatsapp'];

    /**
     * Refresh only the user's contact data (email + phones) from Dynamics.
     * Used by the periodic bulk sync, which must never overwrite the rest of
     * the local profile (name, balances, groups) nor the zone rows.
     */
    public static function syncUserContactData($user): bool
    {
        return self::syncUserRuteroData($user, contactOnly: true)['synced'];
    }

    /**
     * Sync rutero data for a user and update their zones
     * This ensures we have current rutero data before processing orders
     *
     * @param  \App\Models\User  $user
     * @param  bool  $contactOnly  When true, only email and phone fields are updated
     *                             and zone rows are left untouched.
     * @return array{synced: bool, failure: null|string} failure is 'not_found' or 'query_failed'
     */
    public static function syncUserRuteroData($user, bool $contactOnly = false): array
    {
        if (!$user || !$user->document) {
            return ['synced' => false, 'failure' => 'not_found'];
        }

        try {
            // Use OrFail so HTTP/XML Dynamics failures map to failure=query_failed
            // (getCustomRuteroId swallows those for UX on registration/setclient).
            $data = self::getCustomRuteroIdOrFail($user->document);

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
                // Contact-only mode must not restructure the user's sucursales.
                $syncedZones = $contactOnly ? [] : self::applyRoutesToZones($user, $newRoutes);

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
                    if (!$contactOnly && isset($firstRoute['name']) && $firstRoute['name'] !== '' && $firstRoute['name'] !== 'Sin Nombre') {
                        $profilePayload['name'] = $firstRoute['name'];
                    }

                    $fieldsToUpdate = $contactOnly ? self::CONTACT_SYNC_FIELDS : [
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
                        // Skip empty / non-scalar Dynamics payloads (empty XML → []).
                        if ($value === null || $value === '' || is_array($value)) {
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

                // Clear corrupt phone placeholders written from empty SOAP arrays (literal "[]").
                foreach (['phone', 'mobile_phone', 'whatsapp'] as $contactField) {
                    $current = $user->getAttribute($contactField);
                    if (is_string($current) && trim($current) === '[]' && ! array_key_exists($contactField, $toApply)) {
                        $toApply[$contactField] = null;
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

                return ['synced' => true, 'failure' => null];
            }

            \Log::warning('Rutero data sync returned no routes', [
                'user_id' => $user->id,
                'document' => $user->document,
            ]);

            return ['synced' => false, 'failure' => 'not_found'];
        } catch (\Throwable $th) {
            \Log::error('Failed to sync rutero data', [
                'user_id' => $user->id,
                'document' => $user->document,
                'error' => $th->getMessage(),
            ]);

            return ['synced' => false, 'failure' => 'query_failed'];
        }
    }
}
