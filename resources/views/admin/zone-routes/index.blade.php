@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block border-b border-gray-200">
    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Rutas por Zona</h1>
    <p class="mt-1 text-sm text-gray-500">Administra las rutas disponibles por zona para el flujo de Cliente Nuevo (ruta siempre de 4 digitos).</p>
</div>

<div class="p-4 space-y-6">
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

