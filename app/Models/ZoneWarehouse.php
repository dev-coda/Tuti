<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
