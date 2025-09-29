@props(['product', 'bodegaCode' => null])
@php
    $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
    $showInventory = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
@endphp
<div class=" rounded flex flex-col p-2 md:p-6 max-w-[90vw]">
    <div class="flex w-full items-center justify-center py-1 md:py-2 text-gray-400 flex-grow">
        @if($product->images->first())
        <a href="{{route('product', $product->slug)}}" class="flex-grow-1 h-40 block w-full bg-contain bg-center bg-no-repeat hover:scale-110 transition duration-500 cursor-pointer" style="background-image: url({{asset('storage/'.$product->images->first()->path)}});">
        </a>
        @else
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        @endif


    </div>


    <div class=" p-1 md:p-2  flex flex-col">
        <a href="{{route('product', $product->slug)}}" class=" text-[#180F09] font-semibold text-lg">{{$product->name}}</a>
        @if($product->sku)
        <p class=" text-slate-500 text-md">{{$product->sku}}</p>

        @endif
        @auth
            @php
                $available = $product->getInventoryForBodega($bodegaCode);
            @endphp
            @if($showInventory)
                @if($available <= 0)
                    <p class="text-xs text-red-600">Producto no disponible para tu ubicación</p>
                @else
                    <p class="text-xs {{ $available > 5 ? 'text-green-600' : 'text-red-600' }}">Inventario: {{ $available }}</p>
                @endif
            @endif
        @else
            @php $mdtat = $product->getInventoryForMdtat(); @endphp
            @if($showInventory)
                <p class="text-xs text-gray-600">Inventario (MDTAT): {{ $mdtat }}</p>
            @endif
        @endauth
        <div class="flex items-baseline gap-2">
            <span class="text-orange-500 font-semibold text-2xl">${{ currency($product->final_price['price']) }}</span>
            @if($product->final_price['has_discount'])
            <span class="line-through text-slate-400 text-base font-semibold">${{ currency($product->final_price['old']) }}</span>
            @endif
        </div>

        @if($product->final_price['perItemPrice'])
        <p>(Und. x) ${{ currency($product->final_price['perItemPrice']) }}</p>
        @endif
    </div>
    <a href="{{route('product', $product->slug)}}" class="bg-secondary p-1 md:p-2 mt-2 md:mt-4 text-white hover:bg-gray2 flex px-2 md:px-4 text-lg md:text-xl font-semibold rounded-full items-center justify-center w-40 md:w-52 mx-auto">
        <span>¡Lo quiero! </span>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
        </svg>
    </a>
</div>