<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\ZoneRoute;
use Illuminate\Http\Request;

class ZoneRouteController extends Controller
{
    public function index()
    {
        $zonesFromUsers = Zone::query()
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone');

        $zonesFromCatalog = ZoneRoute::query()
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone');

        $zones = $zonesFromUsers->merge($zonesFromCatalog)->unique()->values();

        $routesByZone = ZoneRoute::query()
            ->orderBy('zone')
            ->orderBy('route')
            ->get()
            ->groupBy('zone');

        return view('admin.zone-routes.index', compact('zones', 'routesByZone'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone' => ['required', 'string', 'max:3'],
            'route' => ['required', 'regex:/^\d{4}$/'],
        ]);

        ZoneRoute::firstOrCreate([
            'zone' => strtoupper(trim($validated['zone'])),
            'route' => $validated['route'],
        ]);

        return back()->with('success', 'Ruta asociada a la zona correctamente.');
    }

    public function destroy(ZoneRoute $zoneRoute)
    {
        $zoneRoute->delete();

        return back()->with('success', 'Ruta eliminada de la zona.');
    }
}

