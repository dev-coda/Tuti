@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block border-b border-gray-200">
    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Rutas por Zona</h1>
    <p class="mt-1 text-sm text-gray-500">Administra las rutas disponibles por zona para el flujo de Cliente Nuevo (ruta siempre de 4 digitos).</p>
</div>

<div class="p-4 space-y-6">
    @if(session('success'))
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h2 class="text-lg font-medium text-gray-900 mb-2">Importar desde Dynamics (getRuteros)</h2>
        <p class="text-sm text-gray-500 mb-4">
            Consulta getRuteros para cada zona conocida (vendedores, clientes, bodegas y catálogo actual)
            y llena <code class="bg-gray-100 px-1 rounded">zone_routes</code> junto con zona/ruta/día en las sucursales existentes.
            La sincronización se ejecuta en segundo plano; revisa los logs para el resultado.
        </p>
        <form method="POST" action="{{ route('zone-routes.sync-from-ruteros') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700">
                Sincronizar desde getRuteros
            </button>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="catalog_only" value="1" class="rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                Solo catálogo zone_routes (no actualizar clientes)
            </label>
        </form>
        <p class="mt-3 text-xs text-gray-500">
            También disponible por consola: <code class="bg-gray-100 px-1 rounded">php artisan zone-routes:sync-from-ruteros</code>
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4">
        <h2 class="text-lg font-medium text-gray-900 mb-3">Agregar ruta a zona</h2>
        <form method="POST" action="{{ route('zone-routes.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label for="zone" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select id="zone" name="zone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Selecciona una zona</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone }}">{{ $zone }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="route" class="block text-sm font-medium text-gray-700 mb-1">Ruta (4 digitos)</label>
                <input id="route" name="route" type="text" inputmode="numeric" pattern="\d{4}" maxlength="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="1234" required>
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Agregar</button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                    <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase">Rutas</th>
                    <th class="p-3 text-left text-xs font-medium text-gray-500 uppercase">Accion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($routesByZone as $zone => $routes)
                    @foreach($routes as $route)
                        <tr>
                            <td class="p-3 text-sm text-gray-900">{{ $zone }}</td>
                            <td class="p-3 text-sm font-mono text-gray-700">{{ $route->route }}</td>
                            <td class="p-3">
                                <form method="POST" action="{{ route('zone-routes.destroy', $route) }}" onsubmit="return confirm('Eliminar esta ruta de la zona?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="3" class="p-4 text-sm text-gray-500 text-center">No hay rutas configuradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

