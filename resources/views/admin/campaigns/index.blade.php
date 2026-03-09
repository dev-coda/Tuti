@extends('layouts.admin')

@section('title', 'Campañas')

@section('content')

    @if(session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast('{{ session('success') }}', 'success', 5000);
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: '{{ session('success') }}', type: 'success', duration: 5000 }
                        }));
                    }
                }, 100);
            });
        </script>
        <div class="hidden p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast('{{ session('error') }}', 'error', 5000);
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: '{{ session('error') }}', type: 'error', duration: 5000 }
                        }));
                    }
                }, 100);
            });
        </script>
        <div class="hidden p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
        <div class="flex flex-col w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Campañas</h1>
                <p class="text-sm text-gray-600 mt-1">Gestiona y configura todas tus campañas de marketing y promociones</p>
            </div>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Resumen de Campañas</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Coupons Card -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Cupones</h3>
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-ticket', 'w-6 h-6 text-white')
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total:</span>
                        <span class="font-semibold text-gray-900">{{ $stats['coupons']['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Activos:</span>
                        <span class="font-semibold text-green-600">{{ $stats['coupons']['active'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Expirados:</span>
                        <span class="font-semibold text-red-600">{{ $stats['coupons']['expired'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Recientes (7d):</span>
                        <span class="font-semibold text-blue-600">{{ $stats['coupons']['recent'] }}</span>
                    </div>
                </div>
                <a href="{{ route('coupons.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700">
                    Gestionar Cupones
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                </a>
            </div>

            <!-- Promociones Card -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Promociones</h3>
                    <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-sparkles', 'w-6 h-6 text-white')
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total:</span>
                        <span class="font-semibold text-gray-900">{{ $stats['promociones']['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Activas:</span>
                        <span class="font-semibold text-green-600">{{ $stats['promociones']['active'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Expiradas:</span>
                        <span class="font-semibold text-red-600">{{ $stats['promociones']['expired'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Próximas:</span>
                        <span class="font-semibold text-yellow-600">{{ $stats['promociones']['upcoming'] }}</span>
                    </div>
                </div>
                <a href="{{ route('promociones.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-700">
                    Gestionar Promociones
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                </a>
            </div>

            <!-- Upsell Zones Card -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Zonas de Upsell</h3>
                    <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-arrow-trending-up', 'w-6 h-6 text-white')
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total:</span>
                        <span class="font-semibold text-gray-900">{{ $stats['upsell_zones']['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Activas:</span>
                        <span class="font-semibold text-green-600">{{ $stats['upsell_zones']['active'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Con Reglas:</span>
                        <span class="font-semibold text-blue-600">{{ $stats['upsell_zones']['with_rules'] }}</span>
                    </div>
                </div>
                <a href="{{ route('admin.upsell-zones.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-green-600 hover:text-green-700">
                    Gestionar Zonas
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                </a>
            </div>

            <!-- Volume Discounts Card -->
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Descuentos por Volumen</h3>
                    <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center">
                        @svg('heroicon-o-shopping-cart', 'w-6 h-6 text-white')
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total:</span>
                        <span class="font-semibold text-gray-900">{{ $stats['volume_discounts']['total'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Activos:</span>
                        <span class="font-semibold text-green-600">{{ $stats['volume_discounts']['active'] }}</span>
                    </div>
                </div>
                <a href="{{ route('volume-discounts.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-orange-600 hover:text-orange-700">
                    Gestionar Descuentos
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                </a>
            </div>

        </div>
    </div>

    <!-- Campaign Management Links -->
    <div class="p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Gestión de Campañas</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <a href="{{ route('coupons.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-ticket', 'w-6 h-6 text-blue-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Cupones</h3>
                    <p class="text-sm text-gray-600">Gestiona códigos promocionales</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('promociones.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-sparkles', 'w-6 h-6 text-purple-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Promociones</h3>
                    <p class="text-sm text-gray-600">Descuentos y ofertas especiales</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('admin.upsell-zones.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-arrow-trending-up', 'w-6 h-6 text-green-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Upsells/Cross-sells</h3>
                    <p class="text-sm text-gray-600">Zonas y reglas de recomendación</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('admin.upsell-rules.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-cog-6-tooth', 'w-6 h-6 text-indigo-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Reglas de Upsell</h3>
                    <p class="text-sm text-gray-600">Configura reglas de recomendación</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('volume-discounts.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-shopping-cart', 'w-6 h-6 text-orange-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Descuentos por Volumen</h3>
                    <p class="text-sm text-gray-600">Descuentos basados en cantidad</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('featured-products.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-star', 'w-6 h-6 text-yellow-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Productos Destacados</h3>
                    <p class="text-sm text-gray-600">Gestiona productos destacados</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

            <a href="{{ route('banners.index') }}" class="flex items-center p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center mr-4">
                    @svg('heroicon-o-photo', 'w-6 h-6 text-pink-600')
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">Banners</h3>
                    <p class="text-sm text-gray-600">Gestiona banners promocionales</p>
                </div>
                @svg('heroicon-o-chevron-right', 'w-5 h-5 text-gray-400')
            </a>

        </div>
    </div>

    <!-- Campaign Settings -->
    <div class="p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Configuración de Campañas</h2>
        <form action="{{ route('admin.campaigns.settings.update') }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-6">
            @csrf
            
            <div class="space-y-6">
                <!-- Auto Tags Section -->
                <div>
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Etiquetas Automáticas</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex items-start space-x-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="auto_tag_nuevo_enabled" value="0">
                                <input type="checkbox" name="auto_tag_nuevo_enabled" value="1" 
                                       class="sr-only peer" 
                                       @checked(isset($settings['auto_tag_nuevo_enabled']) && $settings['auto_tag_nuevo_enabled']->value == '1')
                                       onchange="this.form.submit()">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <div class="flex-1">
                                <span class="text-sm font-medium text-gray-900">Etiqueta "NUEVO"</span>
                                <p class="text-xs text-gray-500 mt-1">Muestra automáticamente la etiqueta "NUEVO" en productos creados en los últimos 30 días</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="auto_tag_descuento_enabled" value="0">
                                <input type="checkbox" name="auto_tag_descuento_enabled" value="1" 
                                       class="sr-only peer" 
                                       @checked(isset($settings['auto_tag_descuento_enabled']) && $settings['auto_tag_descuento_enabled']->value == '1')
                                       onchange="this.form.submit()">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <div class="flex-1">
                                <span class="text-sm font-medium text-gray-900">Etiqueta "DESCUENTO"</span>
                                <p class="text-xs text-gray-500 mt-1">Muestra automáticamente el porcentaje o monto de descuento estático en productos</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured Products Section -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Productos Destacados</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="use_most_sold_products" class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="use_most_sold_products" value="0">
                                <input type="checkbox" name="use_most_sold_products" value="1" 
                                       class="sr-only peer" 
                                       @checked(isset($settings['use_most_sold_products']) && $settings['use_most_sold_products']->value == '1')
                                       onchange="this.form.submit()">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium text-gray-900">Usar Productos Más Vendidos</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-14">Muestra automáticamente los productos más vendidos en lugar de los destacados manualmente</p>
                        </div>

                        <div>
                            <label for="featured_products_section_title" class="block text-sm font-medium text-gray-700 mb-2">
                                Título de la Sección
                            </label>
                            <input type="text" 
                                   name="featured_products_section_title" 
                                   id="featured_products_section_title"
                                   value="{{ isset($settings['featured_products_section_title']) ? $settings['featured_products_section_title']->value : 'Productos Destacados' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   onblur="this.form.submit()">
                            <p class="text-xs text-gray-500 mt-1">Título que se mostrará en la sección de productos destacados</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Recent Activity -->
    <div class="p-4 bg-white">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Actividad Reciente</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Recent Coupons -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">Cupones Recientes</h3>
                @if($recentCoupons->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentCoupons as $coupon)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $coupon->code }}</p>
                                    <p class="text-xs text-gray-600">{{ $coupon->name }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Creado: {{ $coupon->created_at->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                                <div class="ml-4">
                                    @if($coupon->active)
                                        <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded">Activo</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded">Inactivo</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('coupons.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-700">
                        Ver todos los cupones
                        @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                    </a>
                @else
                    <p class="text-sm text-gray-500">No hay cupones recientes</p>
                @endif
            </div>

            <!-- Recent Promociones -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">Promociones Recientes</h3>
                @if($recentPromociones->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentPromociones as $promocion)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $promocion->name }}</p>
                                    <p class="text-xs text-gray-600">
                                        {{ $promocion->discount_type === 'percentage' ? $promocion->discount_value . '%' : '$' . number_format($promocion->discount_value, 0) }}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Válida: {{ $promocion->valid_from->format('d/m/Y') }} - {{ $promocion->valid_to->format('d/m/Y') }}
                                    </p>
                                </div>
                                <div class="ml-4">
                                    @if($promocion->isActive())
                                        <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded">Activa</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded">Inactiva</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('promociones.index') }}" class="mt-4 inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-700">
                        Ver todas las promociones
                        @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                    </a>
                @else
                    <p class="text-sm text-gray-500">No hay promociones recientes</p>
                @endif
            </div>

        </div>
    </div>

@endsection
