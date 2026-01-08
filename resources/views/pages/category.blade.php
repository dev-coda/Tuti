@extends('layouts.page')

@section('head')
<link rel="stylesheet" href="{{asset('css/splide.min.css')}}">
<link rel="stylesheet" href="{{asset('css/slick.css')}}">
@include('elements.seo', [
'title'=>$category->name,
'description'=>$category->description
])
@endsection

@section('content')
<section class="w-full grid xl:grid-cols-12 gap-x-10 xl:gap-y-0 gap-y-2 md:gap-y-10 max-w-[100vw] overflow-hidden">
    @if($category->image)
    <div class="col-span-12 mb-4 md:mb-10 flex justify-center max-w-[90vw]">
        <img 
            src="{{asset('storage/'.$category->image)}}" 
            alt="{{$category->name}}" 
            class="w-full max-w-full h-auto object-contain md:w-full md:h-64 lg:h-72 xl:h-80 md:object-cover md:rounded-lg"
        >
    </div>
    @endif
    <div class="flex md:flex-row flex-col md:items-center md:justify-between gap-3 col-span-12 w-full max-w-[90vw] py-2 border-b border-gray-200">
        <nav aria-label="Breadcrumb" class="self-start overflow-hidden max-w-full">
            <ol class="flex items-center space-x-2 text-sm min-w-0">
                <li class="flex-shrink-0">
                    <a href="{{ route('home') }}" class="text-gray-500 hover:text-orange-500 uppercase font-medium tracking-wide">INICIO</a>
                </li>
                <li class="text-gray-400 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </li>
                <li class="min-w-0 flex-1">
                    <span class="text-gray-700 uppercase font-medium tracking-wide truncate block">{{ strtoupper($category->name) }}</span>
                </li>
            </ol>
        </nav>
        <div id="filter-sort-dropdowns" class="self-end md:self-auto"
            data-current-sort="{{ $params['order'] }}"
            data-current-brand-id="{{ $params['brand_id'] ?? 'null' }}"
            data-current-category-id="{{ $params['category_id'] ?? 'null' }}"
            data-sort-options="{{ json_encode([
                ['value' => '1', 'label' => 'Más reciente', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/1/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '2', 'label' => 'Precio: Menor a Mayor', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/2/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '3', 'label' => 'Precio: Mayor a Menor', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/3/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '4', 'label' => 'Nombre A-Z', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/4/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '5', 'label' => 'Nombre Z-A', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/5/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '6', 'label' => 'Más vendidos', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/6/' . $params['category_id'] . '/' . $params['brand_id'])]
            ]) }}"
            data-brands="{{ json_encode($brands->map(function($brand) use ($params) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/' . $params['order'] . '/' . $params['category_id'] . '/' . $brand->id)
                ];
            })) }}"
            data-categories="{{ json_encode($categories->map(function($category) use ($params) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/' . $params['order'] . '/' . $category->id . '/' . $params['brand_id'])
                ];
            })) }}"></div>
    </div>


    <h1 class="font-bold my-2 md:my-5 text-3xl col-span-12 w-full">{{$category->name}}</h1>

    @php
        $vacationInfo = \App\Models\Setting::getVacationModeInfo();
        $isVacationMode = $vacationInfo['active'];
        $formattedVacationDate = $vacationInfo['formatted_date'] ?? 'pronto';
    @endphp

    @if($isVacationMode)
    <div class="col-span-12 mb-6">
        <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded-lg shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm text-orange-700">
                        <span class="font-semibold">Tuti está de vacaciones.</span> Te esperamos nuevamente <span class="font-medium">{{ $formattedVacationDate }}</span>. ¡Gracias!
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if ($products->count() === 0)
    <div class="col-span-12 h-screen">
        <p>No se encontraron productos con estos filtros. Intenta de nuevo.</p>
    </div>
    @endif

    <div class="col-span-12 ">
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-0 gap-y-4 md:gap-y-10 ">
            @foreach ($products as $product)
            <x-product :product="$product" :bodega-code="$bodegaCode ?? null" />
            @endforeach

        </div>
    </div>

    <div class="col-span-12 ">
        {{ $products->links() }}

    </div>






</section>
@endsection

@section('scripts')

@endsection