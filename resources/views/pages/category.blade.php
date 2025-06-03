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
        <div class="md:col-span-12 pt-3 max-w-[90vw] uppercase font-semibold">
            <div class="flex md:justify-between md:items-center flex-col md:flex-row pr-3">
                <div class="flex items-start md:items-center space-x-2 flex-col md:flex-row mr-3">
                    <span class="w-full">Ordenar por:</span>
                    <select name="sort" id="sort" class="border border-gray-200 rounded-md p-2 w-full" onchange="window.location.href=this.value">
                        <option {{ $params['order'] === '1' ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . 1 . '/' . $params['category_id'] . '/' . $params['brand_id']  ) }}">Más reciente</option>
                        <option {{ $params['order'] === '2' ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . 2 . '/' . $params['category_id'] . '/' . $params['brand_id']  ) }}">Precio: Menor a Mayor</option>
                        <option {{ $params['order'] === '3' ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . 3 . '/' . $params['category_id'] . '/' . $params['brand_id']  ) }}">Precio: Mayor a Menor</option>
                        <option {{ $params['order'] === '4' ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . 4 . '/' . $params['category_id'] . '/' . $params['brand_id']  ) }}">Nombre A-Z</option>
                        <option {{ $params['order'] === '5' ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . 5 . '/' . $params['category_id'] . '/' . $params['brand_id']  ) }}">Nombre Z-A</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2 flex-wrap md:flex-nowrap">
                    <span class="w-full">Filtrar por:</span>
                    <select name="filter" id="filter" class="border border-gray-200 rounded-md p-2 w-full" onchange="window.location.href=this.value">
                        <option value="#" disabled><b>Marca</b></option>
                        @foreach ($brands as $brand)
                        <option {{ $params['brand_id'] == $brand->id ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . $params['order'] . '/' . $params['category_id'] . '/' . $brand->id  ) }}">&nbsp;&nbsp;&nbsp;{{ $brand->name }}</option>
                        @endforeach
                        <option value="#" disabled><b>Categoria</b></option>
                        @foreach ($categories as $categoryItem)
                        <option {{ $params['category_id'] == $categoryItem->id ? 'selected' : '' }} value="{{ route('category', $params['slug'] . '/' . $params['slug2'] . '/' . $params['order'] . '/' . $categoryItem->id . '/' . $params['brand_id']  ) }}">&nbsp;&nbsp;&nbsp;{{ $categoryItem->name }}</option>
                        @endforeach
                    </select>

                </div>
            </div>
        </div>
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

        new Splide('#ads', {
            type: 'loop',
            autoplay: true,
        }).mount();
    });
</script>

@endsection