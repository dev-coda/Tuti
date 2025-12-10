<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DeliveryCalendar extends Model
{
    use HasFactory;

    protected $table = 'delivery_calendar';

    protected $fillable = [
        'year',
        'month',
        'week_number',
        'start_date',
        'end_date',
        'cycle',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the next available week for a given cycle
     */
    public static function getNextAvailableWeek(string $cycle, Carbon $fromDate = null): ?self
    {
        $fromDate = $fromDate ?? now();
        
        return self::where('cycle', $cycle)
            ->where('end_date', '>=', $fromDate->format('Y-m-d'))
            ->orderBy('start_date', 'asc')
            ->first();
    }

    /**
     * Get week that contains a specific date
     */
    public static function getWeekForDate(Carbon $date): ?self
    {
        return self::where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->first();
    }
}
