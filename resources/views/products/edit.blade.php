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

                @if(!$product->is_combined)
                    {{Aire::select($variations, 'variation_id', "Variación")->groupClass('col-span-3')}}
                @endif

                {{Aire::input('discount', 'Descuento %')->id('discount')->min(0)->max(100)->groupClass('col-span-1')}}

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
                

                
                

                {{ Aire::textarea('description', "Descripción")->id('description')->rows(5)->groupClass('col-span-6') }}
                {{ Aire::textarea('short_description', "Descripción corta")->id('sort_description')->rows(5)->groupClass('col-span-6') }}



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

    @if($product->images->count())
    <div class="col-span-2">
        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden p-4">
        
            <div class='grid grid-cols-4 gap-5  '>

                @forelse ($product->images as $image)

                 
                        <div class="flex relative w-full h-48 bg-contain bg-center bg-no-repeat rounded" style="background-image: url({{ asset('storage/'.$image->path) }})">
                            <form action="{{ route('products.images_delete', [$product, $image]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button href="" class='text-red-500 absolute left-1 bottom-1 hover:opacity-50 shadow'>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                                        <path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 13.07a3 3 0 01-2.991 2.77H8.084a3 3 0 01-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 01-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 013.369 0c1.603.051 2.815 1.387 2.815 2.951zm-6.136-1.452a51.196 51.196 0 013.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 00-6 0v-.113c0-.794.609-1.428 1.364-1.452zm-.355 5.945a.75.75 0 10-1.5.058l.347 9a.75.75 0 101.499-.058l-.346-9zm5.48.058a.75.75 0 10-1.498-.058l-.347 9a.75.75 0 001.5.058l.345-9z" clip-rule="evenodd" />
                                      </svg>
                                      
                                    
                                </button>
                            </form>
                        </div>
                 
                    
                @empty
                    
                @endforelse

            </div>
        
        </div>
    </div> 
    @endif

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