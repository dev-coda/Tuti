@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupons.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a cupones</a>
        <h1 class="text-2xl font-bold text-gray-900">Pruebas de cupones y XML</h1>
        <p class="text-gray-600 mt-1">Módulo de diagnóstico para inspeccionar XML generado y validar comportamiento de cupones. Las pruebas no transmiten órdenes.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Preview existing order XML -->
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Previsualizar XML de orden existente</h2>
            <p class="text-sm text-gray-600 mb-4">Selecciona una orden para ver el XML que se generaría (sin transmitir).</p>
            <form method="GET" action="{{ route('coupon-tests.preview') }}" class="flex gap-2">
                <select name="order_id" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Selecciona una orden</option>
                    @foreach($recentOrders as $o)
                        <option value="{{ $o->id }}">
                            #{{ $o->id }} - {{ $o->user->name ?? 'N/A' }} - ${{ number_format($o->total, 0) }}
                            @if($o->coupon_code) ({{ $o->coupon_code }}) @endif
                            - {{ $o->created_at->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    Ver XML
                </button>
            </form>
        </div>

        <!-- Run mock test -->
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Prueba con orden simulada</h2>
            <p class="text-sm text-gray-600 mb-4">Crea una orden simulada con productos y cupones para inspeccionar el XML generado.</p>
            <a href="{{ route('coupon-tests.mock') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                Ejecutar prueba simulada
            </a>
        </div>
    </div>

    <div class="mt-8 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <h3 class="font-semibold text-amber-900 mb-2">Reglas del XML</h3>
        <ul class="text-sm text-amber-800 space-y-1">
            <li>• <strong>Descuentos %:</strong> Deben usar el campo <code>&lt;dyn:discount&gt;</code></li>
            <li>• <strong>Descuentos monto fijo:</strong> Deben modificar <code>&lt;dyn:unitPrice&gt;</code> (precio transmitido reducido), discount = 0</li>
        </ul>
    </div>
</div>
@endsection
