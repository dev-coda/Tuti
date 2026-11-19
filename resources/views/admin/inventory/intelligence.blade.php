@extends('layouts.admin')

@section('content')
<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('admin.inventory._nav', [
            'title' => 'Inteligencia de inventario',
            'subtitle' => 'Compara el disponible con la venta procesada, enviada o entregada. La venta es de toda la compañía. Si filtras una bodega, el disponible es solo de esa bodega y la cobertura se acorta.',
        ])

        <form method="GET" action="{{ route('admin.inventory.intelligence') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div>
                <label for="window" class="block text-sm font-medium text-gray-700">Ventana de venta</label>
                <select id="window" name="window" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                    @foreach ([7, 14, 30, 60, 90] as $days)
                        <option value="{{ $days }}" @selected($windowDays === $days)>{{ $days }} días</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cover" class="block text-sm font-medium text-gray-700">Cobertura objetivo</label>
                <select id="cover" name="cover" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                    @foreach ([7, 14, 21, 30, 45] as $days)
                        <option value="{{ $days }}" @selected($coverDays === $days)>{{ $days }} días</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bodega" class="block text-sm font-medium text-gray-700">Disponible de</label>
                <select id="bodega" name="bodega" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Todas las bodegas</option>
                    @foreach ($warehouses as $code)
                        <option value="{{ $code }}" @selected($bodega === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white">Actualizar</button>
        </form>

        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-500">Productos</p>
                <p class="text-2xl font-semibold">{{ number_format($summary['products']) }}</p>
            </div>
            <div class="rounded-lg bg-red-50 p-4 shadow-sm">
                <p class="text-sm text-red-700">Agotados</p>
                <p class="text-2xl font-semibold text-red-900">{{ number_format($summary['stockouts']) }}</p>
            </div>
            <div class="rounded-lg bg-amber-50 p-4 shadow-sm">
                <p class="text-sm text-amber-800">Cobertura corta</p>
                <p class="text-2xl font-semibold text-amber-950">{{ number_format($summary['low_cover']) }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4 shadow-sm">
                <p class="text-sm text-slate-600">Sin rotación</p>
                <p class="text-2xl font-semibold text-slate-900">{{ number_format($summary['dead']) }}</p>
            </div>
        </div>

        @include('admin.inventory._products', [
            'heading' => 'Reposición sugerida',
            'note' => 'Solo productos con venta en la ventana. La cantidad llega a la cobertura objetivo y, como mínimo, al piso.',
            'rows' => $reorder,
            'empty' => 'Nada para reponer con la venta de esta ventana.',
        ])

        @include('admin.inventory._products', [
            'heading' => 'Sin rotación',
            'note' => 'Tienen disponible por encima del piso y no se vendieron en la ventana.',
            'rows' => $dead,
            'empty' => 'No hay stock quieto sobre el piso.',
        ])

        @include('admin.inventory._products', [
            'heading' => 'Más vendidos',
            'note' => 'Venta de la compañía en la ventana, con el disponible actual.',
            'rows' => $movers,
            'empty' => 'No hay ventas de productos gestionados en la ventana.',
        ])
    </div>
</div>
@endsection
