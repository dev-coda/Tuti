@extends('layouts.page')


@section('head')
@include('elements.seo', [
'title'=>'Carrito de compras',
'description'=> 'Carrito de compras'
])
@endsection



@section('content')


@if($set_user)
<div class="grid grid-cols-1 w-full gap-y-5 gap-x-5 xl:px-72" x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">


    <div class="border rounded p-5 mt-5">
        <div>
            {{ Aire::open()->route('seller.setclient')}}
            
                <div class='grid grid-cols-1 gap-5'>
            
                    {{ Aire::input('document', 'Documento Cliente: Escribe el NIT sin dígito de verificación')->groupClass('mb-0') }} 
                    {{ Aire::input('zone', 'Zona Sucursal: Diligencia este campo si tu cliente cuenta con varias sucursales')->helpText('*En el siguiente paso verás las direcciones asociadas')->groupClass('mb-0') }} 
                </div>

            <div class="flex items-center  mt-4">
                <x-primary-button>
                    Ingresar
                </x-primary-button>
            </div>
            {{ Aire::close() }}




        </div>
    </div>



</div>
@else

<div class="max-w-5xl mx-auto px-4 py-8" x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">

    {{-- Toast Notifications (via JS) --}}
    @if($alertVendors)
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                @foreach ($alertVendors as $alert)
                const message{{ $loop->index }} = 'El vendor <strong>{{$alert->name}}</strong> requiere una compra mínima de <strong>${{currency($alert->minimum_purchase)}}</strong> para realizar el pedido. Compra <strong>${{currency($alert->minimum_purchase - $alert->current)}}</strong> más para completar esta compra.';
                if (window.showToast) {
                    window.showToast(message{{ $loop->index }}, 'error', 8000);
                }
                @endforeach
            }, 500);
        });
    </script>
    @endif

    @if($vendorDiscountAlerts)
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                @foreach ($vendorDiscountAlerts as $alert)
                const discountMessage{{ $loop->index }} = 'Agrega <strong>${{currency($alert['needed_amount'])}}</strong> en productos <strong>{{$alert['vendor']->name}}</strong> para recibir un descuento de <strong>{{$alert['discount_percentage']}}%</strong>.';
                if (window.showToast) {
                    window.showToast(discountMessage{{ $loop->index }}, 'info', 8000);
                }
                @endforeach
            }, 700);
        });
    </script>
    @endif

    @if($alertTotal)
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const totalMessage = 'El valor de compra mínima es de <strong>${{currency($min_amount)}}</strong>.';
                if (window.showToast) {
                    window.showToast(totalMessage, 'error', 8000);
                }
            }, 900);
        });
    </script>
    @endif

    {{-- Page Title --}}
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Tu Carrito</h1>

    {{-- ============================================= --}}
    {{-- CARD 1: Products, Coupon & Total --}}
    {{-- ============================================= --}}
    <div class="bg-white border-2 border-gray-200 rounded-xl shadow-sm mb-8">
        
        {{-- Products Header --}}
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                Productos
                <span class="text-sm font-normal text-gray-500">({{ count($products) }} {{ count($products) == 1 ? 'artículo' : 'artículos' }})</span>
            </h2>
        </div>

        {{-- Products List --}}
        <div class="px-6 py-4">
            <div class="space-y-4">
                @foreach ($products as $key => $product)
                <div class="cart-item py-4 {{ !$loop->last ? 'border-b border-gray-100' : '' }}" 
                     data-cart-key="{{ $key }}"
                     data-product-id="{{ $product->id }}"
                     data-unit-price="{{ $has_orders ? $product->calculatedFinalPrice['old'] : $product->calculatedFinalPrice['price'] }}"
                     data-old-price="{{ $product->calculatedFinalPrice['old'] }}"
                     data-has-discount="{{ $product->calculatedFinalPrice['has_discount'] && !$has_orders ? '1' : '0' }}">
                    
                    {{-- Mobile Layout --}}
                    <div class="md:hidden space-y-3">
                        {{-- Product Info Row --}}
                        <div class="flex gap-3">
                            <a href="{{route('product', $product->slug)}}" class="flex-shrink-0">
                                <img src="{{asset('storage/'.$product->image)}}" alt="{{ $product->name }}" class="w-16 h-16 object-contain rounded-lg border border-gray-100">
                            </a>
                            <div class="flex-1 min-w-0">
                                <a href='{{route('product', $product->slug)}}' class="font-medium text-sm text-gray-900 hover:text-orange-600 transition-colors block">
                                    {{$product->name}}
                                </a>
                                @if($product->variation)
                                <span class="text-xs text-gray-400 block mt-1">{{$product->variation->name}} {{$product->item->name}}</span>
                                @endif
                                <span class="text-sm text-gray-500 block mt-1">${{currency($product->calculatedFinalPrice['old'])}}</span>
                            </div>
                            {{-- Delete Button --}}
                            <a href={{route('cart.remove', $key)}} class='flex-shrink-0 p-2 h-fit text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all'>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </a>
                        </div>
                        
                        {{-- Quantity & Price Row --}}
                        <div class="flex items-center justify-between">
                            {{-- Quantity Controls --}}
                            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                <button data-step="{{$product->step}}" data-cart-key="{{ $key }}" type="button" class="qty-decrease w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                                </button>
                                <input type="number" 
                                       data-step="{{$product->step}}" 
                                       data-cart-key="{{ $key }}" 
                                       class="qty-input w-12 text-center bg-transparent border-0 text-sm font-medium focus:ring-2 focus:ring-orange-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                       value="{{$product->quantity}}"
                                       min="{{$product->step}}">
                                <button data-step="{{$product->step}}" data-cart-key="{{ $key }}" type="button" class="qty-increase w-9 h-9 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                </button>
                            </div>
                            
                            {{-- Price --}}
                            <div class="text-right">
                                <span class="item-price font-semibold text-gray-900">${{currency(($has_orders ? $product->calculatedFinalPrice['old'] : $product->calculatedFinalPrice['price']) * $product->quantity)}}</span>
                                @if($product->calculatedFinalPrice['has_discount'] && !$has_orders)
                                <span class="item-old-price block text-xs text-gray-400 line-through">${{currency($product->calculatedFinalPrice['old'] * $product->quantity)}}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Desktop Layout --}}
                    <div class="hidden md:flex items-center gap-4">
                        {{-- Product Image --}}
                        <a href="{{route('product', $product->slug)}}" class="flex-shrink-0">
                            <img src="{{asset('storage/'.$product->image)}}" alt="{{ $product->name }}" class="w-20 h-20 object-contain rounded-lg border border-gray-100">
                        </a>
                        
                        {{-- Product Details --}}
                        <div class="flex-1 min-w-0">
                            <a href='{{route('product', $product->slug)}}' class="font-medium text-gray-900 hover:text-orange-600 transition-colors block truncate">
                                {{$product->name}}
                            </a>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-sm text-gray-500">${{currency($product->calculatedFinalPrice['old'])}}</span>
                                @if($product->variation)
                                <span class="text-xs text-gray-400">• {{$product->variation->name}} {{$product->item->name}}</span>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Quantity Controls --}}
                        <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                            <button data-step="{{$product->step}}" data-cart-key="{{ $key }}" type="button" class="qty-decrease w-10 h-10 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                            </button>
                            <input type="number" 
                                   data-step="{{$product->step}}" 
                                   data-cart-key="{{ $key }}" 
                                   class="qty-input w-14 text-center bg-transparent border-0 text-sm font-medium focus:ring-2 focus:ring-orange-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                   value="{{$product->quantity}}"
                                   min="{{$product->step}}">
                            <button data-step="{{$product->step}}" data-cart-key="{{ $key }}" type="button" class="qty-increase w-10 h-10 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </button>
                        </div>
                        
                        {{-- Price --}}
                        <div class="text-right min-w-[100px]">
                            <span class="item-price font-semibold text-gray-900">${{currency(($has_orders ? $product->calculatedFinalPrice['old'] : $product->calculatedFinalPrice['price']) * $product->quantity)}}</span>
                            @if($product->calculatedFinalPrice['has_discount'] && !$has_orders)
                            <span class="item-old-price block text-sm text-gray-400 line-through">${{currency($product->calculatedFinalPrice['old'] * $product->quantity)}}</span>
                            @endif
                        </div>
                        
                        {{-- Delete Button --}}
                        <a href={{route('cart.remove', $key)}} class='flex-shrink-0 p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all'>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Coupon Section --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Cupón de descuento
            </h3>
            
            @if($appliedCoupon)
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <span class="text-green-800 font-semibold block">{{$appliedCoupon['coupon_code']}}</span>
                                <span class="text-sm text-green-600">-${{currency($appliedCoupon['discount_amount'])}} de descuento</span>
                            </div>
                        </div>
                        <form action="{{route('cart.coupon.remove')}}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium hover:underline">
                                Remover
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <form action="{{route('cart.coupon.apply')}}" method="POST">
                    @csrf
                    <div class="flex md:gap-3">
                        <input type="text" name="coupon_code" placeholder="Ingresa tu código de cupón" 
                               class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg md:rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all">
                        <button type="submit" 
                                class="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white font-medium text-sm rounded-r-lg md:rounded-lg transition-colors shadow-sm">
                            Aplicar
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Totals Section --}}
        <div class="px-6 py-5 bg-gray-50 border-t border-gray-200 rounded-b-xl" id="cart-totals-section">
            @php
            $subtotal = $products->sum(function($product){
                return $product->calculatedFinalPrice['old'] * $product->quantity;
            });

            $totalAfterDiscounts = $products->sum(function($product){
                return $product->calculatedFinalPrice['price'] * $product->quantity;
            });

            if(!$has_orders) {
                $totalDiscount = $subtotal - $totalAfterDiscounts;
                $couponDiscountAmount = $appliedCoupon ? ($appliedCoupon['discount_amount'] ?? 0) : 0;
                $discount = $appliedCoupon ? ($totalDiscount - $couponDiscountAmount) : $totalDiscount;
            } else {
                $discount = 0;
            }
            $finalTotal = $totalAfterDiscounts;
            @endphp

            <div class="space-y-3">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span class="font-medium" id="cart-subtotal">${{currency($subtotal)}}</span>
                </div>
                
                <div class="flex justify-between text-green-600 {{ $discount ? '' : 'hidden' }}" id="cart-discount-row">
                    <span>Descuento</span>
                    <span class="font-medium" id="cart-discount">-${{currency($discount)}}</span>
                </div>
                
                @if($appliedCoupon && $appliedCoupon['discount_amount'] > 0)
                <div class="flex justify-between text-green-600">
                    <span>Cupón ({{$appliedCoupon['coupon_code']}})</span>
                    <span class="font-medium">-${{currency($appliedCoupon['discount_amount'])}}</span>
                </div>
                @endif
                
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-900">Total</span>
                        <span class="text-2xl font-bold text-orange-600" id="cart-total">${{currency($finalTotal)}}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================= --}}
    {{-- CARD 2: Order Details & Checkout --}}
    {{-- ============================================= --}}
    <div class="bg-white border-2 border-gray-200 rounded-xl shadow-sm">
        
        {{-- Section Header --}}
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                Detalles del pedido
            </h2>
        </div>

        <div class="p-6">
            {{-- Client Info (if seller with client) --}}
            @if($client)
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="font-semibold text-blue-800 block">Cliente</span>
                            <span class="text-sm text-blue-600">{{$client->name}}</span>
                        </div>
                    </div>
                    {{ Aire::open()->route('seller.removeclient')->class('inline')}}
                    <button type="submit" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Eliminar cliente">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                    {{ Aire::close() }}
                </div>
            </div>
            @endif

            @if($alertVendors || $alertTotal)
                {{-- Disabled state - show message --}}
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600 mb-4">Revisa los mensajes de alerta para poder continuar con tu pedido.</p>
                    <div id="submit-order-button">
                        <submit-order-button :disabled="true"></submit-order-button>
                    </div>
                </div>
            @else 
                {{ Aire::open()->route('cart.process')}}
                
                <div class="space-y-6">
                    {{-- Address Selection --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Dirección de entrega
                        </label>
                        <select name="zone_id" id="states" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all">
                            @foreach($zones as $id => $address)
                                <option value="{{ $id }}" {{ session('zone_id') == $id ? 'selected' : '' }}>{{ $address }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Delivery Method Selection --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Método de entrega
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Tronex Option --}}
                            <button type="button" 
                                class="delivery-option relative p-5 rounded-xl border-2 transition-all duration-300 text-left"
                                data-method="tronex"
                                id="delivery-option-tronex">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center delivery-icon-bg">
                                            <svg class="w-6 h-6 delivery-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-lg delivery-title">Vendedor Tronex</div>
                                        <div class="text-sm delivery-subtitle mt-1">Entrega durante la visita</div>
                                        @php
                                            $forceDeliveryDateEnabled = \App\Models\Setting::getByKey('force_delivery_date_enabled');
                                            $isForceEnabled = ($forceDeliveryDateEnabled === '1' || $forceDeliveryDateEnabled === 1 || $forceDeliveryDateEnabled === true);
                                        @endphp
                                        @if(!$isForceEnabled)
                                        <div class="text-xs delivery-date mt-2 font-medium" id="delivery-date-tronex">Calculando...</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-gray-300 delivery-check hidden items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </div>
                            </button>

                            {{-- Express Option --}}
                            @php
                                $express48hEnabled = \App\Models\Setting::getByKey('express_48h_enabled');
                                $isEnabled = ($express48hEnabled === '1' || $express48hEnabled === 1 || $express48hEnabled === true);
                            @endphp
                            <button type="button" 
                                class="delivery-option relative p-5 rounded-xl border-2 transition-all duration-300 text-left {{ !$isEnabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : '' }}"
                                data-method="express"
                                id="delivery-option-express"
                                {{ !$isEnabled ? 'disabled' : '' }}>
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center delivery-icon-bg {{ !$isEnabled ? 'border-gray-300' : '' }}">
                                            <svg class="w-6 h-6 delivery-icon {{ !$isEnabled ? 'text-gray-400' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-lg delivery-title {{ !$isEnabled ? 'text-gray-400' : '' }}">
                                            Entrega en 48h
                                            @if(!$isEnabled)
                                                <span class="text-xs font-normal text-gray-400">(No disponible)</span>
                                            @endif
                                        </div>
                                        <div class="text-sm delivery-subtitle mt-1 {{ !$isEnabled ? 'text-gray-400' : '' }}">Compra mínima $80.000</div>
                                        @if($isEnabled && !$isForceEnabled)
                                            <div class="text-xs delivery-date mt-2 font-medium" id="delivery-date-express">Calculando...</div>
                                        @endif
                                    </div>
                                </div>
                                @if($isEnabled)
                                <div class="absolute top-3 right-3 w-5 h-5 rounded-full border-2 border-gray-300 delivery-check hidden items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </div>
                                @endif
                            </button>
                        </div>
                        <input type="hidden" name="delivery_method" id="delivery_method" value="tronex">
                    </div>

                    {{-- Observations --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Observaciones
                        </label>
                        <textarea name="observations" rows="3" placeholder="¿Tienes alguna instrucción especial para tu pedido?" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all resize-none"></textarea>
                    </div>

                    {{-- Submit Button --}}
                    <div class="pt-4 border-t border-gray-100">
                        <div id="submit-order-button">
                            <submit-order-button></submit-order-button>
                        </div>
                    </div>
                </div>
                {{ Aire::close() }}
            @endif
        </div>
    </div>

</div>
@endif






@endsection


@section('scripts')

<script>
    $(function() {
        // Currency formatter
        function formatCurrency(amount) {
            return '$' + new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(amount));
        }

        // Debounce function to prevent too many API calls
        let updateTimeout = null;
        function debounceUpdate(callback, delay = 500) {
            if (updateTimeout) clearTimeout(updateTimeout);
            updateTimeout = setTimeout(callback, delay);
        }

        // Update cart item quantity via AJAX
        async function updateCartQuantity(cartKey, newQuantity) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('CSRF token not found');
                return false;
            }

            try {
                const response = await fetch('/cart/update', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken.content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        cart_key: cartKey,
                        quantity: newQuantity
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to update cart');
                }

                const data = await response.json();
                
                if (data.success) {
                    // Dispatch cart updated event for header widget
                    window.dispatchEvent(new CustomEvent('cart:updated'));
                    
                    // Reload the page to refresh alerts and enable address picker
                    // This ensures vendor minimum alerts are recalculated
                    window.location.reload();
                    return true;
                }
                return false;
            } catch (error) {
                console.error('Error updating cart:', error);
                if (window.showToast) {
                    window.showToast('Error al actualizar el carrito', 'error', 3000);
                }
                return false;
            }
        }

        // Update local prices without page refresh
        function updateLocalPrices(cartItem, newQuantity) {
            const unitPrice = parseFloat(cartItem.dataset.unitPrice);
            const oldPrice = parseFloat(cartItem.dataset.oldPrice);
            const hasDiscount = cartItem.dataset.hasDiscount === '1';

            const newItemPrice = unitPrice * newQuantity;
            const newOldPrice = oldPrice * newQuantity;

            // Update item price display
            const priceEl = cartItem.querySelector('.item-price');
            if (priceEl) {
                priceEl.textContent = formatCurrency(newItemPrice);
            }

            // Update strikethrough price if has discount
            if (hasDiscount) {
                const oldPriceEl = cartItem.querySelector('.item-old-price');
                if (oldPriceEl) {
                    oldPriceEl.textContent = formatCurrency(newOldPrice);
                }
            }

            // Recalculate totals
            recalculateTotals();
        }

        // Recalculate all totals
        function recalculateTotals() {
            let subtotal = 0;
            let total = 0;

            document.querySelectorAll('.cart-item').forEach(item => {
                const oldPrice = parseFloat(item.dataset.oldPrice);
                const unitPrice = parseFloat(item.dataset.unitPrice);
                const qty = parseInt(item.querySelector('.qty-input').value);
                
                subtotal += oldPrice * qty;
                total += unitPrice * qty;
            });

            const discount = subtotal - total;

            // Update subtotal
            const subtotalEl = document.getElementById('cart-subtotal');
            if (subtotalEl) {
                subtotalEl.textContent = formatCurrency(subtotal);
            }

            // Update discount (show/hide row)
            const discountRow = document.getElementById('cart-discount-row');
            const discountEl = document.getElementById('cart-discount');
            if (discountRow && discountEl) {
                if (discount > 0) {
                    discountRow.classList.remove('hidden');
                    discountEl.textContent = '-' + formatCurrency(discount);
                } else {
                    discountRow.classList.add('hidden');
                }
            }

            // Update total
            const totalEl = document.getElementById('cart-total');
            if (totalEl) {
                totalEl.textContent = formatCurrency(total);
            }
        }

        // Handle quantity decrease
        $(document).on('click', '.qty-decrease', function() {
            const cartKey = $(this).data('cart-key');
            const step = parseInt($(this).data('step')) || 1;
            const cartItem = $(this).closest('.cart-item');
            const quantityInput = cartItem.find('.qty-input');

            let quantity = parseInt(quantityInput.val());
            quantity = quantity - step;
            if (quantity < step) {
                quantity = step;
            }
            quantityInput.val(quantity);

            // Update local display immediately
            updateLocalPrices(cartItem[0], quantity);

            // Debounce the server update
            debounceUpdate(() => {
                updateCartQuantity(cartKey, quantity);
            });
        });

        // Handle quantity increase
        $(document).on('click', '.qty-increase', function() {
            const cartKey = $(this).data('cart-key');
            const step = parseInt($(this).data('step')) || 1;
            const cartItem = $(this).closest('.cart-item');
            const quantityInput = cartItem.find('.qty-input');

            let quantity = parseInt(quantityInput.val());
            quantity = quantity + step;
            quantityInput.val(quantity);

            // Update local display immediately
            updateLocalPrices(cartItem[0], quantity);

            // Debounce the server update
            debounceUpdate(() => {
                updateCartQuantity(cartKey, quantity);
            });
        });

        // Handle direct input change
        $(document).on('change', '.qty-input', function() {
            const cartKey = $(this).data('cart-key');
            const step = parseInt($(this).data('step')) || 1;
            const cartItem = $(this).closest('.cart-item');

            let quantity = parseInt($(this).val()) || step;
            
            // Ensure quantity is a multiple of step
            if (quantity % step !== 0) {
                quantity = Math.ceil(quantity / step) * step;
            }
            
            // Ensure minimum
            if (quantity < step) {
                quantity = step;
            }
            
            $(this).val(quantity);

            // Update local display immediately
            updateLocalPrices(cartItem[0], quantity);

            // Debounce the server update
            debounceUpdate(() => {
                updateCartQuantity(cartKey, quantity);
            });
        });

        // Delivery method toggle handler
        const deliveryOptions = document.querySelectorAll('.delivery-option');
        const deliveryMethodInput = document.getElementById('delivery_method');
        const zoneSelect = document.getElementById('states');
        
        function updateDeliveryOption(method) {
            if (deliveryMethodInput) {
                deliveryMethodInput.value = method;
            }
            
            deliveryOptions.forEach(option => {
                const optionMethod = option.getAttribute('data-method');
                const isActive = optionMethod === method;
                const iconBg = option.querySelector('.delivery-icon-bg');
                const icon = option.querySelector('.delivery-icon');
                const title = option.querySelector('.delivery-title');
                const subtitle = option.querySelector('.delivery-subtitle');
                const date = option.querySelector('.delivery-date');
                const check = option.querySelector('.delivery-check');
                
                if (isActive) {
                    option.classList.remove('border-gray-200', 'bg-white', 'hover:border-gray-300');
                    option.classList.add('border-orange-500', 'bg-orange-50');
                    
                    if (iconBg) {
                        iconBg.classList.remove('border-gray-300', 'bg-gray-50');
                        iconBg.classList.add('border-orange-500', 'bg-orange-500');
                    }
                    if (icon) {
                        icon.classList.remove('text-gray-500');
                        icon.classList.add('text-white');
                    }
                    if (title) {
                        title.classList.remove('text-gray-800');
                        title.classList.add('text-orange-700');
                    }
                    if (subtitle) {
                        subtitle.classList.remove('text-gray-500');
                        subtitle.classList.add('text-orange-600');
                    }
                    if (date) {
                        date.classList.remove('text-gray-400');
                        date.classList.add('text-orange-600');
                    }
                    if (check) {
                        check.classList.remove('hidden', 'border-gray-300');
                        check.classList.add('flex', 'border-orange-500', 'bg-orange-500');
                    }
                } else {
                    option.classList.remove('border-orange-500', 'bg-orange-50');
                    option.classList.add('border-gray-200', 'bg-white', 'hover:border-gray-300');
                    
                    if (iconBg) {
                        iconBg.classList.remove('border-orange-500', 'bg-orange-500');
                        iconBg.classList.add('border-gray-300', 'bg-gray-50');
                    }
                    if (icon) {
                        icon.classList.remove('text-white');
                        icon.classList.add('text-gray-500');
                    }
                    if (title) {
                        title.classList.remove('text-orange-700');
                        title.classList.add('text-gray-800');
                    }
                    if (subtitle) {
                        subtitle.classList.remove('text-orange-600');
                        subtitle.classList.add('text-gray-500');
                    }
                    if (date) {
                        date.classList.remove('text-orange-600');
                        date.classList.add('text-gray-400');
                    }
                    if (check) {
                        check.classList.remove('flex', 'border-orange-500', 'bg-orange-500');
                        check.classList.add('hidden', 'border-gray-300');
                    }
                }
            });
            
            fetchDeliveryDate(method);
        }
        
        function fetchDeliveryDate(method) {
            // Skip fetching delivery dates if force delivery date is enabled
            const forceDeliveryEnabled = {{ $isForceEnabled ? 'true' : 'false' }};
            if (forceDeliveryEnabled) {
                return; // Don't fetch or display delivery dates when force is active
            }
            
            const zoneId = zoneSelect ? zoneSelect.value : null;
            let url = `/api/delivery-date/${method}`;
            if (zoneId) {
                url += `?zone_id=${zoneId}`;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const dateElement = document.getElementById(`delivery-date-${method}`);
                    if (dateElement && data.date) {
                        dateElement.textContent = data.date;
                    }
                })
                .catch(error => {
                    console.error('Error fetching delivery date:', error);
                    const dateElement = document.getElementById(`delivery-date-${method}`);
                    if (dateElement) {
                        dateElement.textContent = 'Error al calcular fecha';
                    }
                });
        }
        
        deliveryOptions.forEach(option => {
            option.addEventListener('click', function() {
                const method = this.getAttribute('data-method');
                updateDeliveryOption(method);
            });
        });
        
        if (zoneSelect) {
            zoneSelect.addEventListener('change', function() {
                const currentMethod = deliveryMethodInput ? deliveryMethodInput.value : 'tronex';
                fetchDeliveryDate(currentMethod);
            });
        }
        
        // Initialize
        updateDeliveryOption('tronex');
        fetchDeliveryDate('express');
    })
</script>


@endsection
