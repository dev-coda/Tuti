<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'enabled',
        'show_in_footer',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'show_in_footer' => 'boolean',
    ];

    /**
     * Scope to only get enabled pages
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeShownInFooter($query)
    {
        return $query->where('show_in_footer', true);
    }
}
