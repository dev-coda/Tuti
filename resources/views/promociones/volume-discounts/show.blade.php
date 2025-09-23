@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $volumeDiscount->name }}</h1>
                        <p class="text-gray-600">Detalles del descuento por volumen</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('volume-discounts.edit', $volumeDiscount) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Editar
                        </a>
                        <a href="{{ route('volume-discounts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
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
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                                    <dd class="text-sm text-gray-900">{{ $volumeDiscount->name }}</dd>
                                </div>
                                
                                @if($volumeDiscount->description)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                                    <dd class="text-sm text-gray-900">{{ $volumeDiscount->description }}</dd>
                                </div>
                                @endif
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                    <dd class="text-sm">
                                        @if($volumeDiscount->active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Inactivo
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Discount Configuration -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Configuración del Descuento</h3>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Tipo de Descuento</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($volumeDiscount->discount_type === 'percentage')
                                            Porcentaje (%)
                                        @else
                                            Valor Fijo ($)
                                        @endif
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Valor del Descuento</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $volumeDiscount->discount_value }}{{ $volumeDiscount->discount_type === 'percentage' ? '%' : '$' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <!-- Quantity Configuration -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Configuración de Cantidad</h3>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Cantidad Mínima</dt>
                                    <dd class="text-sm text-gray-900">{{ $volumeDiscount->min_quantity }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Cantidad Máxima</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $volumeDiscount->max_quantity ?: 'Sin límite' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Validity Period -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Período de Validez</h3>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Válido Desde</dt>
                                    <dd class="text-sm text-gray-900">{{ $volumeDiscount->valid_from->format('d/m/Y H:i') }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Válido Hasta</dt>
                                    <dd class="text-sm text-gray-900">{{ $volumeDiscount->valid_to->format('d/m/Y H:i') }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Estado Actual</dt>
                                    <dd class="text-sm">
                                        @if($volumeDiscount->isActive())
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Válido
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Expirado
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Application Scope -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900">Ámbito de Aplicación</h3>
                    
                    <div class="bg-gray-50 p-4 rounded-lg mt-4">
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Aplicar a</dt>
                                <dd class="text-sm text-gray-900">
                                    @switch($volumeDiscount->applies_to)
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
                                        @case('cart')
                                            Carrito Completo
                                            @break
                                        @default
                                            {{ $volumeDiscount->applies_to }}
                                    @endswitch
                                </dd>
                            </div>
                            
                            @if($volumeDiscount->applies_to_ids && count($volumeDiscount->applies_to_ids) > 0)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Elementos Seleccionados</dt>
                                <dd class="text-sm text-gray-900">
                                    <ul class="list-disc list-inside">
                                        @foreach($volumeDiscount->applies_to_ids as $id)
                                            <li>ID: {{ $id }}</li>
                                        @endforeach
                                    </ul>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex justify-between">
                    <form action="{{ route('volume-discounts.destroy', $volumeDiscount) }}" method="POST" 
                          onsubmit="return confirm('¿Estás seguro de que quieres eliminar este descuento por volumen?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Eliminar
                        </button>
                    </form>
                    
                    <div class="text-sm text-gray-500">
                        Creado: {{ $volumeDiscount->created_at->format('d/m/Y H:i') }}
                        @if($volumeDiscount->updated_at != $volumeDiscount->created_at)
                            <br>Actualizado: {{ $volumeDiscount->updated_at->format('d/m/Y H:i') }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
