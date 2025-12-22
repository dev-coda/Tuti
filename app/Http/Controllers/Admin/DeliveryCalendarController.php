<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCalendar;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeliveryCalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $calendars = DeliveryCalendar::query()
            ->when($request->year, function ($query, $year) {
                $query->where('year', 'like', "%{$year}%");
            })
            ->when($request->cycle, function ($query, $cycle) {
                $query->where('cycle', $cycle);
            })
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        $context = compact('calendars');
        return view('delivery-calendars.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('delivery-calendars.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'year' => 'required|string|max:255',
            'month' => 'required|string|max:255',
            'week_number' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cycle' => 'required|in:A,B,C',
        ]);

        DeliveryCalendar::create($validate);

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega creado');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeliveryCalendar $delivery_calendar)
    {
        return view('delivery-calendars.edit', ['deliveryCalendar' => $delivery_calendar]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryCalendar $delivery_calendar)
    {
        $validate = $request->validate([
            'year' => 'required|string|max:255',
            'month' => 'required|string|max:255',
            'week_number' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cycle' => 'required|in:A,B,C',
        ]);

        $delivery_calendar->update($validate);

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryCalendar $delivery_calendar)
    {
        $delivery_calendar->delete();

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega eliminado');
    }
}

