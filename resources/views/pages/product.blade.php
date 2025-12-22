@extends('layouts.page')

@section('head')
@include('elements.seo', [
'title'=>$product->name,
'description'=> $product->sort_description
])
<link rel="stylesheet" href="{{ asset('css/splide.min.css') }}">
@endsection

@section('content')



<div class="grid grid-cols-12 w-full gap-y-5 gap-x-5 max-w-[90vw] mx-auto mt-5">

    <div class="col-span-12">
        <ul class="flex  space-x-2 text-gray-500 font-semibold uppercase">
            <li><a href="#">Inicio</a></li>
            @if($product->category)
            <li>></li>
            <li><a href="{{route('category', $product->category->slug)}}">{{$product->category->name}}</a></li>
            @endif
            {{-- <li>></li> --}}
            {{-- <li><a href="{{route('category',$product->categories->first()->slug)}}">{{$product->categories->first()->name}}</a></li> --}}
        </ul>
    </div>

    <div class="xl:col-span-6 col-span-12">
        <div class="flex justify-center">
            <div class="w-full max-w-md">
                <!-- Main Image -->
                <div class="w-full mb-4 relative">
                    @if($product->images->first())
                    <img id="mainProductImage" src="{{asset('storage/'.$product->images->first()->path)}}" alt="{{ $product->name }}" class="w-full h-full object-contain aspect-square rounded-lg">
                    <x-product-tag :product="$product" />
                    @endif
                </div>

                <!-- Thumbnails -->
                <div class="w-full">
                    <ul class="grid grid-cols-5 gap-2">
                        @foreach ($product->images as $image)
                        <li class="border border-gray-200 rounded-lg p-1 hover:border-orange-500 transition-colors cursor-pointer">
                            <a href="#" class="thumbnail-link" data-image="{{asset('storage/'.$image->path)}}">
                                <img src="{{asset('storage/'.$image->path)}}" alt="{{ $product->name }}" class="w-full h-full object-contain aspect-square">
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <form action="{{ route('cart.add', $product->id) }}" method="POST" class="xl:col-span-6 col-span-12 space-y-4">
        @csrf
        <div class=" p-2  flex flex-col">
            <div class="flex items-center gap-2">
                <a href="{{route('product', $product->slug)}}" class=" text-[#180F09] font-semibold text-xl">{{$product->name}}</a>
                @php
                    $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
                    $showInventory = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
                    $isManaged = $product->isInventoryManaged();
                    $available = auth()->check() ? $product->getInventoryForBodega($bodegaCode ?? null) : $product->getInventoryForMdtat();
                    $invForTag = $product->inventories->firstWhere('bodega_code', (auth()->check() ? ($bodegaCode ?? null) : 'MDTAT'));
                    $reserved = (int) ($invForTag?->reserved ?? 0);
                @endphp
                @if($showInventory && $isManaged && ($available - $reserved) < 10 && ($available - $reserved) > 0)
                    <span class="ml-2 inline-flex items-center text-xs px-2 py-1 rounded-full bg-orange-100 text-orange-700">últimas unidades disponibles</span>
                @endif
            </div>
            @if($product->sku)
            <p class=" text-slate-500 text-lg">{{$product->sku}}</p>

            @endif
            @auth
                @if($showInventory && $isManaged && $available <= 0)
                    <p class="text-sm text-red-600">Producto no disponible para tu ubicación</p>
                @else
                    @if($showInventory && $isManaged)
                        <p class="text-sm {{ $available > 5 ? 'text-green-600' : 'text-red-600' }}">Inventario: {{ $available }}</p>
                        <p class="text-sm text-gray-600">Inventario (MDTAT): {{ $available }}</p>
                    @endif
                @endif
            @else
                @if($showInventory && $isManaged)
                    <p class="text-sm text-gray-600">Inventario (MDTAT): {{ $available }}</p>
                @endif
            @endauth
            <div class="flex items-baseline gap-2">
                <span class="text-orange-500 font-semibold text-3xl">${{ currency($product->final_price['price']) }}</span>
                @if($product->final_price['has_discount'])
                <span class="line-through text-slate-400 text-xl font-semibold">${{ currency($product->final_price['old']) }}</span>
                @endif
            </div>

            @if($product->final_price['perItemPrice'])
            <p class="text-lg">(Und. x) ${{ currency($product->final_price['perItemPrice']) }}</p>
            @endif
            @if($product->brand)
            <p class=" text-slate-500 text-lg">{{$product->brand->name}}</p>

            @endif
            @if($product->category)
            <p class=" text-[#180F09] text-sm">{{$product->category->name}}</p>
            @endif
        </div>



        @if ($product->variation && $product->items->count())
        <span class="text-xl">{{ $product->variation->name }}:</span>
        <select name="variation_id" id="selectPrice">
            @foreach ($product->items->where('pivot.enabled', 1) as $item)
            <option data-price="{{ $item->pivot->price }}" value="{{ $item->pivot->variation_item_id }}">{{ $item->name }}</option>
            @endforeach
        </select>
        @endif

        <div class=" w-min flex items-center justify-start border border-gray-200 rounded-full p-1">
            <button type="button" id='increment' class="bg-gray-200 text-5xl rounded-full p-2 w-12 h-12 flex items-center justify-center">-</button>
            <input type="numeric" id='quantity' name='quantity' class="w-20 text-center rounded border-0 mx-2 text-xl px-4 focus:ring-0 focus:outline-none" value="{{$product->step}}">
            <button type="button" id='decrement' class="bg-orange-500 text-white text-5xl rounded-full p-2 w-12 h-12 flex items-center justify-center">+</button>
        </div>

        <div>
            @php
                $vacationInfo = \App\Models\Setting::getVacationModeInfo();
                $isVacationMode = $vacationInfo['active'];
                $formattedVacationDate = $vacationInfo['formatted_date'] ?? 'pronto';
            @endphp

            <button type="submit" 
                    data-add-to-cart 
                    data-product-id="{{ $product->id }}" 
                    @if($isVacationMode) disabled @endif
                    class="bg-secondary p-2 mt-4 text-white hover:bg-gray2 flex px-4 text-xl font-semibold rounded-full items-center justify-center w-52 disabled:opacity-50 disabled:cursor-not-allowed">
                <span>¡Lo quiero! </span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
            </button>
            @if($isVacationMode)
                <p class="text-xs text-gray-600 text-center mt-2 w-52 break-words">
                    Tuti está de vacaciones. Te esperamos nuevamente {{ $formattedVacationDate }}. ¡Gracias!
                </p>
            @endif
        </div>
    </form>

    <hr class="border-gray-200 col-span-12 mt-5">

    <!-- Product Details Accordion -->
    @php
        $hasDetails = $product->description || $product->technical_specifications || $product->warranty || $product->other_information;
    @endphp
    
    @if($hasDetails)
    <div class="col-span-12 py-5">
        <h3 class="font-bold text-xl mb-4">Detalles del producto</h3>
        <div 
            id="product-details-accordion"
            data-description="{{ json_encode($product->description ?? '', JSON_UNESCAPED_UNICODE) }}"
            data-technical-specifications="{{ json_encode($product->technical_specifications ?? '', JSON_UNESCAPED_UNICODE) }}"
            data-warranty="{{ json_encode($product->warranty ?? '', JSON_UNESCAPED_UNICODE) }}"
            data-other-information="{{ json_encode($product->other_information ?? '', JSON_UNESCAPED_UNICODE) }}"
            data-content-encoding="json"
        ></div>
    </div>
    @endif
    
    <hr class="border-gray-200 col-span-12 mt-5">

    @if($related->count())
    <div class="col-span-12 py-5">
        <h3 class="font-bold text-xl mb-2">Complementa tu compra</h3>
        
        <!-- Desktop Grid -->
        <div class="hidden xl:grid xl:grid-cols-4 gap-5">
            @foreach ($related as $p)
            <x-product :product="$p" :bodega-code="$bodegaCode ?? null" />
            @endforeach
        </div>
        
        <!-- Mobile Carousel -->
        <div class="xl:hidden">
            <div id="complementary-products-carousel" class="splide">
                <div class="splide__track">
                    <ul class="splide__list">
                        @foreach ($related as $p)
                        <li class="splide__slide">
                            <x-product :product="$p" :bodega-code="$bodegaCode ?? null" />
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(count($intermedio) > 0 || count($lateral) > 0)
    <div class="xl:col-span-12 col-span-12 mt-6">
        @if(count($intermedio) > 0)
        <div id='intermedio-banners-product' class="splide mb-0">
            <div class="splide__track">
                <ul class="splide__list">
                    @foreach ($intermedio as $banner)
                    <li class="splide__slide">
                        <a href="{{$banner->url ?? '#'}}">
                            <img src="{{asset('storage/'.$banner->path)}}" class="w-full rounded-lg">
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        @if(count($lateral) > 0)
        <div class="mb-0 pb-0 bg-neutral-200">
            <div id='ads' class="splide mb-0 p-3 bg-neutral-200">
                <div class="splide__track">
                    <ul class="splide__list">
                        @foreach ($lateral as $banner)
                        <li class="splide__slide">
                            <a href="{{$banner->url ?? '#'}}">
                                <img src="{{asset('storage/'.$banner->path)}}" class="w-full">
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif


