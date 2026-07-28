<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneRoute;
use App\Models\ZoneWarehouse;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RuteroZoneSyncService
{
    /**
     * Collect every zone code we should query against getRuteros.
     *
     * @return array<int, string>
     */
    public function discoverZoneCodes(?array $onlyZones = null): array
    {
        if ($onlyZones !== null && $onlyZones !== []) {
            return collect($onlyZones)
                ->map(fn ($zone) => $this->normalizeZoneCode($zone))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return User::query()
            ->whereRelation('roles', 'name', 'seller')
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->pluck('zone')
            ->merge(
                Zone::query()
                    ->whereNotNull('zone')
                    ->where('zone', '!=', '')
                    ->distinct()
                    ->pluck('zone')
            )
            ->merge(
                ZoneRoute::query()
                    ->distinct()
                    ->pluck('zone')
            )
            ->merge(
                ZoneWarehouse::query()
                    ->whereNotNull('zone_code')
                    ->where('zone_code', '!=', '')
                    ->distinct()
                    ->pluck('zone_code')
            )
            ->map(fn ($zone) => $this->normalizeZoneCode($zone))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Import zone/route catalog rows and optionally refresh client zone relationships.
     * Missing CustRuteroID rows are provisioned as Tuti users + zones when
     * Dynamics provides an identification number (NIT/CC).
     *
     * @param  array<int, string>|null  $onlyZones
     * @return array<string, int>
     */
    public function syncFromRuteros(?array $onlyZones = null, bool $updateClients = true, bool $dryRun = false): array
    {
        $zoneCodes = $this->discoverZoneCodes($onlyZones);
        $summary = [
            'zones_requested' => count($zoneCodes),
            'zones_processed' => 0,
            'zones_failed' => 0,
            'zones_empty' => 0,
            'catalog_routes_created' => 0,
            'catalog_routes_seen' => 0,
            'ruteros_seen' => 0,
            'ruteros_without_code' => 0,
            'ruteros_unmatched' => 0,
            'client_zone_rows_updated' => 0,
            'clients_created' => 0,
            'zones_created' => 0,
            'ruteros_skipped_rejected' => 0,
        ];

        foreach ($zoneCodes as $zoneCode) {
            try {
                $ruteros = UserRepository::getRuterosForZone($zoneCode);
            } catch (\Throwable $e) {
                $summary['zones_failed']++;
                Log::error('Rutero zone sync: fetch failed for zone', [
                    'zone' => $zoneCode,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($ruteros === null || $ruteros->isEmpty()) {
                $summary['zones_empty']++;
                continue;
            }

            $summary['zones_processed']++;

            foreach ($ruteros as $rutero) {
                $summary['ruteros_seen']++;

                $this->upsertCatalogRoute($rutero, $summary, $dryRun);

                if ($updateClients) {
                    $this->syncClientZoneRow($rutero, $summary, $dryRun);
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array<string, mixed>  $rutero
     */
    private function upsertCatalogRoute(array $rutero, array &$summary, bool $dryRun): void
    {
        $zone = $this->normalizeZoneCode($rutero['zone'] ?? '');
        $route = $this->normalizeRouteCode($rutero['route'] ?? '');

        if ($zone === '' || $route === '') {
            return;
        }

        $summary['catalog_routes_seen']++;

        if ($dryRun) {
            return;
        }

        $created = ZoneRoute::query()->firstOrCreate([
            'zone' => $zone,
            'route' => $route,
        ])->wasRecentlyCreated;

        if ($created) {
            $summary['catalog_routes_created']++;
        }
    }

    /**
     * Update an existing zone row matched by CustRuteroID, or provision a missing
     * client + sucursal when Dynamics returns a document (aIdentificationNum).
     *
     * Idempotent key: CustRuteroID → zones.code / sucursal_uid cust:{code}.
     * Same NIT/CC can own many sucursales (one User, many Zone rows).
     *
     * @param  array<string, int>  $summary
     * @param  array<string, mixed>  $rutero
     */
    private function syncClientZoneRow(array $rutero, array &$summary, bool $dryRun): void
    {
        $code = trim((string) ($rutero['code'] ?? ''));
        if ($code === '') {
            $summary['ruteros_without_code']++;

            return;
        }

        $sucursalUid = Zone::makeSucursalUid($code, null);
        $zoneRows = Zone::query()
            ->where(function ($query) use ($code, $sucursalUid) {
                $query->where('code', $code);
                if ($sucursalUid !== null && $sucursalUid !== '') {
                    $query->orWhere('sucursal_uid', $sucursalUid);
                }
            })
            ->get();

        if ($zoneRows->isEmpty()) {
            $this->provisionMissingFromRutero($rutero, $summary, $dryRun);

            return;
        }

        foreach ($zoneRows as $zoneRow) {
            $changes = [];
            foreach (['zone', 'route', 'day', 'address'] as $field) {
                $incoming = trim((string) ($rutero[$field] ?? ''));
                if ($incoming !== '' && $incoming !== trim((string) $zoneRow->{$field})) {
                    $changes[$field] = $incoming;
                }
            }

            // Backfill code when the row was matched only by sucursal_uid.
            if (trim((string) $zoneRow->code) === '' && $code !== '') {
                $changes['code'] = $code;
            }

            if ($changes === []) {
                continue;
            }

            if (! $dryRun) {
                $zoneRow->update($changes);
            }

            $summary['client_zone_rows_updated']++;
        }
    }

    /**
     * Create (or attach to) a Tuti client for a Dynamics rutero not yet present locally.
     *
     * @param  array<string, int>  $summary
     * @param  array<string, mixed>  $rutero
     */
    private function provisionMissingFromRutero(array $rutero, array &$summary, bool $dryRun): void
    {
        $document = preg_replace('/\D+/', '', (string) ($rutero['document'] ?? ''));
        if ($document === '') {
            $summary['ruteros_unmatched']++;

            return;
        }

        if ($dryRun) {
            $summary['zones_created']++;
            $existing = User::query()
                ->whereDoesntHave('roles')
                ->where('document', $document)
                ->exists();
            if (! $existing) {
                $summary['clients_created']++;
            }

            return;
        }

        $user = User::query()
            ->whereDoesntHave('roles')
            ->where('document', $document)
            ->first();

        if ($user && $user->isRejectedClient()) {
            $summary['ruteros_skipped_rejected']++;

            return;
        }

        if (! $user) {
            $staffWithDocument = User::query()
                ->whereHas('roles')
                ->where('document', $document)
                ->exists();

            if ($staffWithDocument) {
                $summary['ruteros_unmatched']++;
                Log::warning('Rutero zone sync: document belongs to a staff user; skipping client create', [
                    'document' => $document,
                    'cust_rutero_id' => $rutero['code'] ?? null,
                ]);

                return;
            }

            $user = $this->createClientFromRutero($document, $rutero);
            $summary['clients_created']++;
        }

        $beforeIds = $user->zones()->pluck('id')->all();
        UserRepository::applyRoutesToZones($user, [$rutero], pruneMissing: false);
        $this->applyLightProfileFromRutero($user, $rutero);

        $code = trim((string) ($rutero['code'] ?? ''));
        $sucursalUid = Zone::makeSucursalUid($code, null);
        $zone = $user->zones()->where('code', $code)->first()
            ?? $user->zones()->where('sucursal_uid', $sucursalUid)->first();

        if ($zone && ! in_array($zone->id, $beforeIds, true)) {
            $summary['zones_created']++;
        } elseif ($zone) {
            $summary['client_zone_rows_updated']++;
        } else {
            $summary['ruteros_unmatched']++;
            Log::warning('Rutero zone sync: provisioned client but zone row was not created', [
                'user_id' => $user->id,
                'document' => $document,
                'cust_rutero_id' => $code,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $rutero
     */
    private function createClientFromRutero(string $document, array $rutero): User
    {
        $name = trim((string) ($rutero['name'] ?? ''));
        if ($name === '' || $name === 'Sin Nombre') {
            $name = 'Cliente '.$document;
        }

        $email = UserRepository::normalizeDynamicsEmail($rutero['dynamics_contact_email'] ?? null);
        if ($email === null || User::query()->where('email', $email)->exists()) {
            $email = Str::lower(Str::random(12)).'@tuti.com';
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'document' => $document,
            'password' => Str::random(32),
            'status_id' => User::ACTIVE,
            'client_status' => User::CLIENT_STATUS_CLIENTE,
            'business_name' => $rutero['business_name'] ?? null,
            'phone' => $rutero['phone'] ?? null,
            'mobile_phone' => $rutero['mobile_phone'] ?? null,
            'whatsapp' => $rutero['whatsapp'] ?? null,
            'account_num' => $rutero['account_num'] ?? null,
        ]);

        Log::info('Rutero zone sync: created missing Dynamics client', [
            'user_id' => $user->id,
            'document' => $document,
            'cust_rutero_id' => $rutero['code'] ?? null,
        ]);

        return $user;
    }

    /**
     * Refresh a subset of profile fields from the zone-walk row without a second SOAP call.
     *
     * @param  array<string, mixed>  $rutero
     */
    private function applyLightProfileFromRutero(User $user, array $rutero): void
    {
        $payload = [];

        $name = trim((string) ($rutero['name'] ?? ''));
        if ($name !== '' && $name !== 'Sin Nombre' && $name !== (string) $user->name) {
            $payload['name'] = $name;
        }

        foreach (['phone', 'mobile_phone', 'whatsapp', 'business_name', 'account_num', 'city_code', 'county_id', 'price_group', 'tax_group', 'line_discount', 'customer_status'] as $field) {
            $value = $rutero[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ((string) $user->{$field} !== (string) $value) {
                $payload[$field] = $value;
            }
        }

        if (array_key_exists('balance', $rutero) && $rutero['balance'] !== null && $rutero['balance'] !== '') {
            $payload['balance'] = $rutero['balance'];
        }
        if (array_key_exists('quota_value', $rutero) && $rutero['quota_value'] !== null && $rutero['quota_value'] !== '') {
            $payload['quota_value'] = $rutero['quota_value'];
        }
        if (array_key_exists('is_locked', $rutero)) {
            $payload['is_locked'] = (bool) $rutero['is_locked'];
        }

        $email = UserRepository::normalizeDynamicsEmail($rutero['dynamics_contact_email'] ?? null);
        if ($email !== null && ! User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $payload['email'] = $email;
        }

        if ($payload !== []) {
            $user->update($payload);
        }
    }

    private function normalizeZoneCode(mixed $zone): string
    {
        $normalized = strtoupper(trim((string) $zone));

        return $normalized === '' ? '' : substr($normalized, 0, 3);
    }

    private function normalizeRouteCode(mixed $route): string
    {
        $normalized = strtoupper(trim((string) $route));

        return $normalized === '' ? '' : substr($normalized, 0, 4);
    }
}
