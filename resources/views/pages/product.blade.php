@extends('layouts.page')

@section('head')
@include('elements.seo', [
'title'=>$product->name,
'description'=> $product->sort_description
])
<link rel="stylesheet" href="{{ asset('css/splide.min.css') }}">
<style>
/* Quill.js Content Styling - Matches editor appearance */
.product-content h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    line-height: 1.75rem;
}

.product-content h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    line-height: 1.75rem;
}

.product-content h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
    line-height: 1.75rem;
}

.product-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    line-height: 1.75rem;
}

.product-content p {
    margin-bottom: 1rem;
    line-height: 1.75rem;
}

.product-content ul,
.product-content ol {
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.product-content ul {
    list-style-type: disc;
    list-style-position: outside;
}

.product-content ol {
    list-style-type: disc;
    list-style-position: outside;
}

.product-content li {
    margin-bottom: 0.5rem;
    line-height: 1.75rem;
    padding-left: 0.5rem;
}

.product-content li > p {
    margin-bottom: 0.25rem;
}

.product-content strong {
    font-weight: 700;
    color: #111827;
}

.product-content em {
    font-style: italic;
}

.product-content u {
    text-decoration: underline;
}

.product-content a {
    color: #f97316;
    text-decoration: underline;
}

.product-content a:hover {
    color: #ea580c;
}

.product-content blockquote {
    border-left: 4px solid #f97316;
    padding-left: 1rem;
    font-style: italic;
    margin: 1rem 0;
    color: #6b7280;
}

.product-content code {
    background-color: #f3f4f6;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.product-content pre {
    background-color: #f3f4f6;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1rem 0;
}

.product-content img {
    border-radius: 0.5rem;
    margin: 1rem 0;
    max-width: 100%;
    height: auto;
}

.product-content hr {
    margin: 1.5rem 0;
    border-color: #d1d5db;
}

/* Ensure nested lists work correctly */
.product-content ul ul,
.product-content ul ol,
.product-content ol ul,
.product-content ol ol {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

/* Handle alignment classes from Quill */
.product-content .ql-align-center {
    text-align: center;
}

.product-content .ql-align-right {
    text-align: right;
}

.product-content .ql-align-justify {
    text-align: justify;
}
</style>
@endsection

@section('content')



<div class="grid grid-cols-12 w-full gap-y-5 gap-x-5 max-w-[90vw] mx-auto">

    <!-- Breadcrumbs -->
    <nav class="col-span-12 py-2 border-b border-gray-200 overflow-hidden" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm min-w-0">
            <li class="flex-shrink-0">
                <a href="{{ route('home') }}" class="text-gray-500 hover:text-orange-500 uppercase font-medium tracking-wide">INICIO</a>
            </li>
            @if($product->category)
            <li class="text-gray-400 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </li>
            <li class="flex-shrink-0 max-w-[120px] sm:max-w-none">
                <a href="{{ route('category', $product->category->slug) }}" class="text-gray-500 hover:text-orange-500 uppercase font-medium tracking-wide truncate block">{{ strtoupper($product->category->name) }}</a>
            </li>
            @endif
            <li class="text-gray-400 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </li>
            <li class="min-w-0 flex-1">
                <span class="text-gray-700 uppercase font-medium tracking-wide truncate block">{{ strtoupper($product->name) }}</span>
            </li>
        </ol>
    </nav>

    <div class="xl:col-span-6 col-span-12">
        <div class="flex justify-center">
            <div class="w-full max-w-md">
                <!-- Main Image Card -->
                <div class="w-full mb-4 relative bg-white rounded-2xl border border-gray-200 p-4">
                    @if($product->images->first())
                    <img id="mainProductImage" src="{{asset('storage/'.$product->images->first()->path)}}" alt="{{ $product->name }}" class="w-full h-full object-contain aspect-square">
                    <x-product-tag :product="$product" />
                    @endif
                </div>

                <!-- Thumbnails -->
                <div class="w-full flex justify-center">
                    <ul class="flex gap-3">
                        @foreach ($product->images as $index => $image)
                        <li class="thumbnail-item {{ $index === 0 ? 'border-2 border-orange-500' : 'border-2 border-gray-200' }} rounded-lg p-1 hover:border-orange-500 transition-colors cursor-pointer bg-white">
                            <a href="#" class="thumbnail-link" data-image="{{asset('storage/'.$image->path)}}">
                                <img src="{{asset('storage/'.$image->path)}}" alt="{{ $product->name }}" class="w-14 h-14 object-contain">
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
        <div class="flex flex-col">
            <!-- Product Name -->
            <h1 class="text-[#180F09] font-semibold text-2xl leading-tight">{{$product->name}}</h1>
            
            <!-- SKU -->
            @if($product->sku)
            <p class="text-slate-400 text-sm mt-1">{{$product->sku}}</p>
            @endif

            <!-- Inventory Status -->
            @php
                $inventoryEnabled = \App\Models\Setting::getByKey('inventory_enabled');
                $showInventory = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
                $isManaged = $product->isInventoryManaged();
                // Use orderable stock (available - safety) for client-facing display
                $orderableStock = auth()->check() 
                    ? $product->getOrderableStockForBodega($bodegaCode ?? null) 
                    : $product->getOrderableStockForMdtat();
            @endphp
            @auth
                @if($showInventory && $isManaged && $orderableStock <= 0)
                    <p class="text-sm text-orange-500 mt-1">Producto no disponible para ubicación</p>
                @elseif($showInventory && $isManaged && $orderableStock < 10 && $orderableStock > 0)
                    <span class="inline-flex items-center text-xs px-2 py-1 rounded-full bg-orange-100 text-orange-700 mt-2 w-fit">últimas unidades disponibles</span>
                @endif
            @endauth

            <!-- Short Description Box -->
            @if($product->short_description)
            <div class="bg-gray-100 rounded-lg p-4 mt-4">
                <p class="text-gray-700 text-sm">{{ $product->short_description }}</p>
            </div>
            @endif

            <!-- Price Section -->
            <div class="mt-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-orange-500 font-bold text-4xl">${{ currency($product->final_price['price']) }}</span>
                    @if($product->final_price['has_discount'])
                    <span class="line-through text-slate-400 text-xl font-semibold">${{ currency($product->final_price['old']) }}</span>
                    @endif
                </div>

                @if($product->final_price['perItemPrice'])
                <p class="text-gray-500 text-sm mt-1">(Und. x) ${{ currency($product->final_price['perItemPrice']) }}</p>
                @endif
            </div>

            <!-- Brand & Category -->
            <div class="mt-2">
                @if($product->brand)
                <p class="text-slate-600 font-medium">{{$product->brand->name}}</p>
                @endif
                @if($product->category)
                <p class="text-slate-500 text-sm">{{$product->category->name}}</p>
                @endif
            </div>
        </div>

        <!-- Variation Selector -->
        @if ($product->variation && $product->items->count())
        <div class="mt-4">
            <span class="text-lg font-medium">{{ $product->variation->name }}:</span>
            <select name="variation_id" id="selectPrice" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach ($product->items->where('pivot.enabled', 1) as $item)
                <option data-price="{{ $item->pivot->price }}" value="{{ $item->pivot->variation_item_id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <!-- Quantity & Add to Cart Row -->
        <div class="flex items-center gap-4 mt-4 flex-wrap">
            <!-- Quantity Selector -->
            <div class="flex items-center border border-gray-200 rounded-full p-1">
                <button type="button" id='increment' class="bg-gray-200 text-2xl rounded-full w-10 h-10 flex items-center justify-center font-medium hover:bg-gray-300 transition-colors">−</button>
                <input type="numeric" id='quantity' name='quantity' class="w-16 text-center rounded border-0 text-lg px-2 focus:ring-0 focus:outline-none" value="{{$product->step}}">
                <button type="button" id='decrement' class="bg-orange-500 text-white text-2xl rounded-full w-10 h-10 flex items-center justify-center font-medium hover:bg-orange-600 transition-colors">+</button>
            </div>

            <!-- Add to Cart Button -->
            @php
                $vacationInfo = \App\Models\Setting::getVacationModeInfo();
                $isVacationMode = $vacationInfo['active'];
                $formattedVacationDate = $vacationInfo['formatted_date'] ?? 'pronto';
            @endphp

            <button type="submit" 
                    data-add-to-cart 
                    data-product-id="{{ $product->id }}" 
                    @if($isVacationMode) disabled @endif
                    class="bg-orange-500 py-3 px-8 text-white hover:bg-orange-600 flex text-lg font-semibold rounded-full items-center justify-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex-1 md:flex-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
                <span>¡Lo quiero!</span>
            </button>
        </div>
        
        @if($isVacationMode)
            <p class="text-xs text-gray-600 mt-2">
                Tuti está de vacaciones. Te esperamos nuevamente {{ $formattedVacationDate }}. ¡Gracias!
            </p>
        @endif

        <!-- Specifications PDF Card -->
        @php
            $hasPdf = !empty($product->specifications_pdf);
        @endphp
        <div class="mt-6">
            @if($hasPdf)
            <a href="{{ asset('storage/'.$product->specifications_pdf) }}" 
               target="_blank" 
               download
               class="block rounded-2xl border border-gray-200 bg-white transition-colors group shadow-[inset_4px_0_0_0_#f97316] hover:shadow-[inset_4px_0_0_0_#ea580c]">
                <div class="flex items-center justify-between py-4 px-4 pl-5">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-800 group-hover:text-orange-600">Ficha Tecnica</span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </div>
            </a>
            @else
            <div class="rounded-2xl border border-gray-200 bg-gray-50 opacity-50 cursor-not-allowed shadow-[inset_4px_0_0_0_#d1d5db]">
                <div class="flex items-center justify-between py-4 px-4 pl-5">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-400">Ficha Tecnica</span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </div>
            </div>
            @endif
        </div>
    </form>

    <!-- Product Details Accordions -->
    @php
        $hasDetails = $product->description || $product->technical_specifications || $product->warranty || $product->other_information;
    @endphp
    
    @if($hasDetails)
    <div class="col-span-12 py-6 space-y-4">
        
        <!-- Descripción Accordion -->
        @if($product->description)
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[inset_4px_0_0_0_#f97316] hover:shadow-[inset_4px_0_0_0_#ea580c] transition-shadow cursor-pointer">
            <button type="button" 
                    onclick="toggleAccordion('description')" 
                    class="w-full flex items-center justify-between py-4 px-4 pl-5 transition-colors rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <span class="font-medium text-gray-800">Descripción</span>
                </div>
                <svg id="description-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400 transition-transform duration-300 rotate-180">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div id="description-content" class="border-t border-gray-100">
                <div class="p-6 pl-7">
                    <div class="product-content text-gray-700 text-base leading-7">{!! $product->description !!}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Ficha Técnica Accordion -->
        @if($product->technical_specifications)
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[inset_4px_0_0_0_#f97316] hover:shadow-[inset_4px_0_0_0_#ea580c] transition-shadow cursor-pointer">
            <button type="button" 
                    onclick="toggleAccordion('tech-specs')" 
                    class="w-full flex items-center justify-between py-4 px-4 pl-5 transition-colors rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span class="font-medium text-gray-800">Ficha Técnica</span>
                </div>
                <svg id="tech-specs-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400 transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div id="tech-specs-content" class="hidden border-t border-gray-100">
                <div class="p-6 pl-7">
                    <div class="product-content text-gray-700 text-base leading-7">{!! $product->technical_specifications !!}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Garantía Accordion -->
        @if($product->warranty)
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[inset_4px_0_0_0_#f97316] hover:shadow-[inset_4px_0_0_0_#ea580c] transition-shadow cursor-pointer">
            <button type="button" 
                    onclick="toggleAccordion('warranty')" 
                    class="w-full flex items-center justify-between py-4 px-4 pl-5 transition-colors rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    <span class="font-medium text-gray-800">Garantía</span>
                </div>
                <svg id="warranty-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400 transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div id="warranty-content" class="hidden border-t border-gray-100">
                <div class="p-6 pl-7">
                    <div class="product-content text-gray-700 text-base leading-7">{!! $product->warranty !!}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Otra Información Accordion -->
        @if($product->other_information)
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[inset_4px_0_0_0_#f97316] hover:shadow-[inset_4px_0_0_0_#ea580c] transition-shadow cursor-pointer">
            <button type="button" 
                    onclick="toggleAccordion('other-info')" 
                    class="w-full flex items-center justify-between py-4 px-4 pl-5 transition-colors rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                    <span class="font-medium text-gray-800">Otra Información</span>
                </div>
                <svg id="other-info-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400 transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
            <div id="other-info-content" class="hidden border-t border-gray-100">
                <div class="p-6 pl-7">
                    <div class="product-content text-gray-700 text-base leading-7">{!! $product->other_information !!}</div>
                </div>
            </div>
        </div>
        @endif

    </div>
    @endif

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
    // Accordion toggle function
    function toggleAccordion(id) {
        const content = document.getElementById(id + '-content');
        const chevron = document.getElementById(id + '-chevron');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            chevron.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            chevron.classList.remove('rotate-180');
        }
    }

    $(function() {
        // Thumbnail image click handler with selection indicator
        $('.thumbnail-link').on('click', function(e) {
            e.preventDefault();
            var newImageSrc = $(this).data('image');
            $('#mainProductImage').attr('src', newImageSrc);
            
            // Update selection indicator
            $('.thumbnail-item').removeClass('border-orange-500').addClass('border-gray-200');
            $(this).closest('.thumbnail-item').removeClass('border-gray-200').addClass('border-orange-500');
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