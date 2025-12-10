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

<div class="grid grid-cols-1 w-full gap-y-5 gap-x-5 xl:px-60" x-data="{'isModalOpen': false}" x-on:keydown.escape="isModalOpen=false">


    @if($client)
    {{ Aire::open()->route('seller.removeclient')}}
    <div class="border rounded p-5">
        <div class="flex justify-between">
            <strong>{{$client->name}}</strong>

            <button class="text-slate-500 hover:text-red-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </button>
        </div>
    </div>
    {{ Aire::close() }}
    @endif

    <div class="">

        @if($alertVendors)
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    @foreach ($alertVendors as $alert)
                    const message{{ $loop->index }} = 'El vendor <strong>{{$alert->name}}</strong> requiere una compra mínima de <strong>${{currency($alert->minimum_purchase)}}</strong> para realizar el pedido. Compra <strong>${{currency($alert->minimum_purchase - $alert->current)}}</strong> más para completar esta compra.';
                    if (window.showToast) {
                        window.showToast(message{{ $loop->index }}, 'error', 8000);
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: message{{ $loop->index }}, type: 'error', duration: 8000 }
                        }));
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
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: discountMessage{{ $loop->index }}, type: 'info', duration: 8000 }
                        }));
                    }
                    @endforeach
                }, 700);
            });
        </script>
        @endif

        <div class="">

            @if($alertTotal)
            <script>
                window.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        const totalMessage = 'El valor de compra mínima es de <strong>${{currency($min_amount)}}</strong>.';
                        if (window.showToast) {
                            window.showToast(totalMessage, 'error', 8000);
                        } else {
                            window.dispatchEvent(new CustomEvent('toast:show', {
                                detail: { message: totalMessage, type: 'error', duration: 8000 }
                            }));
                        }
                    }, 900);
                });
            </script>
            @endif  

        <div class="border rounded p-5">
            <div>
                {{ Aire::open()->route('cart.update')}}

                <h3 class="mb-4">Productos</h3>
                <div class="xl:text-base text-sm space-y-5">
                    @foreach ($products as $key => $product)
                    <div class="grid grid-cols-12 items-top gap-x-2">
                        <a href="{{route('product', $product->slug)}}" class="col-span-2 xl:flex hidden">
                            <img src="{{asset('storage/'.$product->image)}}" alt="">
                        </a>
                        <div class="col-span-4 xl:px-3 px-0 flex flex-col">
                            <a href='{{route('product', $product->slug)}}'>{{$product->name}} </a>
                            <div>
                                <small class="text-slate-700">${{currency($product->calculatedFinalPrice['old'])}}</small>
                                @if($product->variation)
                                <small class="text-slate-700">{{$product->variation->name}} {{$product->item->name}}</small>
                                @endif
                            </div>
                        </div>
                        <div class="xl:col-span-3 col-span-5 px-3 flex flex-col">
                            {{-- <input type="text" value="{{$product->quantity}}" class="shadow-sm bg-gray-50 border border-gray-300 text-gray-900 text-sm focus:ring-primary-500 focus:border-primary-500 block w-full p-2 text-center rounded-sm"> --}}
                            <div class=" py-1 flex items-center border border-qgray-border">
                                <div class="flex justify-between items-center w-full">
                                    <button data-step="{{$product->step}}" type="button" class="increment text-blue1 text-3xl text-qgray w-10">-</button>
                                    <input type="numeric" readonly name='items[]' class="quantity w-10 text-center bg-transparent border-0 text-sm focus:ring-0 focus:outline-none" value="{{$product->quantity}}">
                                    <button data-step="{{$product->step}}" type="button" class="decrement text-blue1 text-3xl  w-10">+</button>
                                </div>
                            </div>

                        </div>
                        <div class="col-span-2 text-right">
                            @if($has_orders)
                            <strong class="">${{currency($product->calculatedFinalPrice['old'] * $product->quantity)}}</strong>
                            @else
                            <strong class="">${{currency($product->calculatedFinalPrice['price'] * $product->quantity)}}</strong>
                            @endif
                            @if($product->calculatedFinalPrice['has_discount'] && !$has_orders)
                            <small class="line-through">${{currency($product->calculatedFinalPrice['old'] * $product->quantity)}}</small>
                            @endif
                        </div>
                        <div class="items-start justify-center flex">
                            <a href={{route('cart.remove', $key)}} class='hover:text-red-500 text-slate-400 mt-1'>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-end my-5">
                    <button type="submit" class="bg-orange-600  text-white rounded py-1.5 px-3 text-sm block text-center hover:bg-orange-900">Actualizar</button>
                </div>

                {{ Aire::close() }}

                <!-- Coupon Section -->
                <div class="border-t pt-4 mt-4">
                    @if($appliedCoupon)
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-green-800 font-medium">Cupón aplicado: {{$appliedCoupon['coupon_code']}}</span>
                                    <div class="text-sm text-green-600">
                                        Descuento: ${{currency($appliedCoupon['discount_amount'])}}
                                    </div>
                                </div>
                                <form action="{{route('cart.coupon.remove')}}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                        Remover
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="mb-4">
                            <form action="{{route('cart.coupon.apply')}}" method="POST">
                                @csrf
                                <div class="flex gap-2">
                                    <input type="text" name="coupon_code" placeholder="Código de cupón" 
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <button type="submit" 
                                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
                                        Aplicar
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="text-sm">
                    <hr class="my-4">

                    @php
                    // Calculate subtotal using the original prices
                    $subtotal = $products->sum(function($product){
                        return $product->calculatedFinalPrice['old'] * $product->quantity;
                    });

                    // Calculate the total after all discounts are applied
                    // (this is what the user will actually pay)
                    $totalAfterDiscounts = $products->sum(function($product){
                        return $product->calculatedFinalPrice['price'] * $product->quantity;
                    });

                    // For display purposes:
                    // - If no coupon: show all discounts in the "Descuento" line
                    // - If coupon: the coupon is shown separately, so "Descuento" shows non-coupon discounts
                    // But since calculatedFinalPrice already includes all discounts (including coupon),
                    // we just calculate the total discount as the difference
                    if(!$has_orders)
                    {
                        // If there's a coupon, we want to show it separately
                        // So we calculate: discount = (subtotal - totalAfterDiscounts) - couponDiscount
                        // This way "Descuento" shows regular discounts, and coupon shows separately
                        $totalDiscount = $subtotal - $totalAfterDiscounts;
                        $couponDiscountAmount = $appliedCoupon ? ($appliedCoupon['discount_amount'] ?? 0) : 0;
                        
                        // If there's a coupon, subtract it from total discount to avoid double-counting
                        // since we'll show it on a separate line
                        $discount = $appliedCoupon ? ($totalDiscount - $couponDiscountAmount) : $totalDiscount;
                    }
                    else{
                        $discount = 0;
                    }
                    @endphp

                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <strong>
                            ${{currency($subtotal)}}
                        </strong>
                    </div>
                    @if($discount)
                    <div class="flex justify-between">
                        <span>Descuento</span>
                        <strong>
                            -${{currency($discount)}}
                        </strong>
                    </div>
                    @endif
                    @if($appliedCoupon && $appliedCoupon['discount_amount'] > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Descuento cupón ({{$appliedCoupon['coupon_code']}})</span>
                        <strong>
                            -${{currency($appliedCoupon['discount_amount'])}}
                        </strong>
                    </div>
                    @endif
                    <hr class="my-4">
                    @php
                        // The final total is already calculated with all discounts applied
                        $finalTotal = $totalAfterDiscounts;
                    @endphp
                    <div class="flex justify-between">
                        <strong>Total</strong>
                        <strong>${{currency($finalTotal)}}</strong>
                    </div>


                    @if($alertVendors || $alertTotal)
                        <div id="submit-order-button">
                            <submit-order-button :disabled="true"></submit-order-button>
                        </div>
                    @else 
                        {{ Aire::open()->route('cart.process')}}

                            <div class="pt-5 space-y-4">
                                {{ Aire::select($zones, 'zone_id', 'Dirección')->id('states')->value(session('zone_id'))}}

                                <!-- Delivery Method Selection - Elegant Toggle -->
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">
                                        Elige un método de entrega
                                    </label>
                                    <div class="flex flex-col md:flex-row gap-3">
                                        <!-- Tronex Option -->
                                        <button type="button" 
                                            class="delivery-option flex-1 p-4 rounded-lg border-2 transition-all duration-300 flex items-start space-x-3 w-full"
                                            data-method="tronex"
                                            id="delivery-option-tronex">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center delivery-icon-bg">
                                                    <svg class="w-6 h-6 delivery-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1 text-left">
                                                <div class="font-semibold text-lg delivery-title">Vendedor Tronex</div>
                                                <div class="text-sm delivery-subtitle mt-1">Entrega durante la visita</div>
                                                <div class="text-xs delivery-date mt-1" id="delivery-date-tronex">Calculando...</div>
                                            </div>
                                        </button>

                                        <!-- Express Option -->
                                        <button type="button" 
                                            class="delivery-option flex-1 p-4 rounded-lg border-2 transition-all duration-300 flex items-start space-x-3 w-full"
                                            data-method="express"
                                            id="delivery-option-express">
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center delivery-icon-bg">
                                                    <svg class="w-6 h-6 delivery-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1 text-left">
                                                <div class="font-semibold text-lg delivery-title">Entrega en 48h</div>
                                                <div class="text-sm delivery-subtitle mt-1">Compra mínima $80.000</div>
                                                <div class="text-xs delivery-date mt-1" id="delivery-date-express">Calculando...</div>
                                            </div>
                                        </button>
                                    </div>
                                    <!-- Hidden input for form submission -->
                                    <input type="hidden" name="delivery_method" id="delivery_method" value="tronex">
                                </div>

                                {{Aire::textarea('observations', 'Observaciones')->placeholder('Información adicional')->rows(3)}}
                            </div>

                            <div id="submit-order-button">
                                <submit-order-button></submit-order-button>
                            </div>
                        {{ Aire::close() }}
                        
                    @endif

                </div>



            </div>
        </div>
    </div>


</div>
@endif






@endsection


@section('scripts')

<script>
    $(function() {



        const step = 1;

        $('.increment').on('click', function() {
            const step = $(this).data('step')
            const form = $(this).parent()
            const quantityInput = form.find('.quantity')

            let quantity = parseInt(quantityInput.val())
            quantity = quantity - step
            if (quantity < step) {
                quantity = step
            }
            quantityInput.val(quantity)
        })

        $('.decrement').on('click', function() {
            const step = $(this).data('step')
            const form = $(this).parent()
            const quantityInput = form.find('.quantity')

            let quantity = parseInt(quantityInput.val())
            console.log(quantity)
            quantity = quantity + step
            quantityInput.val(quantity)
        })

        // Delivery method toggle handler
        const deliveryOptions = document.querySelectorAll('.delivery-option');
        const deliveryMethodInput = document.getElementById('delivery_method');
        const zoneSelect = document.getElementById('states');
        
        function updateDeliveryOption(method) {
            // Update hidden input
            if (deliveryMethodInput) {
                deliveryMethodInput.value = method;
            }
            
            // Update UI for both options
            deliveryOptions.forEach(option => {
                const optionMethod = option.getAttribute('data-method');
                const isActive = optionMethod === method;
                
                if (isActive) {
                    // Active state - orange background with white text
                    option.classList.remove('border-gray-300', 'bg-gray-50');
                    option.classList.add('border-orange-500', 'bg-orange-500', 'shadow-md');
                    
                    // Update icon
                    const iconBg = option.querySelector('.delivery-icon-bg');
                    const icon = option.querySelector('.delivery-icon');
                    if (iconBg && icon) {
                        iconBg.classList.remove('border-gray-400', 'bg-white');
                        iconBg.classList.add('border-orange-500', 'bg-white');
                        icon.classList.remove('text-gray-600');
                        icon.classList.add('text-orange-600');
                    }
                    
                    // Update text colors
                    const title = option.querySelector('.delivery-title');
                    const subtitle = option.querySelector('.delivery-subtitle');
                    const date = option.querySelector('.delivery-date');
                    if (title) {
                        title.classList.remove('text-gray-700');
                        title.classList.add('text-white');
                    }
                    if (subtitle) {
                        subtitle.classList.remove('text-gray-500');
                        subtitle.classList.add('text-white');
                    }
                    if (date) {
                        date.classList.remove('text-gray-400');
                        date.classList.add('text-white');
                    }
                } else {
                    // Inactive state - gray
                    option.classList.remove('border-orange-500', 'bg-orange-500', 'bg-orange-50', 'shadow-md');
                    option.classList.add('border-gray-300', 'bg-gray-50');
                    
                    // Update icon
                    const iconBg = option.querySelector('.delivery-icon-bg');
                    const icon = option.querySelector('.delivery-icon');
                    if (iconBg && icon) {
                        iconBg.classList.remove('border-orange-500', 'bg-white');
                        iconBg.classList.add('border-gray-400', 'bg-white');
                        icon.classList.remove('text-orange-600');
                        icon.classList.add('text-gray-600');
                    }
                    
                    // Update text colors
                    const title = option.querySelector('.delivery-title');
                    const subtitle = option.querySelector('.delivery-subtitle');
                    const date = option.querySelector('.delivery-date');
                    if (title) {
                        title.classList.remove('text-white');
                        title.classList.add('text-gray-700');
                    }
                    if (subtitle) {
                        subtitle.classList.remove('text-white');
                        subtitle.classList.add('text-gray-500');
                    }
                    if (date) {
                        date.classList.remove('text-white');
                        date.classList.add('text-gray-400');
                    }
                }
            });
            
            // Fetch delivery date for selected method
            fetchDeliveryDate(method);
        }
        
        function fetchDeliveryDate(method) {
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
        
        // Add click handlers to delivery options
        deliveryOptions.forEach(option => {
            option.addEventListener('click', function() {
                const method = this.getAttribute('data-method');
                updateDeliveryOption(method);
            });
        });
        
        // Update delivery date when zone changes
        if (zoneSelect) {
            zoneSelect.addEventListener('change', function() {
                const currentMethod = deliveryMethodInput ? deliveryMethodInput.value : 'tronex';
                fetchDeliveryDate(currentMethod);
            });
        }
        
        // Initialize with default method (tronex)
        updateDeliveryOption('tronex');
        
        // Also fetch delivery date for express method immediately
        fetchDeliveryDate('express');
    })
</script>


@endsection