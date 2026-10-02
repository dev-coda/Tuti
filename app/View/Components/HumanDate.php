<?php

namespace App\View\Components;

use App\Repositories\OrderRepository;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class HumanDate extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $date = OrderRepository::getBusinessDay();
        $date = OrderRepository::parseBusinessDate($date);
        //spanis days array
        $days = [
            "Domingo",
            "Lunes",
            "Martes",
            "Miércoles",
            "Jueves",
            "Viernes",
            "Sábado"
        ];
          
       

        $months = [
            "Enero",
            "Febrero",
            "Marzo",
            "Abril",
            "Mayo",
            "Junio",
            "Julio",
            "Agosto",
            "Septiembre",
            "Octubre",
            "Noviembre",
            "Diciembre"
        ];

        //format date Lunes 12 de Julio
        $date = $days[$date->dayOfWeek] . ' ' . $date->day . ' de ' . $months[$date->month - 1];

        return view('components.human-date', ['date' => $date]);
    }
}
