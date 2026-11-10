<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Setting extends Model
{
    use HasFactory;

    public const FV_BONIFICACIONES_CITIES_KEY = 'fv_bonificaciones_cities';

    protected $fillable = ['key', 'name', 'value', 'show'];

    public static function getByKey($key)
    {
        return Cache::remember("setting_{$key}", 1800, function () use ($key) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : null;
        });
    }

    /**
     * Get setting value with default fallback
     */
    public static function getByKeyWithDefault($key, $default = null)
    {
        $value = self::getByKey($key);
        return $value !== null ? $value : $default;
    }

    /**
     * Emergency "Forzar Fecha de Entrega". Global must be on, then the client's
     * city can opt out (pilot cities with express keep their programmed dates).
     */
    public static function isForceDeliveryDateEnabled(?int $cityId = null): bool
    {
        $v = self::getByKeyWithDefault('force_delivery_date_enabled', '0');
        if ($v !== '1' && $v !== 1 && $v !== true) {
            return false;
        }

        if ($cityId === null) {
            return true;
        }

        $city = City::query()->find($cityId);
        if (! $city) {
            return true;
        }

        return $city->allowsForceDeliveryDate();
    }

    public static function isForceDeliveryDateEnabledForOrder(?Order $order): bool
    {
        $cityId = $order?->user?->resolvedCityId();

        return self::isForceDeliveryDateEnabled($cityId !== null ? (int) $cityId : null);
    }

    /**
     * Envío 48h (Coordinadora quote + provider) — admin Setting express_48h_enabled.
     * Default off. COORDINADORA_EXPRESS_48H_DISABLED=true in .env forces off in production.
     */
    public static function isExpress48hEnabled(): bool
    {
        if (config('services.coordinadora.express_48h_disabled')) {
            return false;
        }

        $v = self::getByKeyWithDefault('express_48h_enabled', '0');

        return $v === '1' || $v === 1 || $v === true;
    }

    /**
     * Whether the free-shipping threshold for Entrega Especial is active.
     */
    public static function isExpressFreeShippingEnabled(): bool
    {
        $v = self::getByKeyWithDefault('express_free_shipping_enabled', '0');

        return $v === '1' || $v === 1 || $v === true;
    }

    /**
     * Minimum merchandise total (COP) for free express / 48h shipping.
     * Only applied when {@see isExpressFreeShippingEnabled()} is true and min > 0.
     */
    public static function expressFreeShippingMinimum(): float
    {
        $v = self::getByKeyWithDefault('express_free_shipping_min', '0');

        return max(0.0, (float) $v);
    }

    /**
     * Include bonificación quantities on the FV for this operational city.
     * On prorates the approved merchandise total across paid and gift units.
     * Off keeps the FV paid-lines-only. Cities missing from
     * config/zone_warehouses.php stay off.
     */
    public static function isFvBonificacionesEnabledForCityKey(?string $cityKey): bool
    {
        if ($cityKey === null || $cityKey === '') {
            return false;
        }

        $known = (array) config('zone_warehouses.cities', []);
        if (! array_key_exists($cityKey, $known)) {
            return false;
        }

        $value = self::fvBonificacionesCityFlags()[$cityKey] ?? '0';

        return $value === '1' || $value === 1 || $value === true;
    }

    public static function isFvBonificacionesEnabledForOrder(?Order $order): bool
    {
        if (! $order) {
            return false;
        }

        return self::isFvBonificacionesEnabledForCityKey(self::fvCityKeyForOrder($order));
    }

    /**
     * Client city first (users.city_id / city_code → cities.name). A catalog city
     * that is not one of the operational keys stays unknown (off), with no
     * warehouse fallback. Orders with no resolved city use the zone warehouse.
     */
    public static function fvCityKeyForOrder(?Order $order): ?string
    {
        if (! $order) {
            return null;
        }

        $order->loadMissing(['user', 'zone']);
        $city = $order->user?->resolvedCity();
        if ($city) {
            return ZoneWarehouse::cityKeyForName($city->name);
        }

        $snapshot = is_array($order->zone_snapshot) ? $order->zone_snapshot : [];
        $zoneNumber = trim((string) ($snapshot['zone'] ?? ''));
        if ($zoneNumber === '') {
            $zoneNumber = trim((string) ($order->zone?->zone ?? ''));
        }

        $bodega = $zoneNumber !== '' ? ZoneWarehouse::getBodegaForZone($zoneNumber) : null;

        return ZoneWarehouse::cityKeyForBodega($bodega);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fvBonificacionesCityFlags(): array
    {
        $raw = self::getByKeyWithDefault(self::FV_BONIFICACIONES_CITIES_KEY, '{}');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Checkbox rows for the operational cities in config/zone_warehouses.php.
     *
     * @return array<int, array{key: string, label: string, bodega: string, enabled: bool}>
     */
    public static function fvBonificacionesCityOptions(): array
    {
        $flags = self::fvBonificacionesCityFlags();
        $labels = ZoneWarehouse::operationalCityLabels();
        $options = [];

        foreach ((array) config('zone_warehouses.cities', []) as $key => $bodega) {
            $value = $flags[$key] ?? '0';
            $options[] = [
                'key' => (string) $key,
                'label' => $labels[$key] ?? \Illuminate\Support\Str::title((string) $key),
                'bodega' => (string) $bodega,
                'enabled' => $value === '1' || $value === 1 || $value === true,
            ];
        }

        return $options;
    }

    /**
     * Whether a merchandise total qualifies for free express shipping.
     */
    public static function qualifiesForExpressFreeShipping(float $merchandiseTotal): bool
    {
        if (! self::isExpressFreeShippingEnabled()) {
            return false;
        }

        $min = self::expressFreeShippingMinimum();

        return $min > 0 && $merchandiseTotal >= $min;
    }

    /**
     * Zero shipping_cost when the cart/order merchandise meets the free-shipping
     * threshold. Keeps the original quote for UI ("antes $X · gratis").
     *
     * @param  array{shipping_cost?: float|int|string, success?: bool}  $quote
     * @return array<string, mixed>
     */
    public static function applyExpressFreeShipping(array $quote, float $merchandiseTotal): array
    {
        $quoted = round((float) ($quote['shipping_cost'] ?? 0), 2);
        $min = self::expressFreeShippingMinimum();
        $free = self::qualifiesForExpressFreeShipping($merchandiseTotal);

        $quote['quoted_shipping_cost'] = $quoted;
        $quote['free_shipping_min'] = $min;
        $quote['free_shipping_applied'] = $free;
        $quote['merchandise_total'] = round($merchandiseTotal, 2);

        if ($free) {
            $quote['shipping_cost'] = 0.0;
        }

        return $quote;
    }

    /**
     * Check if vacation mode is currently active
     * Returns true if vacation mode is enabled AND current date is within the date range
     */
    public static function isVacationModeActive(): bool
    {
        $enabled = self::getByKey('vacation_mode_enabled');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            return false;
        }

        $fromDate = self::getByKey('vacation_mode_from_date');
        $toDate = self::getByKey('vacation_mode_date'); // Return date

        $today = Carbon::today();

        // If no dates are set, vacation mode is active if enabled
        if (empty($fromDate) && empty($toDate)) {
            return true;
        }

        // If only from date is set, check if today is on or after from date
        if (!empty($fromDate) && empty($toDate)) {
            return $today->gte(Carbon::parse($fromDate));
        }

        // If only to date is set, check if today is before to date
        if (empty($fromDate) && !empty($toDate)) {
            return $today->lt(Carbon::parse($toDate));
        }

        // Both dates are set - check if today is within the range
        // Vacation is active from fromDate until (but not including) toDate
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();

        return $today->gte($from) && $today->lt($to);
    }

    /**
     * Get vacation mode info including whether it's active and the formatted return date
     */
    public static function getVacationModeInfo(): array
    {
        $isActive = self::isVacationModeActive();
        $toDate = self::getByKey('vacation_mode_date');
        $fromDate = self::getByKey('vacation_mode_from_date');
        
        $formattedDate = null;
        $message = null;
        
        if ($isActive && $toDate) {
            $formattedDate = Carbon::parse($toDate)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
            $message = "Tuti está de vacaciones. Te esperamos nuevamente {$formattedDate}. ¡Gracias!";
        } elseif ($isActive) {
            $message = "Tuti está de vacaciones. Te esperamos pronto. ¡Gracias!";
        }

        return [
            'enabled' => (self::getByKey('vacation_mode_enabled') === '1'),
            'active' => $isActive,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'formatted_date' => $formattedDate,
            'message' => $message,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        // Clear cache when settings are updated
        static::saved(function ($setting) {
            Cache::forget("setting_{$setting->key}");
        });

        static::deleted(function ($setting) {
            Cache::forget("setting_{$setting->key}");
        });
    }
}
