@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('bonifications.store')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear Bonificación</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">


                {{ Aire::input('name', "Nombre")->groupClass('col-span-6') }}

                {{ Aire::input('buy', "Cantidad comprada")->groupClass('col-span-3') }}

                {{ Aire::input('get', "Cantidad gratis")->groupClass('col-span-3') }}
                
                {{ Aire::select($products, 'product_id', 'Producto')->groupClass('col-span-6 sm:col-span-3') }}

                {{ Aire::input('max', "Cantidad maxima bonificada")->value(old('max',1))->groupClass('col-span-3')->helpText('Cantidad de veces que se da la bonificación') }}

                {{ Aire::input('gift_invoice_unit_value', "Valor mínimo facturable del obsequio (Express)")->groupClass('col-span-3')->helpText('Valor neto por unidad (sin IVA) con el que se factura el obsequio en pedidos Express. Vacío = valor por defecto (' . number_format((float) config('express_gifts.default_min_unit_value'), 2) . ').') }}

                {{ Aire::select(['cart' => 'Todo el carrito sin descuento', 'trigger' => 'Solo los productos que activan esta bonificación'], 'gift_proration_scope', 'Alcance del prorrateo (Express)')->value(old('gift_proration_scope', 'cart'))->groupClass('col-span-6')->helpText('En pedidos Express, el valor del obsequio se descuenta de los productos sin descuento. Elige si ese descuento se reparte en todo el carrito o solo en los productos que activan esta bonificación.') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('allow_discounts', 'Permitir descuentos y cupones')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está habilitado, los descuentos y cupones se aplicarán junto con esta bonificación. 
                            Por defecto, las bonificaciones bloquean todos los descuentos.
                        </span>
                    </div>
                </div>
                
            

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                      
                    </p>

                    <a href="{{ route('bonifications.index') }}">Cancelar</a>
                    
               
                   
                </div>
            </div>


        </div>
    </div>

</div>
{{ Aire::close() }}




@endsection
