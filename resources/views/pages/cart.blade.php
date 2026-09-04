@extends('layouts.page')


@section('head')
@include('elements.seo', [
'title'=>'Carrito de compras',
'description'=> 'Carrito de compras'
])
@endsection



@section('content')

@php
    $isForceEnabled = $isForceEnabled ?? \App\Models\Setting::isForceDeliveryDateEnabled(
        isset($client) && $client?->city_id
            ? (int) $client->city_id
            : (auth()->user()?->city_id ? (int) auth()->user()->city_id : null)
    );
    $isEnabled = \App\Models\Setting::isExpress48hEnabled();
@endphp

@if($set_user)
<div class="grid grid-cols-1 w-full gap-y-5 gap-x-5 xl:px-72" x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">


    <div class="border rounded p-5 mt-5">
        <div>
            {{ Aire::open()->route('seller.setclient')}}
            
                <div class='grid grid-cols-1 gap-5'>
            
                    {{ Aire::input('document', 'Documento Cliente: Escribe el NIT sin dígito de verificación')->groupClass('mb-0') }} 
                    <p class="text-sm text-gray-500 -mt-2">La zona se asigna automaticamente usando la zona del vendedor.</p>
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

    @if(session('coupon_removed_message'))
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                if (window.showToast) {
                    window.showToast(@json(session('coupon_removed_message')), 'error', 6000);
                }
            }, 600);
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
                     data-unit-price="{{ $product->calculatedFinalPrice['price'] }}"
                     data-old-price="{{ $product->calculatedFinalPrice['old'] }}"
                     data-has-discount="{{ $product->calculatedFinalPrice['has_discount'] ? '1' : '0' }}"
                     data-tax-pct="{{ optional($product->tax)->tax ?? 0 }}">
                    
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
                                <span class="item-price font-semibold text-gray-900">${{currency($product->calculatedFinalPrice['price'] * $product->quantity)}}</span>
                                @if($product->calculatedFinalPrice['has_discount'])
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
                            <span class="item-price font-semibold text-gray-900">${{currency($product->calculatedFinalPrice['price'] * $product->quantity)}}</span>
                            @if($product->calculatedFinalPrice['has_discount'])
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

        @if(!empty($bonificationPreview))
        <div class="px-6 py-4 bg-orange-50 border-t border-orange-100">
            <h3 class="text-sm font-semibold text-orange-800 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3v1H7a2 2 0 00-2 2v3h14v-3a2 2 0 00-2-2h-2v-1c0-1.657-1.343-3-3-3z"></path>
                </svg>
                Bonificaciones aplicables
            </h3>
            <div class="space-y-2">
                @foreach($bonificationPreview as $preview)
                <div class="p-3 bg-white border border-orange-200 rounded-lg">
                    <p class="text-sm text-gray-800">
                        <span class="font-semibold">{{ $preview['bonification_name'] }}</span>:
                        @if(!empty($preview['gift_product_name']))
                            obtienes <span class="font-semibold">{{ $preview['gift_quantity'] }}</span>
                            {{ $preview['gift_quantity'] === 1 ? 'unidad' : 'unidades' }} de
                            <span class="font-semibold">{{ $preview['gift_product_name'] }}</span>.
                        @else
                            obtienes <span class="font-semibold">{{ $preview['gift_quantity'] }}</span>
                            {{ $preview['gift_quantity'] === 1 ? 'unidad' : 'unidades' }} de obsequio.
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Producto que activa: {{ $preview['trigger_product_name'] }}.
                        Regla: compra {{ $preview['buy'] }}, recibe {{ $preview['get'] }}.
                        Cantidad acumulada en carrito: {{ $preview['aggregated_items'] }}.
                    </p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Coupon Section --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Cupón de descuento
            </h3>
            
            @if($appliedCoupon && !empty($appliedCoupon['coupons']))
                {{-- Show each applied coupon with individual remove button --}}
                <div class="space-y-2 mb-3">
                    @foreach($appliedCoupon['coupons'] as $couponEntry)
                    <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-green-800 font-semibold text-sm">{{$couponEntry['coupon_code']}}</span>
                            </div>
                            <form action="{{route('cart.coupon.remove')}}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="coupon_code" value="{{$couponEntry['coupon_code']}}">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium hover:underline">
                                    Remover
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                    <div class="text-sm text-green-700 font-medium text-right">
                        Total descuento por cupones: -${{currency($appliedCoupon['discount_amount'])}}
                    </div>
                </div>
            @elseif($appliedCoupon)
                {{-- Legacy single coupon display --}}
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg mb-3">
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
            @endif
            {{-- Always show the coupon input form to allow adding more coupons --}}
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
        </div>

        {{-- Totals Section --}}
        <div class="px-6 py-5 bg-gray-50 border-t border-gray-200 rounded-b-xl" id="cart-totals-section"
             data-coupon-discount="{{ $appliedCoupon ? ($appliedCoupon['discount_amount'] ?? 0) : 0 }}"
             @if(!empty($cartRetentions['rules_for_js']))
             data-retention-rules="{{ e(json_encode($cartRetentions['rules_for_js'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) }}"
             @endif>
            @php
            $subtotal = $products->sum(function($product){
                return $product->calculatedFinalPrice['old'] * $product->quantity;
            });

            $totalAfterDiscounts = $products->sum(function($product){
                return $product->calculatedFinalPrice['price'] * $product->quantity;
            });

            $totalDiscount = $subtotal - $totalAfterDiscounts;
            $couponDiscountAmount = $appliedCoupon ? ($appliedCoupon['discount_amount'] ?? 0) : 0;
            $discount = max(0, $totalDiscount - $couponDiscountAmount);
            $shippingAmount = 0;
            $finalTotal = $totalAfterDiscounts + $shippingAmount;
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

                @if(!empty($cartRetentions['rules_for_js']))
                <div class="text-xs text-gray-500 -mt-1 mb-1">
                    Retenciones ({{ $cartRetentions['tax_group'] }}) — estimado según reglas activas
                </div>
                <div class="flex justify-between text-amber-800 {{ ($cartRetentions['retention_fuente'] ?? 0) > 0 ? '' : 'hidden' }}" id="cart-retention-fuente-row">
                    <span>Retención en la fuente</span>
                    <span class="font-medium" id="cart-retention-fuente">-${{currency($cartRetentions['retention_fuente'] ?? 0)}}</span>
                </div>
                <div class="flex justify-between text-amber-800 {{ ($cartRetentions['retention_iva'] ?? 0) > 0 ? '' : 'hidden' }}" id="cart-retention-iva-row">
                    <span>Retención de IVA</span>
                    <span class="font-medium" id="cart-retention-iva">-${{currency($cartRetentions['retention_iva'] ?? 0)}}</span>
                </div>
                @endif
                
                <div class="pt-3 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-bold text-gray-900">Total productos</span>
                        <span class="text-2xl font-bold text-orange-600" id="cart-total">${{currency($finalTotal)}}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">El flete y el total a pagar se confirman al elegir el método de envío.</p>
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
                Resumen del pedido
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
                        <input type="hidden" name="sucursal_uid" id="checkout-sucursal-uid" value="">
                        <input type="hidden" name="sucursal_code" id="checkout-sucursal-code" value="">
                        <input type="hidden" name="sucursal_zone_id" id="checkout-sucursal-zone-id" value="">
                        <input type="hidden" name="sucursal_route" id="checkout-sucursal-route" value="">
                        <input type="hidden" name="sucursal_zone" id="checkout-sucursal-zone" value="">
                        <input type="hidden" name="sucursal_day" id="checkout-sucursal-day" value="">
                        <input type="hidden" name="sucursal_address" id="checkout-sucursal-address" value="">
                        <select name="zone_id" id="states" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-700 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all">
                            @foreach($zoneOptions as $z)
                                <option
                                    value="{{ $z->id }}"
                                    data-sucursal-uid="{{ $z->sucursal_uid ?? '' }}"
                                    data-sucursal-code="{{ $z->code ?? '' }}"
                                    data-sucursal-route="{{ $z->route ?? '' }}"
                                    data-sucursal-zone="{{ $z->zone ?? '' }}"
                                    data-sucursal-day="{{ $z->day ?? '' }}"
                                    data-sucursal-address="{{ $z->address ?? '' }}"
                                    data-shipping-standard="{{ ($z->shipping_standard_enabled ?? true) ? '1' : '0' }}"
                                    data-shipping-express="{{ ($z->shipping_express_enabled ?? true) ? '1' : '0' }}"
                                    {{ (int) session('zone_id') === (int) $z->id ? 'selected' : '' }}
                                >{{ $z->address }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Delivery Method Selection --}}
                    @if($shippingMethods->isNotEmpty())
                    <div>
                        <label class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <svg class="h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Método de envío
                        </label>
                        @php
                            $expressFreeShippingMin = \App\Models\Setting::expressFreeShippingMinimum();
                            $expressFreeShippingEnabled = \App\Models\Setting::isExpressFreeShippingEnabled();
                            $freeShippingMessage = \App\Models\Setting::getByKey('free_shipping_message');
                        @endphp
                        <div class="grid grid-cols-1 gap-3 {{ $shippingMethods->count() >= 2 ? 'md:grid-cols-2' : '' }}" id="delivery-options-grid">
                            @foreach($shippingMethods as $method)
                            @php
                                $isExpress = $method->code === 'express';
                                $displayName = $isExpress ? 'Entrega Especial' : 'Entrega Standard';
                                $displayDescription = $isExpress
                                    ? 'Realiza tu pedido de lunes a viernes antes de las 5:00 pm para recibir en 48 horas. Sábado y domingo realiza pedido las 24 horas y recíbelo en 48 horas del siguiente día hábil. Aplica para ciudades principales.'
                                    : 'Envío gratis.';
                            @endphp
                            <button type="button"
                                class="delivery-option relative w-full rounded-xl border-2 border-gray-200 bg-white p-4 text-left transition-all duration-200 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-orange-500"
                                data-method="{{ $method->code }}"
                                id="delivery-option-{{ $method->code }}"
                                aria-pressed="false">
                                <div class="flex items-start gap-4" style="padding-right: 2rem;">
                                    <span class="delivery-icon-bg flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full border-2 border-gray-300 bg-gray-50 text-gray-500" style="margin-top: 2px;">
                                        @if($isExpress)
                                            <svg class="delivery-icon block h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        @else
                                            <svg class="delivery-icon block h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="delivery-title block text-base font-bold leading-tight text-gray-800">{{ $displayName }}</span>
                                        <span class="delivery-subtitle mt-1 block text-sm leading-snug text-gray-500">{{ $displayDescription }}</span>
                                        @if($isExpress && $expressFreeShippingEnabled && $expressFreeShippingMin > 0)
                                            <span class="mt-1 block text-xs text-green-700">
                                                Envío gratis desde ${{ number_format($expressFreeShippingMin, 0, ',', '.') }}
                                            </span>
                                        @elseif(!$isExpress && filled($freeShippingMessage))
                                            <span class="mt-1 block text-xs text-green-700">{{ $freeShippingMessage }}</span>
                                        @endif
                                        @if(!$isForceEnabled)
                                            <span class="delivery-date mt-2 block text-xs font-medium text-gray-400">
                                                Fecha de entrega:
                                                <span id="delivery-date-{{ $method->code }}">Calculando...</span>
                                            </span>
                                        @endif
                                    </span>
                                </div>
                                <span class="delivery-check pointer-events-none absolute hidden h-5 w-5 items-center justify-center rounded-full border-2 border-gray-300 bg-white" style="top: 0.75rem; right: 0.75rem;" aria-hidden="true">
                                    <svg class="block h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                                </span>
                            </button>
                            @endforeach
                        </div>
                        <p id="express-bonification-block" class="mt-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3 {{ !empty($cartHasBonifications) ? '' : 'hidden' }}">
                            {{ \App\Services\BonificationCheckoutService::expressBlockedByBonificationsMessage() }}
                        </p>
                        @if(!empty($expressVisibilityDebug) && auth()->user()?->hasRole('admin'))
                            <details class="mt-3 rounded-lg border border-slate-300 bg-slate-50 p-3 text-xs text-slate-800">
                                <summary class="cursor-pointer font-semibold text-slate-900">
                                    Admin · diagnóstico Entrega Especial
                                    ({{ $expressVisibilityDebug['visible'] ? 'VISIBLE' : 'OCULTO' }}
                                    @if($expressVisibilityDebug['city_name'])
                                        · {{ $expressVisibilityDebug['city_name'] }}
                                    @endif)
                                </summary>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach($expressVisibilityDebug['checks'] as $check)
                                        <li>
                                            <span class="font-mono">{{ $check['ok'] ? '[OK]' : '[FAIL]' }}</span>
                                            <strong>{{ $check['label'] }}</strong>
                                            — {{ $check['detail'] }}
                                            <span class="text-slate-400">({{ $check['key'] }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        <input type="hidden" name="delivery_method" id="delivery_method" value="{{ $shippingMethods->first()->code ?? 'tronex' }}">
                        <input type="hidden" name="shipping_quote_amount" id="shipping_quote_amount" value="0">
                    </div>
                    @else
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-800">No hay métodos de envío disponibles en este momento. Por favor contacta al administrador.</p>
                    </div>
                    @endif

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

                    {{-- Payable total (includes freight after method selection) --}}
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3" id="checkout-payable-section">
                        <div class="flex justify-between text-gray-700" id="cart-shipping-row">
                            <span id="cart-shipping-label">Flete</span>
                            <span class="font-medium text-right" id="cart-shipping">
                                <span class="line-through text-gray-400" id="cart-shipping-struck">$0</span>
                                <span class="ml-2 font-semibold text-green-700" id="cart-shipping-gratis">GRATIS</span>
                            </span>
                        </div>
                        <p class="text-xs text-red-600 hidden" id="cart-shipping-error"></p>
                        <div class="pt-3 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold uppercase tracking-wide text-gray-900">Total a pagar</span>
                                <span class="text-2xl font-bold text-orange-600" id="checkout-total-payable">${{currency($finalTotal)}}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <div class="pt-2">
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
        let currentShippingAmount = 0;
        let shippingQuoteRequestId = 0;
        let shippingQuoteAbortController = null;
        // Currency formatter
        function formatCurrency(amount) {
            return '$' + new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(amount));
        }

        function roundMoney(n) {
            return Math.round(n * 100) / 100;
        }

        function calcReteFuenteSubtotal(subtotal, rule) {
            if (!rule || rule.pct_rte_fuente <= 0 || rule.base_rte_fuente <= 0) return 0;
            if (subtotal < rule.base_rte_fuente) return 0;
            return roundMoney((subtotal * rule.pct_rte_fuente) / 100);
        }

        function calcReteIvaAmount(ivaAmount, rule) {
            if (!rule || rule.pct_rte_iva <= 0 || rule.base_rte_iva <= 0) return 0;
            if (ivaAmount < rule.base_rte_iva) return 0;
            return roundMoney((ivaAmount * rule.pct_rte_iva) / 100);
        }

        function updateCartRetentions() {
            const totalsSection = document.getElementById('cart-totals-section');
            if (!totalsSection || !totalsSection.dataset.retentionRules) return;
            let rules;
            try {
                rules = JSON.parse(totalsSection.dataset.retentionRules);
            } catch (e) {
                return;
            }
            let baseArt = 0;
            let ivaArt = 0;
            document.querySelectorAll('.cart-item').forEach((item) => {
                const unit = parseFloat(item.dataset.unitPrice);
                const taxPct = parseFloat(item.dataset.taxPct || 0);
                const qtyInput = item.querySelector('.qty-input');
                const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 0) : 0;
                const lineTotal = unit * qty;
                if (taxPct > 0) {
                    const base = lineTotal / (1 + taxPct / 100);
                    ivaArt += lineTotal - base;
                    baseArt += base;
                } else {
                    baseArt += lineTotal;
                }
            });
            const shipPct = rules.shipping_iva_percent || 19;
            let baseFlete = 0;
            let ivaFlete = 0;
            if (currentShippingAmount > 0) {
                if (shipPct > 0) {
                    baseFlete = currentShippingAmount / (1 + shipPct / 100);
                    ivaFlete = currentShippingAmount - baseFlete;
                } else {
                    baseFlete = currentShippingAmount;
                }
            }
            const art = rules.articulo;
            const fl = rules.flete;
            const reteFuenteArt = art ? calcReteFuenteSubtotal(baseArt, art) : 0;
            const reteIvaArt = art ? calcReteIvaAmount(ivaArt, art) : 0;
            const reteFuenteFl = fl ? calcReteFuenteSubtotal(baseFlete, fl) : 0;
            const reteIvaFl = fl ? calcReteIvaAmount(ivaFlete, fl) : 0;
            const totalFuente = roundMoney(reteFuenteArt + reteFuenteFl);
            const totalIva = roundMoney(reteIvaArt + reteIvaFl);

            const rowFuente = document.getElementById('cart-retention-fuente-row');
            const elFuente = document.getElementById('cart-retention-fuente');
            const rowIva = document.getElementById('cart-retention-iva-row');
            const elIva = document.getElementById('cart-retention-iva');

            if (rowFuente && elFuente) {
                if (totalFuente > 0) {
                    rowFuente.classList.remove('hidden');
                    elFuente.textContent = '-' + formatCurrency(totalFuente);
                } else {
                    rowFuente.classList.add('hidden');
                }
            }
            if (rowIva && elIva) {
                if (totalIva > 0) {
                    rowIva.classList.remove('hidden');
                    elIva.textContent = '-' + formatCurrency(totalIva);
                } else {
                    rowIva.classList.add('hidden');
                }
            }
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

                    if (data.coupon_removed && window.showToast) {
                        window.showToast(data.coupon_removed_message || 'El cupón fue removido del carrito.', 'error', 6000);
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        // Reload the page to refresh alerts and enable address picker
                        // This ensures vendor minimum alerts are recalculated
                        window.location.reload();
                    }
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
            let totalWithoutShipping = 0;

            document.querySelectorAll('.cart-item').forEach(item => {
                const oldPrice = parseFloat(item.dataset.oldPrice);
                const unitPrice = parseFloat(item.dataset.unitPrice);
                const qty = parseInt(item.querySelector('.qty-input').value);
                
                subtotal += oldPrice * qty;
                totalWithoutShipping += unitPrice * qty;
            });

            const allDiscounts = subtotal - totalWithoutShipping;
            const couponDiscountAmount = parseFloat(
                document.getElementById('cart-totals-section').dataset.couponDiscount || 0
            );
            const nonCouponDiscount = Math.max(0, allDiscounts - couponDiscountAmount);

            // Update subtotal
            const subtotalEl = document.getElementById('cart-subtotal');
            if (subtotalEl) {
                subtotalEl.textContent = formatCurrency(subtotal);
            }

            // Update non-coupon discount (show/hide row); coupon has its own server-rendered row
            const discountRow = document.getElementById('cart-discount-row');
            const discountEl = document.getElementById('cart-discount');
            if (discountRow && discountEl) {
                if (nonCouponDiscount > 0) {
                    discountRow.classList.remove('hidden');
                    discountEl.textContent = '-' + formatCurrency(nonCouponDiscount);
                } else {
                    discountRow.classList.add('hidden');
                }
            }

            // Update merchandise total (no freight)
            const totalEl = document.getElementById('cart-total');
            if (totalEl) {
                totalEl.textContent = formatCurrency(totalWithoutShipping);
            }

            const payableEl = document.getElementById('checkout-total-payable');
            if (payableEl) {
                payableEl.textContent = formatCurrency(totalWithoutShipping + currentShippingAmount);
            }

            updateCartRetentions();
        }

        function renderShippingAmountDisplay(options = {}) {
            const shippingAmountEl = document.getElementById('cart-shipping');
            const shippingLabelEl = document.getElementById('cart-shipping-label');
            const loading = options.loading === true;
            const freeShipping = options.freeShipping === true || currentShippingAmount <= 0;
            const quoted = Number(options.quotedShippingCost || 0);

            if (shippingLabelEl) {
                if (loading) {
                    shippingLabelEl.textContent = 'Flete (cotizando…)';
                } else {
                    shippingLabelEl.textContent = 'Flete';
                }
            }

            if (!shippingAmountEl) {
                return;
            }

            if (loading && currentShippingAmount <= 0 && !options.freeShipping) {
                shippingAmountEl.innerHTML = '<span class="text-gray-500">…</span>';
                return;
            }

            if (freeShipping) {
                const struckValue = quoted > 0 ? quoted : 0;
                shippingAmountEl.innerHTML =
                    '<span class="line-through text-gray-400">' + formatCurrency(struckValue) + '</span>' +
                    '<span class="ml-2 font-semibold text-green-700">GRATIS</span>';
                return;
            }

            shippingAmountEl.innerHTML = '<span class="font-medium text-gray-900">' + formatCurrency(currentShippingAmount) + '</span>';
        }

        function setShippingAmount(amount, options = {}) {
            currentShippingAmount = Number(amount || 0);
            const shippingInput = document.getElementById('shipping_quote_amount');
            const shippingErrorEl = document.getElementById('cart-shipping-error');

            if (shippingInput) {
                shippingInput.value = currentShippingAmount.toFixed(2);
            }

            renderShippingAmountDisplay(options);

            if (shippingErrorEl) {
                if (options.error) {
                    shippingErrorEl.textContent = options.error;
                    shippingErrorEl.classList.remove('hidden');
                } else {
                    shippingErrorEl.textContent = '';
                    shippingErrorEl.classList.add('hidden');
                }
            }

            recalculateTotals();
        }

        function getCartMerchandiseTotal() {
            let totalWithoutShipping = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const unitPrice = parseFloat(item.dataset.unitPrice);
                const quantityInput = item.querySelector('.qty-input');
                const quantity = quantityInput ? parseInt(quantityInput.value) : 0;
                totalWithoutShipping += unitPrice * quantity;
            });
            return Math.max(0, totalWithoutShipping);
        }

        function fetchShippingQuote(method) {
            const zoneId = zoneSelect ? zoneSelect.value : null;
            shippingQuoteRequestId += 1;
            const requestId = shippingQuoteRequestId;

            if (shippingQuoteAbortController) {
                shippingQuoteAbortController.abort();
            }
            shippingQuoteAbortController = (typeof AbortController !== 'undefined')
                ? new AbortController()
                : null;

            if (!zoneId || method !== 'express') {
                setShippingAmount(0, { freeShipping: true, quotedShippingCost: 0 });
                return;
            }

            // Keep the shipping row visible while quoting so totals never
            // silently drop the estimated cost when express is selected.
            setShippingAmount(currentShippingAmount, { forceShow: true, loading: true });

            const merchandiseTotal = getCartMerchandiseTotal();
            const url = `/api/shipping-quote/${method}?zone_id=${zoneId}&merchandise_total=${encodeURIComponent(merchandiseTotal.toFixed(2))}`;

            fetch(url, shippingQuoteAbortController ? { signal: shippingQuoteAbortController.signal } : undefined)
                .then(response => response.json().then(data => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (requestId !== shippingQuoteRequestId) {
                        return;
                    }

                    if (!ok || !data.success) {
                        setShippingAmount(0, {
                            forceShow: true,
                            error: data.message || 'No se pudo cotizar el envío 48H. Revisa la zona o intenta de nuevo.',
                        });
                        return;
                    }

                    setShippingAmount(Number(data.shipping_cost || 0), {
                        forceShow: true,
                        freeShipping: !!data.free_shipping_applied,
                        quotedShippingCost: Number(data.quoted_shipping_cost || data.shipping_cost || 0),
                    });
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    if (requestId !== shippingQuoteRequestId) {
                        return;
                    }
                    setShippingAmount(0, {
                        forceShow: true,
                        error: 'No se pudo cotizar el envío 48H. Revisa la zona o intenta de nuevo.',
                    });
                });
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
        const sucursalUidInput = document.getElementById('checkout-sucursal-uid');
        const sucursalCodeInput = document.getElementById('checkout-sucursal-code');
        const sucursalZoneIdInput = document.getElementById('checkout-sucursal-zone-id');
        const sucursalRouteInput = document.getElementById('checkout-sucursal-route');
        const sucursalZoneInput = document.getElementById('checkout-sucursal-zone');
        const sucursalDayInput = document.getElementById('checkout-sucursal-day');
        const sucursalAddressInput = document.getElementById('checkout-sucursal-address');

        function syncCheckoutSucursalCode() {
            if (!zoneSelect || !sucursalCodeInput) {
                return;
            }
            const opt = zoneSelect.options[zoneSelect.selectedIndex];
            if (sucursalUidInput) {
                sucursalUidInput.value = opt ? (opt.getAttribute('data-sucursal-uid') || '') : '';
            }
            sucursalCodeInput.value = opt ? (opt.getAttribute('data-sucursal-code') || '') : '';
            if (sucursalZoneIdInput) {
                sucursalZoneIdInput.value = zoneSelect.value || '';
            }
            if (sucursalRouteInput) {
                sucursalRouteInput.value = opt ? (opt.getAttribute('data-sucursal-route') || '') : '';
            }
            if (sucursalZoneInput) {
                sucursalZoneInput.value = opt ? (opt.getAttribute('data-sucursal-zone') || '') : '';
            }
            if (sucursalDayInput) {
                sucursalDayInput.value = opt ? (opt.getAttribute('data-sucursal-day') || '') : '';
            }
            if (sucursalAddressInput) {
                sucursalAddressInput.value = opt ? (opt.getAttribute('data-sucursal-address') || '') : '';
            }
        }

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
                    option.setAttribute('aria-pressed', 'true');
                    
                    if (iconBg) {
                        iconBg.classList.remove('border-gray-300', 'bg-gray-50', 'text-gray-500');
                        iconBg.classList.add('border-orange-500', 'bg-orange-500', 'text-white');
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
                        check.classList.remove('hidden', 'border-gray-300', 'bg-white');
                        check.classList.add('flex', 'border-orange-500', 'bg-orange-500');
                    }
                } else {
                    option.classList.remove('border-orange-500', 'bg-orange-50');
                    option.classList.add('border-gray-200', 'bg-white', 'hover:border-gray-300');
                    option.setAttribute('aria-pressed', 'false');
                    
                    if (iconBg) {
                        iconBg.classList.remove('border-orange-500', 'bg-orange-500', 'text-white');
                        iconBg.classList.add('border-gray-300', 'bg-gray-50', 'text-gray-500');
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
                        check.classList.add('hidden', 'border-gray-300', 'bg-white');
                    }
                }
            });
            
            fetchDeliveryDate(method);
            fetchShippingQuote(method);
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
        
        function getSelectedZoneShippingFlags() {
            const cityFlags = @json($cityShippingFlags ?? ['standard' => true, 'express' => true]);
            const cartHasBonifications = {{ !empty($cartHasBonifications) ? 'true' : 'false' }};
            if (!zoneSelect) {
                return {
                    standard: cityFlags.standard !== false,
                    express: cityFlags.express !== false && !cartHasBonifications,
                };
            }
            const opt = zoneSelect.options[zoneSelect.selectedIndex];
            return {
                standard: (!opt || opt.getAttribute('data-shipping-standard') !== '0') && cityFlags.standard !== false,
                express: (!opt || opt.getAttribute('data-shipping-express') !== '0') && cityFlags.express !== false && !cartHasBonifications,
            };
        }

        function syncZoneShippingMethods(preferredMethod) {
            const flags = getSelectedZoneShippingFlags();
            const available = [];
            const cartHasBonifications = {{ !empty($cartHasBonifications) ? 'true' : 'false' }};

            deliveryOptions.forEach((option) => {
                const code = option.getAttribute('data-method');
                const allowed = code === 'express' ? flags.express : flags.standard;
                option.classList.toggle('hidden', !allowed);
                option.disabled = !allowed;
                if (allowed) {
                    available.push(code);
                }
            });

            const bonifMsg = document.getElementById('express-bonification-block');
            if (bonifMsg) {
                bonifMsg.classList.toggle('hidden', !cartHasBonifications);
            }

            const grid = document.getElementById('delivery-options-grid');
            let emptyMsg = document.getElementById('delivery-options-empty');
            if (available.length === 0) {
                if (!emptyMsg && grid) {
                    emptyMsg = document.createElement('p');
                    emptyMsg.id = 'delivery-options-empty';
                    emptyMsg.className = 'text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3';
                    emptyMsg.textContent = 'No hay métodos de envío habilitados para esta ciudad o dirección.';
                    grid.parentNode.insertBefore(emptyMsg, grid.nextSibling);
                }
                if (emptyMsg) {
                    emptyMsg.classList.remove('hidden');
                }
                if (deliveryMethodInput) {
                    deliveryMethodInput.value = '';
                }
                setShippingAmount(0, { freeShipping: true });
                return available;
            }

            if (emptyMsg) {
                emptyMsg.classList.add('hidden');
            }

            const current = preferredMethod || (deliveryMethodInput ? deliveryMethodInput.value : null);
            const next = available.includes(current) ? current : available[0];
            updateDeliveryOption(next);
            return available;
        }

        deliveryOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.disabled || this.classList.contains('hidden')) {
                    return;
                }
                const method = this.getAttribute('data-method');
                updateDeliveryOption(method);
            });
        });
        
        if (zoneSelect) {
            zoneSelect.addEventListener('change', function() {
                syncCheckoutSucursalCode();
                syncZoneShippingMethods();
            });
        }

        syncCheckoutSucursalCode();

        const shippingMethodCodes = @json($shippingMethods->pluck('code')->values()->all());

        // Initialize (only methods shown in checkout — no hard-coded express when 48h is off)
        if (shippingMethodCodes.length) {
            syncZoneShippingMethods(shippingMethodCodes[0]);
            shippingMethodCodes.forEach(function(code) {
                fetchDeliveryDate(code);
            });
        } else {
            setShippingAmount(0, { freeShipping: true });
        }
    })
</script>


@endsection