</div>

@endsection




@section('scripts')

<script>
    $(function() {
        // Thumbnail image click handler
        $('.thumbnail-link').on('click', function(e) {
            e.preventDefault();
            var newImageSrc = $(this).data('image');
            $('#mainProductImage').attr('src', newImageSrc);
        });

        const step = parseInt('{{$product->step}}');

        $('#increment').on('click', function() {
            let quantity = parseInt($('#quantity').val())
            quantity = quantity - step
            if (quantity < step) {
                quantity = step
            }
            $('#quantity').val(quantity)
        })

        $('#decrement').on('click', function() {
            let quantity = parseInt($('#quantity').val())
            quantity = quantity + step
            $('#quantity').val(quantity)
        })

        //make sure the quantity is a multiple of the step
        $('#quantity').on('change', function() {
            let quantity = parseInt($(this).val())
            if (quantity % step != 0) {
                quantity = Math.floor(quantity / step) * step
                $(this).val(quantity)
            }
        })

    })
</script>


<script src="{{asset('js/splide.min.js')}}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('ads')) {
            new Splide('#ads', {
                type: 'loop',
                autoplay: true,
                perPage: 6,
                arrows: true,
                pagination: true,
                gap: '1rem',
            }).mount();
        }

        // Initialize intermedio banners carousel if it exists
        if (document.getElementById('intermedio-banners-product')) {
            new Splide('#intermedio-banners-product', {
                type: 'loop',
                autoplay: true,
                perPage: 1,
                arrows: true,
                pagination: true,
            }).mount();
        }
        
        // Initialize complementary products carousel if it exists
        if (document.getElementById('complementary-products-carousel')) {
            new Splide('#complementary-products-carousel', {
                type: 'slide',
                perPage: 1,
                perMove: 1,
                arrows: true,
                pagination: true,
                gap: '1rem',
                padding: '1rem',
                breakpoints: {
                    640: {
                        perPage: 1,
                        gap: '0.5rem',
                        padding: '0.5rem',
                    },
                    768: {
                        perPage: 2,
                        gap: '1rem',
                        padding: '1rem',
                    },
                },
            }).mount();
        }
    });
</script>

@endsection