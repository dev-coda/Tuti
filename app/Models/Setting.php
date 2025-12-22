<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Setting extends Model
{
    use HasFactory;

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
