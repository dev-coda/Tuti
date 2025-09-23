@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $promocion->name }}</h1>
                        <p class="text-gray-600">Detalles de la promoción</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('promocion.edit', $promocion) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Editar
                        </a>
                        <a href="{{ route('promocion.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Volver
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->name }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descripción</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->description ?: 'Sin descripción' }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado</label>
                            <div class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $promocion->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $promocion->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Discount Configuration -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Configuración del Descuento</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo de Descuento</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $promocion->discount_type === 'percentage' ? 'Porcentaje (%)' : 'Valor Fijo ($)' }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Valor del Descuento</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $promocion->discount_type === 'percentage' ? $promocion->discount_value . '%' : '$' . number_format($promocion->discount_value, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <!-- Validity Period -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Período de Validez</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Válido Desde</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->valid_from->format('d/m/Y H:i') }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Válido Hasta</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->valid_to->format('d/m/Y H:i') }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado Actual</label>
                            <div class="mt-1">
                                @php
                                    $now = now();
                                    $isActive = $now->between($promocion->valid_from, $promocion->valid_to);
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $isActive ? 'Vigente' : 'Expirada' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Information -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Información de Uso</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Usos Actuales</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->current_usage }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Límite de Usos</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $promocion->usage_limit ?: 'Sin límite' }}</div>
                        </div>

                        @if($promocion->usage_limit)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Usos Restantes</label>
                            <div class="mt-1 text-sm text-gray-900">{{ max(0, $promocion->usage_limit - $promocion->current_usage) }}</div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Application Scope -->
                <div class="mt-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Ámbito de Aplicación</h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Aplicar a</label>
                        <div class="mt-1 text-sm text-gray-900">
                            @switch($promocion->level)
                                @case('products')
                                    Productos Específicos
                                    @break
                                @case('categories')
                                    Categorías
                                    @break
                                @case('brands')
                                    Marcas
                                    @break
                                @case('vendors')
                                    Proveedores
                                    @break
                                @case('zones')
                                    Zonas
                                    @break
                                @default
                                    {{ $promocion->level }}
                            @endswitch
                        </div>
                    </div>

                    @if($promocion->level_ids && count($promocion->level_ids) > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Elementos Seleccionados</label>
                        <div class="mt-1">
                            <div class="flex flex-wrap gap-2">
                                @foreach($promocion->level_ids as $id)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Elemento {{ $id }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Minimum Requirements -->
                <div class="mt-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Requisitos Mínimos</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Valor Mínimo del Carrito</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $promocion->minimum_cart_value ? '$' . number_format($promocion->minimum_cart_value, 2) : 'Sin requisito' }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unidades Mínimas del Carrito</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $promocion->minimum_cart_units ?: 'Sin requisito' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage History -->
                @if($promocion->usages->count() > 0)
                <div class="mt-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Historial de Uso</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($promocion->usages->take(10) as $usage)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $usage->user ? $usage->user->name : 'Usuario eliminado' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        #{{ $usage->order->id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $usage->created_at->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if($promocion->usages->count() > 10)
                    <div class="text-sm text-gray-500 text-center">
                        Mostrando los últimos 10 usos de {{ $promocion->usages->count() }} totales
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
