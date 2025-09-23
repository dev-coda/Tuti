@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Promociones</h1>
                        <p class="text-gray-600">Gestiona descuentos, bonificaciones, cupones y promociones</p>
                    </div>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-blue-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    @svg('heroicon-o-tag', 'w-6 h-6 text-blue-600')
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-blue-600">Descuentos Directos</p>
                                    <p class="text-2xl font-bold text-blue-900">{{ $brands->where('discount', '>', 0)->count() + $vendors->where('discount', '>', 0)->count() }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-green-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 rounded-lg">
                                    @svg('heroicon-o-shopping-bag', 'w-6 h-6 text-green-600')
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-green-600">Bonificaciones</p>
                                    <p class="text-2xl font-bold text-green-900">{{ $bonifications->count() }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-purple-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-100 rounded-lg">
                                    @svg('heroicon-o-ticket', 'w-6 h-6 text-purple-600')
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-purple-600">Cupones</p>
                                    <p class="text-2xl font-bold text-purple-900">{{ $coupons->count() }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-orange-50 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="p-2 bg-orange-100 rounded-lg">
                                    @svg('heroicon-o-sparkles', 'w-6 h-6 text-orange-600')
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-orange-600">Promociones</p>
                                    <p class="text-2xl font-bold text-orange-900">{{ $promociones->count() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Descuento Directo -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-tag', 'w-8 h-8 text-blue-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Descuento Directo</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Configura descuentos por marca y proveedor</p>
                            <a href="{{ route('promociones.descuento-directo') }}" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                Gestionar
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>

                        <!-- Descuento por Volumen -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-shopping-bag', 'w-8 h-8 text-green-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Descuento por Volumen</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Configura descuentos por cantidad comprada</p>
                            <a href="{{ route('promociones.descuento-volumen') }}" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                Gestionar
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>

                        <!-- Bonificaciones -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-gift', 'w-8 h-8 text-purple-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Bonificaciones</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Gestiona bonificaciones de productos</p>
                            <a href="{{ route('promociones.bonificaciones') }}" 
                               class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                                Gestionar
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>

                        <!-- Cupones -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-ticket', 'w-8 h-8 text-indigo-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Cupones</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Gestiona cupones de descuento</p>
                            <a href="{{ route('promociones.cupones') }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                                Gestionar
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>

                        <!-- Promociones -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-sparkles', 'w-8 h-8 text-orange-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Promociones</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Crea promociones avanzadas con reglas</p>
                            <a href="{{ route('promociones.promociones') }}" 
                               class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                                Gestionar
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>

                        <!-- Análisis -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-4">
                                @svg('heroicon-o-chart-bar', 'w-8 h-8 text-gray-600')
                                <h3 class="text-lg font-semibold text-gray-900 ml-3">Análisis</h3>
                            </div>
                            <p class="text-gray-600 mb-4">Analiza el rendimiento de promociones</p>
                            <a href="{{ route('promociones.analisis') }}" 
                               class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                                Ver Análisis
                                @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
