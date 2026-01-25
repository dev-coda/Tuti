@extends('layouts.page')


@section('head')

@include('elements.seo', ['title'=>'Inicio' ])

<link rel="stylesheet" href="{{asset('css/splide.min.css')}}">
<link rel="stylesheet" href="{{asset('css/slick.css')}}">

@endsection


@section('content')




<section class="w-full bg-gray-100 pt-4 sm:pt-6">
    <div class="max-w-6xl mx-auto px-4">
        <div class="rounded-2xl overflow-hidden border border-gray-200 bg-white shadow-sm">
            <section id='banners' class="splide">
                <div class="splide__track">
                    <ul class="splide__list">
                        @foreach ($banners as $banner)
                        <li class="splide__slide">
                            <a href="{{$banner->url ?? '#'}}">
                                <img src="{{asset('storage/'.$banner->path)}}" class="w-full h-auto object-cover">
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>
    </div>
</section>







<section class="max-w-6xl mx-auto px-4 mt-6 sm:mt-8 space-y-6 sm:space-y-8">
    <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg sm:text-2xl font-semibold text-gray-900">Categorías</h2>
        </div>
        <div id="featured-categories" class="pt-1"></div>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg sm:text-2xl font-semibold text-gray-900">Productos Destacados</h2>
        </div>
        <div id="featured-products" class="pt-1"></div>
    </div>
</section>

<section class="max-w-6xl mx-auto px-4 mt-6 sm:mt-8 pb-6 sm:pb-10">
    <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg sm:text-2xl font-semibold text-gray-900">Marcas Destacadas</h2>
        </div>
        <div id='ads' class="splide">
            <div class="splide__track">
                <ul class="splide__list">
                    @foreach ($lateral as $banner)
                    <li class="splide__slide">
                        <a href="{{$banner->url ?? '#'}}">
                            <img src="{{asset('storage/'.$banner->path)}}" class="w-full h-auto object-contain">
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
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
            perPage: 1,
            gap: '1rem',
        }).mount();

        new Splide('#ads', {
            type: 'loop',
            autoplay: true,
            perPage: 6,
            gap: '1rem',
            breakpoints: {
                1280: { perPage: 5 },
                1024: { perPage: 4 },
                768: { perPage: 3 },
                640: { perPage: 2 },
                480: { perPage: 2 }
            }
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