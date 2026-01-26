@props(['product', 'bodegaCode' => null])
@php
    $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
    $showInventory = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
    $vacationInfo = \App\Models\Setting::getVacationModeInfo();
    $isVacationMode = $vacationInfo['active'];
    $formattedVacationDate = $vacationInfo['formatted_date'] ?? 'pronto';
@endphp
<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-3 md:p-4 flex flex-col max-w-[90vw]">
    <div class="flex w-full items-center justify-center py-2 text-gray-400 flex-grow relative">
        @if($product->images->first())
        <a href="{{route('product', $product->slug)}}" class="h-36 md:h-44 block w-full bg-contain bg-center bg-no-repeat hover:scale-105 transition duration-300 cursor-pointer" style="background-image: url({{asset('storage/'.$product->images->first()->path)}});">
        </a>
        <x-product-tag :product="$product" />
        @else
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        @endif
    </div>


    <div class="mt-2 flex flex-col">
        <a href="{{route('product', $product->slug)}}" class="text-gray-800 font-medium text-sm md:text-base leading-tight">{{$product->name}}</a>
        @if($product->sku)
        <p class="text-xs text-blue-600 mt-1">{{$product->sku}}</p>

        @endif
        @auth
            @php
                $isManaged = $product->isInventoryManaged();
                // Use orderable stock (available - safety) for client-facing display
                $orderableStock = $product->getOrderableStockForBodega($bodegaCode);
            @endphp
            @if($showInventory && $isManaged)
                @if($orderableStock <= 0)
                    <p class="text-xs text-red-600 mt-1">Producto no disponible para tu ubicación</p>
                @else
                    <p class="text-xs {{ $orderableStock > 5 ? 'text-green-600' : 'text-red-600' }}">Inventario: {{ $orderableStock }}</p>
                @endif
            @endif
        @else
            @php 
                $isManaged = $product->isInventoryManaged();
                // Use orderable stock (available - safety) for client-facing display
                $orderableStock = $product->getOrderableStockForMdtat();
            @endphp
            @if($showInventory && $isManaged)
                @if($orderableStock <= 0)
                    <p class="text-xs text-red-600 mt-1">Producto no disponible para tu ubicación</p>
                @else
                    <p class="text-xs {{ $orderableStock > 5 ? 'text-green-600' : 'text-red-600' }}">Inventario: {{ $orderableStock }}</p>
                @endif
            @endif
        @endauth
        <div class="flex items-baseline gap-2 mt-2">
            <span class="text-orange-500 font-bold text-lg md:text-xl">${{ currency($product->final_price['price']) }}</span>
            @if($product->final_price['has_discount'])
            <span class="line-through text-gray-400 text-xs md:text-sm font-semibold">${{ currency($product->final_price['old']) }}</span>
            @endif
        </div>

        @if($product->final_price['perItemPrice'])
        <p class="text-xs text-gray-500 mt-1">(Und. x) ${{ currency($product->final_price['perItemPrice']) }}</p>
        @endif
    </div>
    <form action="{{ route('cart.add', $product->id) }}" method="POST" class="w-full flex justify-center">
        @csrf
        <input type="hidden" name="quantity" value="{{ $product->step ?? 1 }}">
        @if($product->variation_id)
            @php
                $firstVariation = $product->items->first();
            @endphp
            @if($firstVariation)
                <input type="hidden" name="variation_id" value="{{ $firstVariation->id }}">
            @endif
        @endif
        <button type="submit" 
                data-add-to-cart 
                data-product-id="{{ $product->id }}" 
                @if($isVacationMode) disabled @endif
                class="bg-orange-500 hover:bg-orange-600 text-white text-sm md:text-base font-semibold rounded-full px-4 py-2 w-full mt-4 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
            <span>¡Lo quiero!</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
            </svg>
        </button>
    </form>
</div>