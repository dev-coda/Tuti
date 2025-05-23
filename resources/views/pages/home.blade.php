@extends('layouts.page')


@section('head')

@include('elements.seo', ['title'=>'Inicio' ])

<link rel="stylesheet" href="{{asset('css/splide.min.css')}}">
<link rel="stylesheet" href="{{asset('css/slick.css')}}">

@endsection


@section('content')




<section id='banners' class="splide mb-10">
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
    
    <div class="xl:col-span-9 col-span-12 ">
        <div class="grid grid-cols-2 xl:grid-cols-3 gap-5 ">
            @foreach ($products as $product)
            <x-product :product="$product" />
            @endforeach

        </div>
    </div>

    <div class="xl:col-span-3 col-span-12">

        <div id='ads' class="splide mb-10">
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