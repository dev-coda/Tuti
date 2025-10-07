@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Cupones</h1>
                            <p class="text-gray-600">Gestiona cupones de descuento</p>
                        </div>
                        <a href="{{ route('coupons.create') }}" 
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                            Nuevo Cupón
                        </a>
                    </div>

                    <!-- Search -->
                    <div class="mb-6">
                        <form method="GET" class="flex gap-4">
                            <div class="flex-1">
                                <input type="text" 
                                       name="q" 
                                       value="{{ request('q') }}" 
                                       placeholder="Buscar cupones por nombre o código..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <button type="submit" 
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                Buscar
                            </button>
                            @if(request('q'))
                                <a href="{{ route('promociones.cupones') }}" 
                                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                    Limpiar
                                </a>
                            @endif
                        </form>
                    </div>

                    <!-- Coupons Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aplica a</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vigencia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uso</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($coupons as $coupon)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-mono font-medium text-gray-900">{{ $coupon->code }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $coupon->name }}</div>
                                            @if($coupon->description)
                                                <div class="text-sm text-gray-500">{{ Str::limit($coupon->description, 50) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $coupon->value }}{{ $coupon->type === 'percentage' ? '%' : '$' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @switch($coupon->applies_to)
                                                @case('cart')
                                                    <span class="text-gray-600">Carrito completo</span>
                                                    @break
                                                @case('product')
                                                    <span class="text-blue-600">Productos específicos</span>
                                                    @break
                                                @case('category')
                                                    <span class="text-purple-600">Categorías</span>
                                                    @break
                                                @case('brand')
                                                    <span class="text-indigo-600">Marcas</span>
                                                    @break
                                                @case('vendor')
                                                    <span class="text-orange-600">Proveedores</span>
                                                    @break
                                                @case('customer')
                                                    <span class="text-pink-600">Clientes específicos</span>
                                                    @break
                                                @case('customer_type')
                                                    <span class="text-yellow-600">Tipos de cliente</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="text-xs text-gray-500">Desde: {{ $coupon->valid_from->format('d/m/Y') }}</div>
                                                <div class="text-xs text-gray-500">Hasta: {{ $coupon->valid_to->format('d/m/Y') }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div>
                                                <div class="text-sm">{{ $coupon->current_usage }} usos</div>
                                                @if($coupon->total_usage_limit)
                                                    <div class="text-xs text-gray-500">de {{ $coupon->total_usage_limit }} máximo</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($coupon->isValid())
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Activo
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    Inactivo
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <a href="{{ route('coupons.edit', $coupon) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    Editar
                                                </a>
                                                <form method="POST" action="{{ route('coupons.toggle', $coupon) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="text-{{ $coupon->active ? 'yellow' : 'green' }}-600 hover:text-{{ $coupon->active ? 'yellow' : 'green' }}-900">
                                                        {{ $coupon->active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('coupons.destroy', $coupon) }}"
                                                      class="inline" 
                                                      onsubmit="return confirm('¿Estás seguro de que quieres eliminar este cupón?')">
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
                                                @svg('heroicon-o-ticket', 'w-12 h-12 text-gray-400 mb-2')
                                                <p>No hay cupones configurados</p>
                                                <a href="{{ route('coupons.create') }}" 
                                                   class="mt-2 text-indigo-600 hover:text-indigo-700">
                                                    Crear el primero
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($coupons->hasPages())
                        <div class="mt-6">
                            {{ $coupons->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
