<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneRoute;
use App\Models\ZoneWarehouse;
use App\Repositories\UserRepository;

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
        ];

        foreach ($zoneCodes as $zoneCode) {
            try {
                $ruteros = UserRepository::getRuterosForZone($zoneCode);
            } catch (\Throwable $e) {
                $summary['zones_failed']++;
                \Log::error('Rutero zone sync: fetch failed for zone', [
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

        $zoneRows = Zone::query()->where('code', $code)->get();
        if ($zoneRows->isEmpty()) {
            $summary['ruteros_unmatched']++;
            return;
        }

        foreach ($zoneRows as $zoneRow) {
            $changes = [];
            foreach (['zone', 'route', 'day'] as $field) {
                $incoming = trim((string) ($rutero[$field] ?? ''));
                if ($incoming !== '' && $incoming !== trim((string) $zoneRow->{$field})) {
                    $changes[$field] = $incoming;
                }
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
