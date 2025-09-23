@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Promociones</h1>
                            <p class="text-gray-600">Gestiona promociones avanzadas con reglas personalizadas</p>
                        </div>
                        <a href="{{ route('promocion.create') }}" 
                           class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                            Nueva Promoción
                        </a>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <form method="GET" class="flex gap-4">
                            <div class="flex-1">
                                <input type="text" 
                                       name="q" 
                                       value="{{ request('q') }}" 
                                       placeholder="Buscar promociones..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                            </div>
                            <button type="submit" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                Buscar
                            </button>
                            @if(request('q'))
                                <a href="{{ route('promocion.index') }}" 
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                    </div>

                    <!-- Promociones Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nivel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requisitos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vigencia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uso</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($promociones as $promocion)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ $promocion->name }}</div>
                                                @if($promocion->description)
                                                    <div class="text-sm text-gray-500">{{ Str::limit($promocion->description, 50) }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $promocion->discount_value }}{{ $promocion->discount_type === 'percentage' ? '%' : '$' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @switch($promocion->level)
                                                @case('products')
                                                    <span class="text-blue-600">Productos específicos</span>
                                                    @break
                                                @case('categories')
                                                    <span class="text-purple-600">Categorías</span>
                                                    @break
                                                @case('brands')
                                                    <span class="text-indigo-600">Marcas</span>
                                                    @break
                                                @case('vendors')
                                                    <span class="text-orange-600">Proveedores</span>
                                                    @break
                                                @case('zones')
                                                    <span class="text-pink-600">Zonas</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="space-y-1">
                                                @if($promocion->minimum_cart_value)
                                                    <div class="text-xs text-gray-500">
                                                        Mín. valor: ${{ number_format($promocion->minimum_cart_value, 0) }}
                                                    </div>
                                                @endif
                                                @if($promocion->minimum_cart_units)
                                                    <div class="text-xs text-gray-500">
                                                        Mín. unidades: {{ $promocion->minimum_cart_units }}
                                                    </div>
                                                @endif
                                                @if(!$promocion->minimum_cart_value && !$promocion->minimum_cart_units)
                                                    <span class="text-gray-400">Sin requisitos</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="text-xs text-gray-500">Desde: {{ $promocion->valid_from->format('d/m/Y') }}</div>
                                                <div class="text-xs text-gray-500">Hasta: {{ $promocion->valid_to->format('d/m/Y') }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="text-sm">{{ $promocion->current_usage }} usos</div>
                                                @if($promocion->usage_limit)
                                                    <div class="text-xs text-gray-500">de {{ $promocion->usage_limit }} máximo</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($promocion->isActive())
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Activa
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Inactiva
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('promocion.show', $promocion) }}" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Ver
                                                </a>
                                                <a href="{{ route('promocion.edit', $promocion) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    Editar
                                                </a>
                                                <form method="POST" action="{{ route('promocion.destroy', $promocion) }}" 
                                                      class="inline" 
                                                      onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta promoción?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-sparkles', 'w-12 h-12 text-gray-400 mb-2')
                                                <p>No hay promociones configuradas</p>
                                                <a href="{{ route('promocion.create') }}" 
                                                   class="mt-2 text-orange-600 hover:text-orange-700">
                                                    Crear la primera
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($promociones->hasPages())
                        <div class="mt-6">
                            {{ $promociones->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
