@extends('layouts.admin')

@section('title', 'Pedido sugerido')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Pedido sugerido</h1>
                <p class="text-sm text-gray-500">Define cómo se arma el pedido que el vendedor carga desde Mi Cuenta</p>
            </div>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                Volver a Configuraciones
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="col-span-full mb-4">
            <div class="flex items-center p-4 text-sm text-green-800 border border-green-300 rounded-lg bg-green-50">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="col-span-full xl:col-span-2">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <p class="text-sm text-gray-600 mb-6">
                El pedido parte de lo que el cliente ya compra. El sobrepedido sube el valor por encima de su ticket promedio.
                La prioridad de cada heurística decide con qué se cubre ese incremento: más unidades de lo que ya pide, productos que se compran juntos, o los más vendidos.
            </p>

            <form action="{{ route('settings.pedido-sugerido.update') }}" method="POST" class="space-y-6">
                @csrf

                <label class="flex items-center gap-3">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500" @checked(old('enabled', $config['enabled'] ? '1' : '0') == '1')>
                    <span class="text-sm font-medium text-gray-800">Activar pedido sugerido</span>
                </label>

                <div>
                    <h2 class="text-sm font-semibold text-gray-900 mb-1">Prioridad de heurísticas</h2>
                    <p class="text-xs text-gray-500 mb-4">Un número más alto pesa más. No tienen que sumar 100.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="weight_history" class="block text-xs font-medium text-gray-500 mb-1">Pedidos anteriores</label>
                            <input id="weight_history" name="weight_history" type="number" min="0" max="100" required
                                   value="{{ old('weight_history', $config['weight_history']) }}"
                                   class="w-full border-gray-300 rounded-lg text-sm">
                            @error('weight_history')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="weight_together" class="block text-xs font-medium text-gray-500 mb-1">Comprados juntos</label>
                            <input id="weight_together" name="weight_together" type="number" min="0" max="100" required
                                   value="{{ old('weight_together', $config['weight_together']) }}"
                                   class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label for="weight_bestseller" class="block text-xs font-medium text-gray-500 mb-1">Más vendidos</label>
                            <input id="weight_bestseller" name="weight_bestseller" type="number" min="0" max="100" required
                                   value="{{ old('weight_bestseller', $config['weight_bestseller']) }}"
                                   class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="overorder_percent" class="block text-xs font-medium text-gray-500 mb-1">Sobrepedido (%)</label>
                        <input id="overorder_percent" name="overorder_percent" type="number" min="0" max="200" step="0.5" required
                               value="{{ old('overorder_percent', $config['overorder_percent']) }}"
                               class="w-full border-gray-300 rounded-lg text-sm">
                        <p class="mt-1 text-xs text-gray-500">Porcentaje por encima del ticket promedio del cliente.</p>
                        @error('overorder_percent')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="history_limit" class="block text-xs font-medium text-gray-500 mb-1">Pedidos de historial</label>
                        <input id="history_limit" name="history_limit" type="number" min="1" max="50" required
                               value="{{ old('history_limit', $config['history_limit']) }}"
                               class="w-full border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label for="max_lines" class="block text-xs font-medium text-gray-500 mb-1">Máximo de productos</label>
                        <input id="max_lines" name="max_lines" type="number" min="1" max="40" required
                               value="{{ old('max_lines', $config['max_lines']) }}"
                               class="w-full border-gray-300 rounded-lg text-sm">
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-lg shadow-sm hover:bg-orange-700 focus:ring-4 focus:ring-orange-300">
                    Guardar
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
