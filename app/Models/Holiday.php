<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type_id',
        'date',
        'day_type'
    ];

    const HOLIDAY = 1;
    const SATURDAY = 2;
    
    const DAY_TYPE_FESTIVO = 'festivo';
    const DAY_TYPE_LABORAL = 'laboral';

    protected $casts = [
        'date' => 'date',
    ];

    public function getTypeAttribute()
    {
        return $this->type_id === self::HOLIDAY ? 'Festivo' : 'Sábado';
    }

    public function getDayAttribute()
    {
        //array of days of week spanish
        $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return $days[$this->date->dayOfWeek];
    }
}
