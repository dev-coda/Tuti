
    
  
        <div class="variation-editor p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <style>
                .variation-editor .variation-sku-field {
                    field-sizing: content;
                    min-width: max(14rem, var(--sku-min, 14rem));
                    height: 2.75rem;
                    white-space: nowrap;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                }
                @media (max-width: 1023px) {
                    .variation-editor table,
                    .variation-editor tbody,
                    .variation-editor tr,
                    .variation-editor td {
                        display: block;
                        width: 100%;
                        min-width: 0 !important;
                        max-width: none;
                    }
                    .variation-editor thead {
                        display: none;
                    }
                    .variation-editor tr {
                        border: 1px solid #e5e7eb;
                        border-radius: 0.5rem;
                        margin-bottom: 0.75rem;
                        padding: 0.75rem 1rem;
                    }
                    .variation-editor td {
                        padding: 0.5rem 0;
                        border: 0;
                    }
                    .variation-editor td[data-label]::before {
                        content: attr(data-label);
                        display: block;
                        margin-bottom: 0.25rem;
                        font-size: 0.75rem;
                        font-weight: 600;
                        letter-spacing: 0.04em;
                        text-transform: uppercase;
                        color: #6b7280;
                    }
                    .variation-editor .variation-sku-field {
                        min-width: 0 !important;
                        width: 100% !important;
                        height: auto;
                        min-height: calc(var(--sku-lines, 1) * 1.25rem + 1.25rem);
                        white-space: pre-wrap;
                        overflow-wrap: anywhere;
                    }
                }
            </style>

            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Variaciones - {{ $product->variation->name }}</h1>

            <div class="relative mt-5 lg:overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3"></th>
                            <th scope="col" class="px-4 py-3">Nombre</th>
                            <th scope="col" class="px-4 py-3 ">Precio</th>
                            <th scope="col" class="px-4 py-3 whitespace-nowrap">SKU</th>
                            <th scope="col" class="px-4 py-3">Inventario</th>
                            <th scope="col" class="px-4 py-3">Imagen</th>
                            
                
                            <th scope="col" class="px-4 py-3">
                                
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($product->items as $item)
                        <tr class="border-b">
                            <td class="w-4 px-4 py-3" data-label="Activa">
                                <div class="flex items-center">
                                    {{ Aire::hidden("variations[".$item->pivot->variation_item_id."][enabled]")->value(0)}}
                                    <input
                                        @checked($item->pivot->enabled) name='{{ "variations[".$item->pivot->variation_item_id."][enabled]" }}' type="checkbox" value='1'  class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600 focus:ring-primary-500">
                                    <label for="checkbox-table-search-1" class="sr-only">checkbox</label>
                                </div>
                            </td>
                            <td scope="row" class="px-4 py-3 font-medium text-gray-900" data-label="Nombre">
                                {{ $item->name }}   
                            </td>

                          
                            <td class="px-4 py-3 lg:max-w-[5rem]" data-label="Precio">
                                {{ Aire::input("variations[".$item->pivot->variation_item_id."][price]")
                                    ->value(old("variations[".$item->pivot->variation_item_id."][price]", $item->pivot->price))
                                    ->groupClass('mb-0')
                                }}
                            </td>

                            @php
                                $skuInputName = 'variations['.$item->pivot->variation_item_id.'][sku]';
                                $skuValue = (string) old($skuInputName, $item->pivot->sku);
                                $skuMinCh = mb_strlen($skuValue) + 4;
                                $skuLines = max(1, (int) ceil(max(1, mb_strlen($skuValue)) / 24));
                            @endphp
                            <td class="px-4 py-3 align-middle" data-label="SKU" style="min-width: calc(max(14rem, {{ $skuMinCh }}ch) + 2rem)">
                                {{ Aire::textarea($skuInputName)
                                    ->value($skuValue)
                                    ->rows($skuLines)
                                    ->groupClass('mb-0')
                                    ->addClass('variation-sku-field')
                                    ->setAttribute('style', '--sku-min: '.$skuMinCh.'ch; --sku-lines: '.$skuLines.';')
                                    ->setAttribute('spellcheck', 'false')
                                    ->setAttribute('autocomplete', 'off')
                                    ->setAttribute('aria-label', 'SKU '.$item->name)
                                    ->setAttribute('title', $skuValue)
                                    ->setAttribute('onkeydown', "if(event.key==='Enter'){event.preventDefault();}")
                                }}
                            </td>

                            <td class="px-4 py-3 lg:min-w-[16rem]" data-label="Inventario">
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

                            <td class="px-4 py-3" data-label="Imagen">
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
   

