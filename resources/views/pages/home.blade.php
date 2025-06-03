@extends('layouts.page')


@section('head')

@include('elements.seo', ['title'=>'Inicio' ])

<link rel="stylesheet" href="{{asset('css/splide.min.css')}}">
<link rel="stylesheet" href="{{asset('css/slick.css')}}">

@endsection


@section('content')




<section id='banners' class="splide">
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
</section>







<section class="w-full grid grid-cols-12 xl:gap-x-10 gap-x-0 xl:gap-y-0 gap-y-10">



    <div class="xl:col-span-12 col-span-12 bg-neutral-200 p-6">

        <h4 class="col-span-12 text-slate-700 text-3xl font-semibold mb-3 flex justify-center">
            Categorías
        </h4>
        <div class="grid grid-cols-12 grid-flow-row xl:grid-cols-12 gap-5 ">
            @foreach ($featured as $category)

            <div class="border border-gray-100 rounded md:col-span-4 col-span-12 bg-cover bg-center hover:scale-110 transition duration-500 cursor-pointer object-cover" style="background-image: url({{asset('storage/'.$category->image)}});">
                <div class=" text-gray-400">
                    <a href="{{route('category', $category->slug)}}" class="h-40 block w-full bg-cover bg-center hover:scale-110 transition duration-500 cursor-pointer object-cover mt-0">
                        <div class="bg-orange-500 text-white font-semibold text-lg p-4  flex flex-col relative h-12 w-64 bottom-6 mx-auto justify-center items-center rounded-3xl mt-0">
                            <a href="{{route('category', $category->slug)}}" class="mx-auto mt-0 pt-0">{{$category->name}}</a>
                        </div>

                    </a>

                </div>

            </div>

            @endforeach
        </div>




    </div>

    <div class="xl:col-span-12 col-span-12">
        <h4 class="col-span-12 text-slate-700 text-3xl font-semibold mb-3 mt-12 flex justify-center">
            Productos Destacados
        </h4>
        <div class="xl:col-span-12 col-span-12 pb-6">
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-0 ">
                @foreach ($products as $product)
                <x-product :product="$product" />
                @endforeach

            </div>
        </div>

        {{ $products->links() }}
    </div>


</section>
<div class="xl:col-span-12 col-span-12 mt-6 mb-0 pb-0  bg-neutral-200">

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
            perPage: 6,
        }).mount();
    });





    // $("splide").slick({
    //     infinite: true,
    //     slidesToShow: 2,
    //     dots: false,
    //     slidesToScroll: 1,
    //     autoplay: true,
    //     nextArrow:
    //         '<button><i class="fa-solid fa-chevron-right " style = " position: absolute; right: 0; top: 50%; "></i></button>',
    //     prevArrow:
    //         '<button><i class="fa-solid fa-chevron-left " style = " position: absolute; left: -10px; top: 50%; "></i></button>',
    //     responsive: [
    //         {
    //             breakpoint: 600,
    //             settings: {
    //                 slidesToShow: 1,
    //                 slidesToScroll: 1,
    //             },
    //         },
    //     ],
    // });




    // $('.owl-carousel.lateral').owlCarousel({
    //     loop:true,
    //     margin:10,
    //     nav:false,
    //     //auto
    //     autoplay:true,

    //     responsive:{
    //         0:{
    //             items:2
    //         },


    //         1000:{
    //             items:1
    //         }
    //     }

    // })
</script>

@endsection