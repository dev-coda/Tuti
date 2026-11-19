@extends('layouts.admin')

@section('content')
<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('admin.inventory._nav', [
            'title' => 'Control de inventario',
            'subtitle' => 'Stock sincronizado por bodega. El piso es el stock de seguridad del producto, si no el de la categoría, y si no el mínimo global. Este módulo no escribe inventario.',
        ])

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Líneas</p>
                <p class="text-2xl font-semibold text-gray-900">{{ number_format($summary['lines']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Disponible</p>
                <p class="text-2xl font-semibold text-gray-900">{{ number_format($summary['line_units']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Agotadas</p>
                <p class="text-2xl font-semibold text-red-700">{{ number_format($summary['line_stockouts']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Bajo piso</p>
                <p class="text-2xl font-semibold text-orange-700">{{ number_format($summary['line_below_floor']) }}</p>
            </div>
        </div>

        <p class="mb-4 text-sm text-gray-500">
            Piso global: {{ $summary['global_floor'] }}.
            {{ $summary['inventory_enabled'] ? 'La validación de inventario en la tienda está activa.' : 'La validación de inventario en la tienda está apagada.' }}
            @if ($summary['last_sync'])
                Última sincronización: {{ $summary['last_sync']['at'] }} ({{ $summary['last_sync']['bodega'] }}, {{ $summary['last_sync']['status'] }}).
            @else
                Sin sincronizaciones registradas.
            @endif
        </p>

        <form method="GET" action="{{ route('admin.inventory.index') }}" class="mb-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 md:grid-cols-4">
            <div>
                <label for="q" class="block text-sm font-medium text-gray-700">Producto o SKU</label>
                <input id="q" name="q" value="{{ $filters['q'] }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
            </div>
            <div>
                <label for="bodega" class="block text-sm font-medium text-gray-700">Bodega</label>
                <select id="bodega" name="bodega" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Todas</option>
                    @foreach ($warehouses as $code)
                        <option value="{{ $code }}" @selected($filters['bodega'] === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                <select id="status" name="status" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                    @foreach (['all' => 'Todos', 'stockout' => 'Agotados', 'below_floor' => 'Bajo piso', 'in_stock' => 'En piso'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Filtrar</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Bodega</th>
                        <th class="px-4 py-3 text-right">Disponible</th>
                        <th class="px-4 py-3 text-right">Físico</th>
                        <th class="px-4 py-3 text-right">Reservado</th>
                        <th class="px-4 py-3 text-right">Piso</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('products.edit', $row['product_id']) }}" class="font-medium text-gray-900 hover:underline">{{ $row['name'] }}</a>
                                <div class="text-xs text-gray-500">{{ $row['sku'] }}@if ($row['variation']) · {{ $row['variation'] }}@endif</div>
                                @unless ($row['active'])
                                    <span class="text-xs text-gray-400">Inactivo</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3">{{ $row['bodega'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['available']) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['physical']) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['reserved']) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['floor']) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $row['status_class'] }}">{{ $row['status_label'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No hay líneas de inventario con ese filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $rows->links('vendor.pagination.tailwind') }}</div>
    </div>
</div>
@endsection
