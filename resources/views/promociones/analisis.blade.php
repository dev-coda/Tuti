@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Análisis de Promociones</h1>
                    <p class="text-gray-600">Analiza el rendimiento de descuentos, bonificaciones, cupones y promociones</p>
                </div>

                <!-- Date Range Filter -->
                <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <form method="GET" action="{{ route('promociones.analisis') }}" class="flex items-end space-x-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                            <input type="date" name="start_date" id="start_date" 
                                   value="{{ $startDate->format('Y-m-d') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha Fin</label>
                            <input type="date" name="end_date" id="end_date" 
                                   value="{{ $endDate->format('Y-m-d') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Filtrar
                        </button>
                    </form>
                </div>

                <!-- Summary Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-blue-600">Total Descuentos</p>
                                <p class="text-2xl font-semibold text-blue-900">${{ number_format($totalDiscountAmount, 2) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-green-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-green-600">Órdenes con Descuento</p>
                                <p class="text-2xl font-semibold text-green-900">{{ $totalOrders }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-purple-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-purple-600">Usuarios Únicos</p>
                                <p class="text-2xl font-semibold text-purple-900">{{ $totalUsers }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-orange-50 p-6 rounded-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-orange-600">Promedio por Orden</p>
                                <p class="text-2xl font-semibold text-orange-900">${{ number_format($averageDiscountPerOrder, 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount Types Breakdown -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Descuentos por Tipo</h3>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Descuento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Descontado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($totalDiscountsByType as $discountType)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        @switch($discountType->discount_type)
                                            @case('product')
                                                Descuento de Producto
                                                @break
                                            @case('brand')
                                                Descuento de Marca
                                                @break
                                            @case('vendor')
                                                Descuento de Proveedor
                                                @break
                                            @case('coupon')
                                                Cupón
                                                @break
                                            @case('promocion')
                                                Promoción
                                                @break
                                            @case('volume_discount')
                                                Descuento por Volumen
                                                @break
                                            @case('bonification')
                                                Bonificación
                                                @break
                                            @default
                                                {{ ucfirst($discountType->discount_type) }}
                                        @endswitch
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $discountType->count }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($discountType->total_amount, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($discountType->total_amount / $discountType->count, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No hay datos de descuentos para el período seleccionado</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Performing Discounts -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Descuentos Más Efectivos</h3>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usos</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Descontado</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($topPerformingDiscounts as $discount)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $discount->discount_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @switch($discount->discount_type)
                                            @case('product')
                                                Producto
                                                @break
                                            @case('brand')
                                                Marca
                                                @break
                                            @case('vendor')
                                                Proveedor
                                                @break
                                            @case('coupon')
                                                Cupón
                                                @break
                                            @case('promocion')
                                                Promoción
                                                @break
                                            @case('volume_discount')
                                                Volumen
                                                @break
                                            @case('bonification')
                                                Bonificación
                                                @break
                                            @default
                                                {{ ucfirst($discount->discount_type) }}
                                        @endswitch
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $discount->usage_count }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${{ number_format($discount->total_discount, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No hay datos de descuentos para el período seleccionado</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activity Log -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
                    <div class="bg-white rounded-lg shadow">
                        <div class="max-h-96 overflow-y-auto">
                            @forelse($recentApplications as $application)
                            <div class="px-6 py-4 border-b border-gray-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            @switch($application->discount_type)
                                                @case('product')
                                                    Descuento de Producto
                                                    @break
                                                @case('brand')
                                                    Descuento de Marca
                                                    @break
                                                @case('vendor')
                                                    Descuento de Proveedor
                                                    @break
                                                @case('coupon')
                                                    Cupón aplicado
                                                    @break
                                                @case('promocion')
                                                    Promoción aplicada
                                                    @break
                                                @case('volume_discount')
                                                    Descuento por Volumen
                                                    @break
                                                @case('bonification')
                                                    Bonificación aplicada
                                                    @break
                                                @default
                                                    Descuento aplicado
                                            @endswitch
                                        </p>
                                        <p class="text-sm text-gray-500">{{ $application->discount_name }} - Orden #{{ $application->order_id }}</p>
                                        <p class="text-xs text-gray-400">Usuario: {{ $application->user->email ?? 'N/A' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-500">${{ number_format($application->discount_amount, 2) }}</p>
                                        <p class="text-xs text-gray-400">{{ $application->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="px-6 py-4 text-center text-sm text-gray-500">
                                No hay actividad reciente para el período seleccionado
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection