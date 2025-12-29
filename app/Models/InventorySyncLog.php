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
