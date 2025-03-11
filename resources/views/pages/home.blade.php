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
    <div class="col-span-3 hidden xl:block" id="accordion-collapse2" data-accordion="collapse" data-active-classes='text-gray-700'>

        @foreach ($categories as $category)

        <h2 id="accordion-collapse2-heading-c{{$category->id}}">

            <button type="button" class="bg-blue2  @if($loop->first) rounded-t @endif @if($loop->last) rounded-b @endif flex items-center justify-between w-full py-2 px-4 font-medium rtl:text-right text-gray-500  focus:ring-0 focus:ring-gray-200  gap-3" data-accordion-target="#accordion-collapse2-body-c{{$category->id}}" aria-expanded="true" aria-controls="accordion-collapse2-body-c{{$category->id}}">
                <div class="flex items-center space-x-2">
                    <span class="icon-energy"></span>
                    <span>{{$category->name}}</span>
                </div>

                <svg data-accordion-icon class="w-3 h-3 rotate-180 shrink-0" aria-hidden="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5 5 1 1 5" />
                </svg>
            </button>
        </h2>
        <div id="accordion-collapse2-body-c{{$category->id}}" class="hidden bg-blue3" aria-labelledby="accordion-collapse2-heading-c{{$category->id}}">
            <div class="px-3 py-3">
                <ul class="pl-7 text-sm space-y-2">
                    <li><a class="text-gray-600" href="{{route('category', $category->slug)}}">{{$category->name}}</a></li>
                    @foreach ($category->children as $subcategory)
                    <li><a class="text-gray-600" href="{{route('category2', $subcategory->slug)}}">{{$subcategory->name}}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        @endforeach



    </div>
    <div class="xl:col-span-6 col-span-12 ">
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