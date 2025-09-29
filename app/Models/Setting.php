<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
