<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationItem extends Model
{
    use HasFactory;

    protected $appends = ['price_label'];

    protected $fillable = ['name', 'variation_id'];

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }

    public function getPriceLabelAttribute()
    {
        if (! $this->pivot) {
            return $this->name;
        }

        return $this->variation->name.': '.$this->name.' -  $'.number_format($this->pivot->price);
    }
}
