@extends('layouts.admin')

@section('content')

    @if (session('success'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
        <div class="w-full">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Tamaños de Empaque</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Empaques usados para asignar los envíos de Coordinadora según las dimensiones de los productos y del pedido.
                    </p>
                </div>
                <div class="flex gap-2">
                    <form action="{{ route('package-types.sync-dimensions') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-2')
                            Sincronizar dimensiones
                        </button>
                    </form>
                    <a href="{{ route('package-types.create') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                        Nuevo tamaño
                    </a>
                </div>
            </div>

            {{-- Dimension sync status --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $dimensionStats['with_dimensions'] }} / {{ $dimensionStats['total_products'] }}</div>
                    <div class="text-sm text-gray-500">Productos con dimensiones</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-lg font-semibold text-gray-900">{{ $dimensionStats['last_synced_at'] ?? 'Nunca' }}</div>
                    <div class="text-sm text-gray-500">Última sincronización completa</div>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="text-lg font-semibold {{ $syncLogs->first()?->status === 'error' ? 'text-red-600' : 'text-green-600' }}">
                        {{ $syncLogs->first()?->status === 'error' ? 'Error' : ($syncLogs->isEmpty() ? '—' : 'OK') }}
                    </div>
                    <div class="text-sm text-gray-500">Estado del último intento</div>
                </div>
            </div>

            {{-- Package types table --}}
            <div class="overflow-x-auto border border-gray-200 rounded-lg mb-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Código</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Nombre</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Dimensiones máx. (L × A × H cm)</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Peso máx. (kg)</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Orden</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Activo</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($packageTypes as $packageType)
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-medium text-gray-900">{{ $packageType->code }}</td>
                                <td class="p-4 text-sm text-gray-900">{{ $packageType->name }}</td>
                                <td class="p-4 text-sm text-gray-900">
                                    {{ rtrim(rtrim(number_format($packageType->max_length_cm, 2), '0'), '.') }} ×
                                    {{ rtrim(rtrim(number_format($packageType->max_width_cm, 2), '0'), '.') }} ×
                                    {{ rtrim(rtrim(number_format($packageType->max_height_cm, 2), '0'), '.') }}
                                </td>
                                <td class="p-4 text-sm text-gray-900">{{ rtrim(rtrim(number_format($packageType->max_weight_kg, 3), '0'), '.') }}</td>
                                <td class="p-4 text-sm text-gray-900">{{ $packageType->position }}</td>
                                <td class="p-4">
                                    @if ($packageType->active)
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Sí</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">No</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('package-types.edit', $packageType) }}" class="text-blue-600 hover:underline text-sm">Editar</a>
                                        <form action="{{ route('package-types.destroy', $packageType) }}" method="POST"
                                            onsubmit="return confirm('¿Eliminar el tamaño {{ $packageType->code }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-sm text-gray-500">
                                    No hay tamaños de empaque. Cree uno o ejecute <code>php artisan db:seed --class=PackageTypeSeeder</code> para cargar los valores por defecto.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Sync logs --}}
            <div class="border border-gray-200 rounded-lg p-4">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Registro de sincronización de dimensiones</h2>
                @if ($syncLogs->isEmpty())
                    <p class="text-sm text-gray-500">Aún no se ha ejecutado ninguna sincronización.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Fecha</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Estado</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Filtro</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Artículos</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Con dimensiones</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Productos actualizados</th>
                                    <th class="p-3 text-xs font-medium text-left text-gray-500 uppercase">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($syncLogs as $log)
                                    <tr>
                                        <td class="p-3 text-sm text-gray-900 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="p-3">
                                            @if ($log->status === 'success')
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">OK</span>
                                            @else
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Error</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-sm text-gray-900">{{ $log->item_id_filter ?? 'Todos' }}</td>
                                        <td class="p-3 text-sm text-gray-900">{{ $log->items_received }}</td>
                                        <td class="p-3 text-sm text-gray-900">{{ $log->items_with_dimensions }}</td>
                                        <td class="p-3 text-sm text-gray-900">{{ $log->products_updated }}</td>
                                        <td class="p-3 text-sm">
                                            @if ($log->error_message)
                                                <span class="text-red-600 text-xs">{{ $log->error_message }}</span>
                                            @elseif (!empty($log->unmatched_skus))
                                                <details>
                                                    <summary class="cursor-pointer text-blue-600 hover:underline text-xs">{{ count($log->unmatched_skus) }} SKUs sin coincidencia</summary>
                                                    <div class="text-xs text-gray-600 mt-1 max-w-md break-all">{{ implode(', ', $log->unmatched_skus) }}</div>
                                                </details>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
