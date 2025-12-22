<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteCycle;
use Illuminate\Http\Request;

class RouteCycleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $routeCycles = RouteCycle::query()
            ->when($request->route, function ($query, $route) {
                $query->where('route', 'like', "%{$route}%");
            })
            ->when($request->cycle, function ($query, $cycle) {
                $query->where('cycle', $cycle);
            })
            ->orderBy('route')
            ->paginate(20);

        $context = compact('routeCycles');
        return view('route-cycles.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('route-cycles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'route' => 'required|string|max:255|unique:route_cycles,route',
            'cycle' => 'required|in:A,B,C',
        ]);

        RouteCycle::create($validate);

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta creado');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RouteCycle $route_cycle)
    {
        return view('route-cycles.edit', ['routeCycle' => $route_cycle]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RouteCycle $route_cycle)
    {
        $validate = $request->validate([
            'route' => 'required|string|max:255|unique:route_cycles,route,' . $route_cycle->id,
            'cycle' => 'required|in:A,B,C',
        ]);

        $route_cycle->update($validate);

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RouteCycle $route_cycle)
    {
        $route_cycle->delete();

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta eliminado');
    }
}

