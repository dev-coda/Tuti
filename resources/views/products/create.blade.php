@extends('layouts.admin')




@section('content')
{{ Aire::open()->route('products.store')->enctype('multipart/form-data')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear Producto</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">


                {{ Aire::input('name', "Nombre")->groupClass('col-span-3') }}
                 {{ Aire::input('sku', "SKU")->groupClass('col-span-3') }}

                {{ Aire::input('price', "Precio")->groupClass('col-span-3') }}
                {{ Aire::input('delivery_days', "Tiempo de entrega")->helpText('Días')->groupClass('col-span-3') }}

                {{ Aire::input('quantity_min', "Cantidad mínima")->groupClass('col-span-3') }}
                {{ Aire::input('quantity_max', "Cantidad maxima")->helpText('Si esta en cero no hay límite')->groupClass('col-span-3') }}

                
               
                <livewire:select-combined-product :variations='$variations' /> 
                
               


                

                
                {{ Aire::select(['percentage' => 'Porcentaje (%)', 'fixed_amount' => 'Valor Fijo ($)'], 'discount_type', 'Tipo de Descuento')->value('percentage')->groupClass('col-span-2') }}
                {{Aire::input('discount', 'Valor del Descuento')->id('discount')->min(0)->step(0.01)->groupClass('col-span-2')}}

                {{Aire::input('package_quantity', 'Cantidad por Empaque')->id('package_quantity')->groupClass('col-span-2')}}

                {{Aire::input('step', 'Steps')->min(1)->max(100)->groupClass('col-span-6')->helpText('Salto de cantidad para el precio')}}
              
                {{ Aire::input('safety_stock', 'Stock de seguridad')->type('number')->min(0)->helpText('Nivel mínimo de inventario permitido por producto')->groupClass('col-span-3') }}
                
                <div class="col-span-3 flex items-center">
                    {{ Aire::hidden('inventory_opt_out')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name='inventory_opt_out' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Excluir de gestión de inventario</span>
                    </label>
                </div>

                <!-- Rich Text Editors for Product Content -->
                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content=""
                        data-name="description"
                        data-placeholder="Escribe la descripción del producto..."
                        data-height="250px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción corta</label>
                    {{ Aire::textarea('short_description', "")->id('sort_description')->rows(3)->groupClass('mb-0')->helpText('Descripción breve para listados de productos') }}
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ficha técnica</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content=""
                        data-name="technical_specifications"
                        data-placeholder="Especificaciones técnicas del producto..."
                        data-height="200px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Garantía</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content=""
                        data-name="warranty"
                        data-placeholder="Información sobre la garantía del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Otra información</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content=""
                        data-name="other_information"
                        data-placeholder="Información adicional del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div>
                    {{ Aire::hidden('active')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name='active' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900 ">Activo</span>
                    </label>
                </div>
  
            

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                        <a href="{{ route('products.index') }}">Cancelar</a>
                    </p>
                </div>
            </div>


        </div>
    </div>

    <div class="col-span-1">
    
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Marca</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($brands, 'brand_id')}}
            </div>
        </div>
    
        <x-product-categories relation='categories' :items="$categories" title="Categorías" />
        <x-product-attributes relation='labels' :items="$labels" title="Etiquetas" />


        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Impuesto</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($taxes, 'tax_id')}}
            </div>
        </div>

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Bonificacion</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($bonifications, 'bonification_id')}}
            </div>
        </div>

    </div>

   
</div>
{{ Aire::close() }}



@endsection



@section('scripts')

@endsection