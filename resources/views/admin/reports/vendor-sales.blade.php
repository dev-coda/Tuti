@extends('layouts.admin')

@section('title', 'Ventas por proveedor')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="flex flex-col w-full mb-1">
        <div class="mb-2">
            <a href="{{ route('admin.reports.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Reportes</a>
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl mt-2">Ventas por proveedor</h1>
            <p class="text-sm text-gray-600 mt-1">
                Ventas de productos de los proveedores seleccionados, incluyendo las unidades bonificadas.
                Las fechas son días calendario de Colombia. Por defecto el rango es el mes anterior y el proveedor ETERNA.
            </p>
        </div>
    </div>
</div>

<div class="p-4">
    @if($errors->any())
        <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $checkedVendorIds = array_map('intval', old('vendor_ids', $selectedVendorIds));
    @endphp

    <form method="GET" action="{{ route('admin.reports.vendor-sales.export') }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Fecha desde</label>
                <input type="date" id="date_from" name="date_from" required
                    value="{{ old('date_from', $dateFrom) }}"
                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Fecha hasta</label>
                <input type="date" id="date_to" name="date_to" required
                    value="{{ old('date_to', $dateTo) }}"
                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">Proveedores</label>
                <div class="flex gap-3 text-xs">
                    <button type="button" id="select-all-vendors" class="text-blue-600 hover:text-blue-800">Seleccionar todos</button>
                    <button type="button" id="select-eterna" class="text-blue-600 hover:text-blue-800">Solo ETERNA</button>
                </div>
            </div>
            <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @forelse($vendors as $vendor)
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input type="checkbox" name="vendor_ids[]" value="{{ $vendor->id }}"
                            class="vendor-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            data-vendor-name="{{ $vendor->name }}"
                            {{ in_array((int) $vendor->id, $checkedVendorIds, true) ? 'checked' : '' }}>
                        <span>{{ $vendor->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-500">No hay proveedores registrados.</p>
                @endforelse
            </div>
            <p class="mt-2 text-xs text-gray-500">
                El archivo incluye ventas del proveedor, bonificaciones, el pedido completo (con productos de otros proveedores) y un resumen por producto.
                Solo entran pedidos procesados, enviados o entregados que incluyen al menos un producto de los proveedores seleccionados.
                Las unidades vendidas coinciden con la cantidad enviada. Las bonificaciones se listan aparte, en unidades regaladas. El valor se muestra antes de IVA y con IVA.
            </p>
            @if($selectedVendorIds === [])
                <p class="mt-2 text-xs text-amber-700">No se encontró un proveedor ETERNA. Selecciona al menos uno para generar el reporte.</p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 mr-2')
                Descargar Excel
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('select-all-vendors')?.addEventListener('click', function () {
        document.querySelectorAll('.vendor-checkbox').forEach(function (box) {
            box.checked = true;
        });
    });

    document.getElementById('select-eterna')?.addEventListener('click', function () {
        document.querySelectorAll('.vendor-checkbox').forEach(function (box) {
            box.checked = (box.dataset.vendorName || '').toLowerCase().includes('eterna');
        });
    });
</script>
@endsection
