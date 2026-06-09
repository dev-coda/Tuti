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

                <div class="col-span-6 border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <h4 class="text-sm font-semibold text-gray-900">Descuentos por marca y proveedor</h4>
                    <p class="mt-1 text-xs text-gray-600 mb-4">Marca o proveedor pueden tener descuentos globales. Activa estas opciones para que <strong>este producto no reciba</strong> ese descuento de marca o de proveedor. El descuento configurado en este producto (tipo y valor más abajo) sigue aplicando con normalidad.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            {{ Aire::hidden('exclude_from_brand_discount')->value(0)}}
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input @checked($product->exclude_from_brand_discount ?? false) type="checkbox" name="exclude_from_brand_discount" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium text-gray-900">Ignorar descuento de marca</span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            {{ Aire::hidden('exclude_from_vendor_discount')->value(0)}}
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input @checked($product->exclude_from_vendor_discount ?? false) type="checkbox" name="exclude_from_vendor_discount" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                <span class="ml-3 text-sm font-medium text-gray-900">Ignorar descuento de proveedor</span>
                            </label>
                        </div>
                    </div>
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

                {{ Aire::input('coordinadora_weight_kg', 'Peso Coordinadora (kg)')->type('number')->step(0.001)->min(0)->groupClass('col-span-3') }}
                {{ Aire::input('coordinadora_height_cm', 'Alto Coordinadora (cm)')->type('number')->step(0.01)->min(0)->groupClass('col-span-1') }}
                {{ Aire::input('coordinadora_width_cm', 'Ancho Coordinadora (cm)')->type('number')->step(0.01)->min(0)->groupClass('col-span-1') }}
                {{ Aire::input('coordinadora_length_cm', 'Largo Coordinadora (cm)')->type('number')->step(0.01)->min(0)->groupClass('col-span-1') }}

                <div class="col-span-3">
                    {{ Aire::hidden('calculate_package_price')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->calculate_package_price) type="checkbox" name='calculate_package_price' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Calcular precio por empaque</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Cuando está desactivado, el empaque se trata como 1 unidad en el procesamiento de órdenes</p>
                </div>

                <div class="col-span-3">
                    {{ Aire::hidden('sync_variations_with_dynamics')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($product->sync_variations_with_dynamics) type="checkbox" name='sync_variations_with_dynamics' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Sincronizar variaciones con Dynamics</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Cuando está activado, las variaciones heredan el precio del producto padre al actualizar desde Dynamics</p>
                </div>
                
                <!-- Rich Text Editors for Product Content -->
                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ $product->description ?? '' }}"
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
                        data-content="{{ $product->technical_specifications ?? '' }}"
                        data-name="technical_specifications"
                        data-placeholder="Especificaciones técnicas del producto..."
                        data-height="200px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Garantía</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ $product->warranty ?? '' }}"
                        data-name="warranty"
                        data-placeholder="Información sobre la garantía del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Otra información</label>
                    <div 
                        class="rich-text-editor-mount" 
                        data-content="{{ $product->other_information ?? '' }}"
                        data-name="other_information"
                        data-placeholder="Información adicional del producto..."
                        data-height="150px"
                    ></div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">PDF de Especificaciones</label>
                    <div class="space-y-3">
                        @if($product->specifications_pdf)
                            <div class="flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-green-600">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-green-800">PDF cargado</p>
                                    <a href="{{ asset('storage/'.$product->specifications_pdf) }}" target="_blank" class="text-xs text-green-600 hover:underline">Ver PDF actual</a>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-red-600 cursor-pointer">
                                    <input type="checkbox" name="remove_specifications_pdf" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span>Eliminar</span>
                                </label>
                            </div>
                        @endif
                        <div>
                            <input type="file" name="specifications_pdf" accept=".pdf,application/pdf" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 p-2">
                            <p class="mt-1 text-xs text-gray-500">{{ $product->specifications_pdf ? 'Cargar un nuevo PDF reemplazará el actual.' : 'Cargar un archivo PDF con las especificaciones del producto.' }} Máximo 10MB.</p>
                        </div>
                    </div>
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
            <h3 class="mb-3 text-lg font-semibold">Inventario del producto padre por bodega</h3>
            @if($product->variation_id)
                <p class="mb-3 text-xs text-gray-500">
                    Este producto tiene variaciones. Si el SKU del padre es dummy, el inventario real puede estar en los SKU de las variaciones.
                </p>
            @endif
            @php $inventories = ($product->inventories ?? collect())->whereNull('variation_item_id'); @endphp
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

            @if($product->variation_id && $product->items->count())
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h4 class="mb-3 text-base font-semibold">Inventario por variación</h4>
                    <div class="space-y-4">
                        @foreach($product->items->where('pivot.enabled', 1)->sortBy('id') as $item)
                            @php
                                $variationSku = $product->selectedVariationSku((int) $item->id);
                                $variationInventories = $product->inventoriesForSelectedVariation((int) $item->id)->orderBy('bodega_code')->get();
                            @endphp
                            <div class="border rounded-lg p-3 bg-gray-50">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 mb-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ $item->name }}</div>
                                        <div class="text-xs text-gray-500">
                                            SKU variación: {{ $variationSku ?: 'Sin SKU' }}
                                        </div>
                                    </div>
                                    @if(! $variationSku)
                                        <span class="text-xs text-orange-700 bg-orange-100 rounded-full px-2 py-1 w-fit">Sin SKU para sincronizar</span>
                                    @endif
                                </div>

                                @if($variationInventories->count() === 0)
                                    <p class="text-sm text-gray-500">Sin registros de inventario para esta variación.</p>
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        @foreach($variationInventories as $inv)
                                            <div class="border rounded-lg p-3 bg-white">
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
                        @endforeach
                    </div>
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
                $enabledVariationItems = $product->items->where('pivot.enabled', 1)->values();
                $imagesPayload = $product->images->map(function ($img) use ($product) {
                    return [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->path),
                        'delete_url' => route('products.images_delete', [$product, $img]),
                        'update_variation_url' => route('products.images_variation', [$product, $img]),
                        'variation_item_id' => $img->variation_item_id,
                    ];
                })->values()->all();
                $variationItemsPayload = $enabledVariationItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                })->values()->all();
            @endphp
            <div
                id="product-image-reorder"
                data-images='@json($imagesPayload)'
                data-variation-items='@json($variationItemsPayload)'
                data-reorder-url="{{ route('products.images_reorder', $product) }}"
            ></div>
        </div>
    </div>

    <div class="col-span-1">

        {{ Aire::open()->route('products.images', $product)->bind($product)->enctype('multipart/form-data')}}
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
                <h3 class="mb-4 text-xl font-semibold ">Imagenes</h3>
                <div class="col-span-2 space-y-3">
                    @php
                        $enabledVariationItems = $product->items->where('pivot.enabled', 1)->values();
                    @endphp
                    @if($product->variation && $enabledVariationItems->count())
                    <div>
                        <label for="variation_item_id" class="block text-sm font-medium text-gray-700 mb-2">Asociar nuevas imágenes a variación (opcional)</label>
                        <select id="variation_item_id" name="variation_item_id" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:outline-none focus:border-orange-500 p-2">
                            <option value="">Sin variación específica</option>
                            @foreach($enabledVariationItems as $variationItem)
                                <option value="{{ $variationItem->id }}">
                                    {{ $variationItem->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Las imágenes ya cargadas se asignan en la cuadrícula de la izquierda. Aquí puedes elegir la variación al subir imágenes nuevas.</p>
                    </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Seleccione una o más imágenes</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 p-2">
                        <p class="mt-1 text-xs text-gray-500">Puede seleccionar múltiples archivos (máx. 4MB cada uno)</p>
                    </div>
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