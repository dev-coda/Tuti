@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Bonificaciones</h1>
                            <p class="text-gray-600">Gestiona bonificaciones de productos (comprar X, llevar Y)</p>
                        </div>
                        <a href="{{ route('bonifications.create') }}" 
                           class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                            Nueva Bonificación
                        </a>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <form method="GET" class="flex gap-4">
                            <div class="flex-1">
                                <input type="text" 
                                       name="q" 
                                       value="{{ request('q') }}" 
                                       placeholder="Buscar bonificaciones..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <button type="submit" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                Buscar
                            </button>
                            @if(request('q'))
                                <a href="{{ route('promociones.bonificaciones') }}" 
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                    </div>

                    <!-- Bonifications Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Compra</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lleva</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Máximo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto Base</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productos Aplicables</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($bonifications as $bonification)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $bonification->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $bonification->buy }} unidades
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $bonification->get }} unidades
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                {{ $bonification->max }} máximo
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($bonification->product)
                                                <div class="flex items-center">
                                                    @if($bonification->product->image)
                                                        <img class="h-8 w-8 rounded-full object-cover mr-2" 
                                                             src="{{ asset('storage/' . $bonification->product->image) }}" 
                                                             alt="{{ $bonification->product->name }}">
                                                    @else
                                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                                                            @svg('heroicon-o-cube', 'w-4 h-4 text-gray-400')
                                                        </div>
                                                    @endif
                                                    <span class="text-sm">{{ $bonification->product->name }}</span>
                                                </div>
                                            @else
                                                <span class="text-gray-400">Sin producto base</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="text-blue-600">{{ $bonification->products_count }} productos</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('bonifications.edit', $bonification) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    Editar
                                                </a>
                                                <form method="POST" action="{{ route('bonifications.destroy', $bonification) }}" 
                                                      class="inline" 
                                                      onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta bonificación?')">
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
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            <div class="flex flex-col items-center">
                                                @svg('heroicon-o-gift', 'w-12 h-12 text-gray-400 mb-2')
                                                <p>No hay bonificaciones configuradas</p>
                                                <a href="{{ route('bonifications.create') }}" 
                                                   class="mt-2 text-purple-600 hover:text-purple-700">
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
                    @if($bonifications->hasPages())
                        <div class="mt-6">
                            {{ $bonifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
