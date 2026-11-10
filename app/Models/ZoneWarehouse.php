<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ZoneWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_code',
        'bodega_code',
    ];

    /**
     * Get bodega code for a zone code
     * Checks database first, then config file
     * Handles case-insensitive matching and trimming
     * 
     * @param string|null $zoneCode
     * @return string|null
     */
    public static function getBodegaForZone(?string $zoneCode): ?string
    {
        if (!$zoneCode) {
            return null;
        }

        // Normalize zone code
        $originalZoneCode = $zoneCode;
        $zoneCode = trim((string) $zoneCode);
        if (empty($zoneCode)) {
            \Log::debug('ZoneWarehouse::getBodegaForZone - Empty zone code after trim', [
                'original' => $originalZoneCode,
            ]);
            return null;
        }

        // Try DB exact match
        $bodega = self::where('zone_code', $zoneCode)->value('bodega_code');
        if ($bodega) {
            \Log::debug('ZoneWarehouse::getBodegaForZone - Found in DB (exact match)', [
                'zone_code' => $zoneCode,
                'bodega_code' => $bodega,
            ]);
            return $bodega;
        }

        // Try DB case-insensitive match
        $bodega = self::whereRaw('LOWER(TRIM(zone_code)) = ?', [mb_strtolower($zoneCode)])->value('bodega_code');
        if ($bodega) {
            \Log::debug('ZoneWarehouse::getBodegaForZone - Found in DB (case-insensitive)', [
                'zone_code' => $zoneCode,
                'bodega_code' => $bodega,
            ]);
            return $bodega;
        }

        // Try config file exact match
        $mappings = (array) config('zone_warehouses.mappings', []);
        if (isset($mappings[$zoneCode])) {
            $mapVal = $mappings[$zoneCode];
            $result = is_array($mapVal) ? ($mapVal[0] ?? null) : $mapVal;
            \Log::debug('ZoneWarehouse::getBodegaForZone - Found in config (exact match)', [
                'zone_code' => $zoneCode,
                'bodega_code' => $result,
            ]);
            return $result;
        }

        // Try config file case-insensitive match
        $normalizedZoneCode = mb_strtolower($zoneCode);
        foreach ($mappings as $configZone => $configBodega) {
            if (mb_strtolower(trim((string) $configZone)) === $normalizedZoneCode) {
                $result = is_array($configBodega) ? ($configBodega[0] ?? null) : $configBodega;
                \Log::debug('ZoneWarehouse::getBodegaForZone - Found in config (case-insensitive)', [
                    'zone_code' => $zoneCode,
                    'config_zone' => $configZone,
                    'bodega_code' => $result,
                ]);
                return $result;
            }
        }

        \Log::warning('ZoneWarehouse::getBodegaForZone - No mapping found', [
            'zone_code' => $zoneCode,
            'db_mappings_count' => self::count(),
            'config_mappings_count' => count($mappings),
            'config_mappings_keys' => array_keys($mappings),
        ]);

        return null;
    }

    /**
     * Warehouse that serves a catalog city (Cúcuta → CU00, Medellín → MDTAT, …).
     */
    public static function bodegaForCity(?City $city): ?string
    {
        return self::bodegaForCityName($city?->name);
    }

    public static function bodegaForCityName(?string $cityName): ?string
    {
        $key = self::cityKeyForName($cityName);
        if ($key === null) {
            return null;
        }

        $bodega = config('zone_warehouses.cities.'.$key);

        return is_string($bodega) && $bodega !== '' ? $bodega : null;
    }

    /**
     * Operational city key from config/zone_warehouses.php (bogota, medellin, …).
     * Same normalization as bodegaForCityName. Names outside that list return null.
     */
    public static function cityKeyForName(?string $cityName): ?string
    {
        $key = Str::lower(Str::ascii(trim((string) $cityName)));
        if ($key === '') {
            return null;
        }

        $cities = (array) config('zone_warehouses.cities', []);

        return array_key_exists($key, $cities) ? $key : null;
    }

    /**
     * Operational city that owns a warehouse code (BG05 → bogota, BQ08 → barranquilla).
     * Matches the city's configured bodega, or the same letter prefix when it is unique.
     */
    public static function cityKeyForBodega(?string $bodega): ?string
    {
        $bodega = strtoupper(trim((string) $bodega));
        if ($bodega === '') {
            return null;
        }

        $cities = (array) config('zone_warehouses.cities', []);
        foreach ($cities as $key => $code) {
            if (strtoupper(trim((string) $code)) === $bodega) {
                return (string) $key;
            }
        }

        $family = self::bodegaFamily($bodega);
        $matches = [];
        foreach ($cities as $key => $code) {
            if (self::bodegaFamily((string) $code) === $family) {
                $matches[] = (string) $key;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * Display names for the operational cities, preferring the cities table.
     *
     * @return array<string, string> city key => label
     */
    public static function operationalCityLabels(): array
    {
        $labels = [];
        foreach (City::query()->orderByDesc('is_preferred')->orderBy('id')->get(['name']) as $city) {
            $key = self::cityKeyForName($city->name);
            if ($key !== null && ! isset($labels[$key])) {
                $labels[$key] = $city->name;
            }
        }

        return $labels;
    }

    private static function bodegaFamily(string $bodega): string
    {
        $bodega = strtoupper(trim($bodega));
        $stripped = preg_replace('/\d+$/', '', $bodega) ?? '';

        return $stripped !== '' ? $stripped : $bodega;
    }
}
