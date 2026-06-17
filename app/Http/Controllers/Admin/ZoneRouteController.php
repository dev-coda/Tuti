<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncZoneRuteros;
use App\Models\Zone;
use App\Models\ZoneRoute;
use App\Services\RuteroZoneSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function syncFromRuteros(Request $request, RuteroZoneSyncService $syncService)
    {
        $zones = collect($request->input('zones', []))
            ->map(fn ($zone) => trim((string) $zone))
            ->filter()
            ->values()
            ->all();

        $zoneCodes = $syncService->discoverZoneCodes($zones !== [] ? $zones : null);

        if ($zoneCodes === []) {
            return back()->with('error', 'No hay zonas configuradas para sincronizar.');
        }

        $sessionId = 'manual-' . now()->format('Ymd-His') . '-' . Str::random(8);
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        SyncZoneRuteros::dispatch(
            $zoneCodes,
            $sessionId,
            ! $request->boolean('catalog_only'),
        )
            ->onConnection($queueConnection)
            ->onQueue('default');

        return back()->with(
            'success',
            'Sincronización enviada a la cola para '.count($zoneCodes).' zona(s). Session: '.$sessionId
        );
    }
}

