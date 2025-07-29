<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'business_name', 'read', 'city', 'city_id', 'nit', 'terms_accepted'];

    protected $appends = ['state'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function scopeUnRead($query)
    {
        return $query->where('read', false);
    }

    //get total row unread
    public function scopeTotalUnRead()
    {
        return $this->unRead()->count();
    }

    protected function state(): Attribute
    {
        return Attribute::get(function () {
            $existe = User::where('email', $this->email)->exists();
            return $existe ? 'Existente' : 'Nuevo';
        });
    }
}
