<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventorySyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'bodega_code',
        'skus_received',
        'products_updated',
        'products_set_to_zero',
        'skus_in_response',
        'soap_response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'skus_in_response' => 'array',
    ];

    public static function reportTimezone(): string
    {
        return (string) config('app.seller_dashboard_timezone', 'America/Bogota');
    }

    /**
     * Configured bodegas with no successful sync on the given calendar date
     * in the business timezone.
     *
     * @return \Illuminate\Support\Collection<int, array{bodega_code: string, last_status: ?string, last_error: ?string, last_attempt_at: mixed, attempt_count: int}>
     */
    public static function unsyncedBodegasOnDate(\Carbon\CarbonInterface $date): \Illuminate\Support\Collection
    {
        $tz = self::reportTimezone();
        $local = \Carbon\Carbon::parse($date->toDateString(), $tz);
        $start = $local->copy()->startOfDay()->utc();
        $end = $local->copy()->endOfDay()->utc();

        $configured = ZoneWarehouse::query()
            ->select('bodega_code')
            ->distinct()
            ->orderBy('bodega_code')
            ->pluck('bodega_code');

        $logsByBodega = self::query()
            ->where('bodega_code', '!=', 'GENERAL')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->get()
            ->groupBy('bodega_code');

        return $configured
            ->map(function (string $bodega) use ($logsByBodega) {
                $logs = $logsByBodega->get($bodega, collect());
                if ($logs->contains(fn (self $log) => $log->status === 'success')) {
                    return null;
                }

                $latest = $logs->first();

                return [
                    'bodega_code' => $bodega,
                    'last_status' => $latest?->status,
                    'last_error' => $latest?->error_message,
                    'last_attempt_at' => $latest?->created_at,
                    'attempt_count' => $logs->count(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Bodegas that were not properly synced on the previous business calendar day.
     */
    public static function unsyncedBodegasYesterday(): \Illuminate\Support\Collection
    {
        return self::unsyncedBodegasOnDate(now(self::reportTimezone())->subDay());
    }

    /**
     * Get the latest sync logs grouped by sync run
     */
    public static function getLatestSyncRun()
    {
        // Get logs from the most recent sync (within last 5 minutes of the latest log)
        $latestLog = self::latest()->first();
        
        if (!$latestLog) {
            return collect();
        }
        
        $fiveMinutesAgo = $latestLog->created_at->subMinutes(5);
        
        return self::where('created_at', '>=', $fiveMinutesAgo)
            ->orderBy('bodega_code')
            ->get();
    }
}
