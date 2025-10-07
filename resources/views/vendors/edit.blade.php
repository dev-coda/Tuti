@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('vendors.update', $vendor)->bind($vendor)->enctype('multipart/form-data')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar vendor</h1>
    </div>
    <!-- Right Content -->
   
    <div class="col-span-2">

       
        
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
                <h3 class="mb-4 text-xl font-semibold ">Información</h3>
                

                <div class="grid grid-cols-6 gap-6">
                    {{ Aire::input('name', "Nombre")->placeholder('Nombre')->groupClass('col-span-6 sm:col-span-3') }}
                    {{ Aire::input('slug', "Slug")->placeholder('Slug')->groupClass('col-span-6 sm:col-span-3') }}
                    {{ Aire::input('vendor_type', "Vendor Type")->groupClass('col-span-3') }}
                    {{ Aire::number('minimum_purchase', "Compra mínima")->placeholder('Compra mínima')->groupClass('col-span-3') }}
                    {{ Aire::number('minimum_discount_amount', "Mínimo para descuento")->placeholder('Monto mínimo para aplicar descuento')->helpText('Monto mínimo en productos de este vendor para aplicar el descuento')->groupClass('col-span-6') }}
                    {{ Aire::select(['percentage' => 'Porcentaje (%)', 'fixed_amount' => 'Valor Fijo ($)'], 'discount_type', 'Tipo de Descuento')->groupClass('col-span-3') }}
                    {{  Aire::input('discount', 'Valor del Descuento')->id('discount')->min(0)->step(0.01)->groupClass('col-span-3')}}

                    <div class="col-span-4 flex items-center">
                        {{ Aire::hidden('first_purchase_only')->value(0)}}
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input @checked($vendor->first_purchase_only) type="checkbox" name='first_purchase_only' value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-900">Aplicar solo en primera compra</span>
                        </label>
                    </div>

                    <div>
                        {{ Aire::hidden('active')->value(0)}}
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input @checked($vendor->active) type="checkbox" name='active' value="1" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-900 ">Activo</span>
                        </label>
                    </div>

                </div>
    
            </div>

            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
                <h3 class="mb-4 text-xl font-semibold ">Imágenes</h3>
                

                <div class="grid grid-cols-2 gap-5">
                    <div class="flex flex-col justify-end">
                        <div class="mb-3 text-center">
                            <img src="{{ asset('storage/'.$vendor->image) }}" alt="" class="w-48 inline-block">
                        </div>
          
                        {{ Aire::file('image_file', 'Imagen') }}
                    </div>
    
                    <div class="flex  flex-col justify-end">
                        <div class="mb-3 text-center">
                            <img src="{{ asset('storage/'.$vendor->banner) }}" alt="" class="w-48 inline-block">
                        </div>
                        {{ Aire::file('banner_file', 'Banner') }}
                    </div>
                </div>
            </div>


            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            
                <div class="grid grid-cols-6 gap-6">         
                    <div class="col-span-6 justify-between  items-center  space-x-2 flex">

                        <p class="flex space-x-2 items-center">
                            {{ Aire::submit('Actualizar')->variant()->submit() }}
                            <a href="{{ route('vendors.index') }}">Cancelar</a>
                        </p>


                        <x-remove-button />  
                    
                    </div>
                </div>

                
            </div>
     
    </div>
    
   
</div>
{{ Aire::close() }}


<x-remove-drawer title="Vendor" route='vendors.destroy' :item='$vendor' />


@endsection
