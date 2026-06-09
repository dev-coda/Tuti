
    
  
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            


            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Variaciones - {{ $product->variation->name }}</h1>

            <div class="relative overflow-x-auto mt-5">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3"></th>
                            <th scope="col" class="px-4 py-3">Nombre</th>
                            <th scope="col" class="px-4 py-3 ">Precio</th>
                            <th scope="col" class="px-4 py-3">SKU</th>
                            <th scope="col" class="px-4 py-3">Inventario</th>
                            <th scope="col" class="px-4 py-3">Imagen</th>
                            
                
                            <th scope="col" class="px-4 py-3">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->items as $item)
                        <tr class="border-b">
                            <td class="w-4 px-4 py-3 ">
                                <div class="flex items-center">
                                    {{ Aire::hidden("variations[".$item->pivot->variation_item_id."][enabled]")->value(0)}}
                                    <input
                                        @checked($item->pivot->enabled) name='{{ "variations[".$item->pivot->variation_item_id."][enabled]" }}' type="checkbox" value='1'  class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600 focus:ring-primary-500">
                                    <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
                                </div>
                            </td>
                            <td scope="row" class="px-4 py-3 font-medium text-gray-900">
                                {{ $item->name }}   
                            </td>

                          
                            <td class="px-4 py-3 max-w-[5rem]">
                                {{ Aire::input("variations[".$item->pivot->variation_item_id."][price]")
                                    ->value(old("variations[".$item->pivot->variation_item_id."][price]", $item->pivot->price))
                                    ->groupClass('mb-0')
                                }}
                            </td>

                               <td class="px-4 py-3 max-w-[5rem]">
                                <div class="flex items-center">

                                    {{ Aire::input("variations[".$item->pivot->variation_item_id."][sku]")
                                    ->value(old("variations[".$item->pivot->variation_item_id."][sku]", $item->pivot->sku))
                                    ->groupClass('mb-0')
                                }}
                                
                                </div>
                            </td>

                            <td class="px-4 py-3 min-w-[16rem]">
                                @php
                                    $variationSku = $product->selectedVariationSku((int) $item->id);
                                    $variationInventories = $product->inventoriesForSelectedVariation((int) $item->id)->orderBy('bodega_code')->get();
                                @endphp

                                @if(! $variationSku)
                                    <span class="inline-flex text-xs text-orange-700 bg-orange-100 rounded-full px-2 py-1">Sin SKU para sincronizar</span>
                                @elseif($variationInventories->count() === 0)
                                    <span class="inline-flex text-xs text-gray-600 bg-gray-100 rounded-full px-2 py-1">Sin inventario sincronizado</span>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($variationInventories as $inv)
                                            <div class="rounded border border-gray-200 bg-white px-2 py-1 text-xs">
                                                <div class="font-semibold text-gray-900">{{ $inv->bodega_code }}</div>
                                                <div class="text-green-700">Disp: {{ (int) $inv->available }}</div>
                                                <div class="text-blue-700">Fis: {{ (int) $inv->physical }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @php
                                    $variationImages = $product->images
                                        ->where('variation_item_id', $item->id)
                                        ->sortBy('position');
                                    $primaryVariationImage = $variationImages->first();
                                @endphp
                                @if($primaryVariationImage)
                                    <div class="flex items-center gap-2">
                                        <img
                                            src="{{ asset('storage/'.$primaryVariationImage->path) }}"
                                            alt="{{ $item->name }}"
                                            class="w-12 h-12 object-contain rounded border border-gray-200 bg-white"
                                        >
                                        @if($variationImages->count() > 1)
                                            <span class="text-xs text-gray-500">+{{ $variationImages->count() - 1 }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Sin imagen</span>
                                @endif
                            </td>

                        
                        </tr>
                        @endforeach
                        
                    </tbody>
                </table>
            </div> 
        
        </div>
   

