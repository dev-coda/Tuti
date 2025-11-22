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
        $zoneCode = trim((string) $zoneCode);
        if (empty($zoneCode)) {
            return null;
        }

        // Try DB exact match
        $bodega = self::where('zone_code', $zoneCode)->value('bodega_code');
        if ($bodega) {
            return $bodega;
        }

        // Try DB case-insensitive match
        $bodega = self::whereRaw('LOWER(TRIM(zone_code)) = ?', [mb_strtolower($zoneCode)])->value('bodega_code');
        if ($bodega) {
            return $bodega;
        }

        // Try config file exact match
        $mappings = (array) config('zone_warehouses.mappings', []);
        if (isset($mappings[$zoneCode])) {
            $mapVal = $mappings[$zoneCode];
            return is_array($mapVal) ? ($mapVal[0] ?? null) : $mapVal;
        }

        // Try config file case-insensitive match
        $normalizedZoneCode = mb_strtolower($zoneCode);
        foreach ($mappings as $configZone => $configBodega) {
            if (mb_strtolower(trim((string) $configZone)) === $normalizedZoneCode) {
                return is_array($configBodega) ? ($configBodega[0] ?? null) : $configBodega;
            }
        }

        return null;
    }
}
