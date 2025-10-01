@extends('layouts.admin')



@section('content')


{{ Aire::open()->route('products.update', $product)->bind($product)->enctype('multipart/form-data')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class=" flex justify-between items-center ">
            <h1 class=" font-semibold text-xl text-gray-900 sm:text-2xl">{{ $product->name }}</h1>

            <a class="flex items-center space-x-2 hover:text-blue-500" target="_blank" href="{{ route('product', $product->slug) }}">
                <span>Ver</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>  
            </a>
        </div>
    </div>

  

    <div class="col-span-2">


        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">


                {{ Aire::input('name', "Nombre")->groupClass('col-span-3') }}
                {{ Aire::input('slug', "Slug")->groupClass('col-span-3') }}
                 {{ Aire::input('sku', "SKU")->groupClass('col-span-3') }}

                 @php
                    $discount_on =  $product->finalPrice['discount_on'];
                    $discount =  $product->finalPrice['discount'];

                 @endphp
                {{ Aire::input('price', "Precio")->groupClass('col-span-3')->helpText(
                    $discount_on ? "Descuento del {$discount}% aplicado en {$discount_on}" : "Sin descuento"
                    ) }}

                {{ Aire::input('delivery_days', "Tiempo de entrega")->helpText('Días')->groupClass('col-span-3') }}

                {{ Aire::input('quantity_min', "Cantidad mínima")->groupClass('col-span-3') }}
                {{ Aire::input('quantity_max', "Cantidad maxima")->helpText('Si esta en cero no hay límite')->groupClass('col-span-3') }}

                {{ Aire::input('safety_stock', 'Stock de seguridad')->type('number')->min(0)->helpText('Nivel mínimo de inventario permitido por producto')->groupClass('col-span-3') }}
                
                <div class="col-span-3 flex items-center">
                    {{ Aire::hidden('inventory_opt_out')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->inventory_opt_out) type="checkbox" name='inventory_opt_out' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Excluir de gestión de inventario</span>
                    </label>
                </div>

                @if(!$product->is_combined)
                    {{Aire::select($variations, 'variation_id', "Variación")->groupClass('col-span-3')}}
                @endif

                {{ Aire::select(['percentage' => 'Porcentaje (%)', 'fixed_amount' => 'Valor Fijo ($)'], 'discount_type', 'Tipo de Descuento')->groupClass('col-span-2') }}
                
                {{Aire::input('discount', 'Valor del Descuento')->id('discount')->min(0)->step(0.01)->groupClass('col-span-2')}}

                <div class="col-span-2 flex items-center">
                    {{ Aire::hidden('first_purchase_only')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->first_purchase_only) type="checkbox" name='first_purchase_only' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Aplicar solo en primera compra</span>
                    </label>
                </div>

                {{Aire::input('package_quantity', 'Cantidad por Empaque')->id('package_quantity')->groupClass('col-span-3')}}

                {{Aire::input('step', 'Steps')->min(1)->max(100)->groupClass('col-span-3')->helpText('Salto de cantidad para el precio')}}

                <div class="col-span-3">
                    {{ Aire::hidden('calculate_package_price')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->calculate_package_price) type="checkbox" name='calculate_package_price' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Calcular precio por empaque</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Cuando está desactivado, el empaque se trata como 1 unidad en el procesamiento de órdenes</p>
                </div>
                
                <!-- Rich Text Editors for Product Content -->
                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ htmlspecialchars($product->description ?? '', ENT_QUOTES, 'UTF-8') }}"
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
                        data-content="{{ htmlspecialchars($product->technical_specifications ?? '', ENT_QUOTES, 'UTF-8') }}"
                        data-name="technical_specifications"
                        data-placeholder="Especificaciones técnicas del producto..."
                        data-height="200px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Garantía</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ htmlspecialchars($product->warranty ?? '', ENT_QUOTES, 'UTF-8') }}"
                        data-name="warranty"
                        data-placeholder="Información sobre la garantía del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Otra información</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ htmlspecialchars($product->other_information ?? '', ENT_QUOTES, 'UTF-8') }}"
                        data-name="other_information"
                        data-placeholder="Información adicional del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div>
                    {{ Aire::hidden('active')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->active) type="checkbox" name='active' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900 ">Activo</span>
                    </label>
                </div>
            </div>
        </div>

        @includeWhen($product->variation, 'products.variations', ['product' => $product])

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <div class="col-span-2 justify-between  items-center space-x-2 flex">
                <p class="flex space-x-2 items-center">
                    {{ Aire::submit('Actualizar')->variant()->submit() }}
                    <a href="{{ route('products.index') }}">Cancelar</a>
                </p>
                <x-remove-button />  
            
            </div>
        </div>

        {{-- Inventory overview --}}
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-3 text-lg font-semibold">Inventario por bodega</h3>
            @php $inventories = $product->inventories ?? collect(); @endphp
            @if($inventories->count() === 0)
                <p class="text-sm text-gray-500">Sin registros de inventario.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($inventories as $inv)
                        <div class="border rounded-lg p-3">
                            <div class="text-sm text-gray-500">Bodega</div>
                            <div class="font-semibold">{{ $inv->bodega_code }}</div>
                            <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <div class="text-xs text-gray-500">Disponible</div>
                                    <div class="font-semibold text-green-700">{{ (int) $inv->available }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Reservado</div>
                                    <div class="font-semibold text-orange-700">{{ (int) $inv->reserved }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Físico</div>
                                    <div class="font-semibold text-blue-700">{{ (int) $inv->physical }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <div class="col-span-1">


        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Productos Relacionados</h3>
            <livewire:search-related-product :product="$product" :related='$product->related' /> 
           
        </div>


        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Marca</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($brands, 'brand_id')}}
            </div>
        </div>

                                                         
        <x-product-categories relation='categories' :product="$product" :items="$categories" title="Categorías"  />
        <x-product-attributes relation='labels' :product="$product" :items="$labels" title="Etiquetas" />

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Impuesto</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($taxes, 'tax_id')}}
            </div>
        </div>
        
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Bonificacion</h3>
            <div class="grid grid-cols-1 gap-3">
                {{Aire::select($bonifications, 'bonification_id')->value(old('bonification_id', $product->bonifications->first()?->id))}}
            </div>
        </div>

    </div>

   
</div>
{{ Aire::close() }}





@includeWhen($product->is_combined, 'products.combinations', ['product' => $product, 'products' => $products])


<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Imagenes    </h1>
    </div>

    <div class="col-span-2">
        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden p-4">
            @php
                $imagesPayload = $product->images->map(function ($img) use ($product) {
                    return [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->path),
                        'delete_url' => route('products.images_delete', [$product, $img]),
                    ];
                })->values()->all();
            @endphp
            <div
                id="product-image-reorder"
                data-images='@json($imagesPayload)'
                data-reorder-url="{{ route('products.images_reorder', $product) }}"
            ></div>
        </div>
    </div>

    <div class="col-span-1">

        {{ Aire::open()->route('products.images', $product)->bind($product)->enctype('multipart/form-data')}}
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
                <h3 class="mb-4 text-xl font-semibold ">Imagenes</h3>
                <div class="col-span-2 justify-between  items-center space-x-2 flex">
                    {{Aire::file('image', 'Seleccione una imagen') }}
                    {{ Aire::submit('Agregar')->variant()->submit() }}
                </div>
            </div>
        {{ Aire::close() }}



    </div>
</div>




<x-remove-drawer title="Producti" route='products.destroy' :item='$product' />

@endsection

@section('scripts')
<script defer>
    Livewire.on('postAdded', () => {
        alert('A post was added with the id of: ' + postId);
    })
</script>
@endsection