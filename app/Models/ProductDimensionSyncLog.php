<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductDimensionSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'item_id_filter',
        'items_received',
        'items_with_dimensions',
        'products_updated',
        'unmatched_skus',
        'error_message',
    ];

    protected $casts = [
        'unmatched_skus' => 'array',
    ];
}
