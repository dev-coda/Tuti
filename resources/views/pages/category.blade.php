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
<section class="w-full grid xl:grid-cols-12 gap-x-10 xl:gap-y-0 gap-y-10 max-w-[100vw] overflow-hidden">
    <div id='banners' class="splide mb-10 max-h-[384px] w-full max-w-[90vw] col-span-12">
        <div class="splide__track">
            <ul class="splide__list">
                @foreach ($banners as $banner)
                <li class="splide__slide">
                    <a href="{{$banner->url ?? '#'}}">
                        <img src="{{asset('storage/'.$banner->path)}}" class="w-full">
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="flex md:flex-row flex-col items-center md:justify-between justify-center gap-3 col-span-12 font-semibold w-full max-w-[90vw]">
        <ul class="flex  space-x-2 text-gray-500 uppercase">
            <li><a href="/">Inicio</a></li>
            <li>></li>
            <li><a href=" {{route('category', $params['slug'])}} ">{{$category->name}}</a></li>
        </ul>
        <div id="filter-sort-dropdowns"
            data-current-sort="{{ $params['order'] }}"
            data-current-brand-id="{{ $params['brand_id'] ?? 'null' }}"
            data-current-category-id="{{ $params['category_id'] ?? 'null' }}"
            data-sort-options="{{ json_encode([
                ['value' => '1', 'label' => 'Más reciente', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/1/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '2', 'label' => 'Precio: Menor a Mayor', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/2/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '3', 'label' => 'Precio: Mayor a Menor', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/3/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '4', 'label' => 'Nombre A-Z', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/4/' . $params['category_id'] . '/' . $params['brand_id'])],
                ['value' => '5', 'label' => 'Nombre Z-A', 'url' => route('category', $params['slug'] . '/' . $params['slug2'] . '/5/' . $params['category_id'] . '/' . $params['brand_id'])]
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


    <h1 class="font-bold my-5 text-3xl col-span-12 w-full">{{$category->name}}</h1>

    @if ($products->count() === 0)
    <div class="col-span-12 h-screen">
        <p>No se encontraron productos con estos filtros. Intenta de nuevo.</p>
    </div>
    @endif

    <div class="col-span-12 ">
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-0 gap-y-10 ">
            @foreach ($products as $product)
            <x-product :product="$product" />
            @endforeach

        </div>
    </div>

    <div class="col-span-12 ">
        {{ $products->links() }}

    </div>






</section>
@endsection

@section('scripts')
<script src="{{asset('js/splide.min.js')}}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Splide('#banners', {
            type: 'loop',
            autoplay: true,
        }).mount();
    });
</script>

@endsection