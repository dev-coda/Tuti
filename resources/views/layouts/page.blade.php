<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{asset('favicon.png')}}" sizes="32x32" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
    $categories = App\Models\Category::active()->whereNull('parent_id')->with('children')->orderBy('name')->get();
    $phone = App\Models\Setting::where('key', 'phone')->first()->value;
    $email = App\Models\Setting::where('key', 'email')->first()->value;
    $google_tag = App\Models\Setting::where('key', 'google_tag')->first()->value;
    @endphp

    {!! $google_tag !!}

    @yield('head')

    <!-- Icons -->
    <link rel="icon" href="{{ asset('img/icons/android-chrome-192x192.png') }}" type="image/png" sizes="192x192" />
    <link rel="icon" href="{{ asset('img/icons/android-chrome-512x512.png') }}" type="image/png" sizes="512x512" />
    <link rel="icon" href="{{ asset('img/icons/favicon-16x16.png') }}" type="image/png" sizes="16x16" />
    <link rel="icon" href="{{ asset('img/icons/favicon-32x32.png') }}" type="image/png" sizes="32x32" />
    <link rel="apple-touch-icon" href="{{ asset('img/icons/android-chrome-192x192.png') }}">
    <link rel="manifest" href="{{ asset('build/manifest.json') }}" type="application/json">
</head>

<body class="antialdiased font-dm text-primary">
    <div id="app">
        @include('elements.mobile-menu')
        <main id='main'>
            <div class="bg-amber-500 py-2">
                <div class="container mx-auto">
                    <div class="flex justify-center  space-x-5 text-slate-700 font-semibold">
                        {{-- {{email}}</span>
                        <span>{{$phone}}</span> --}}
                        <span>Envíos gratis por compras mayores a $22.000</span>
                    </div>
                </div>
            </div>
            <nav class="bg-slate-700 border-gray-200 border-b  ">
                <div class="max-w-screen-xl flex flex-wrap items-center justify-between mx-auto py-1 xl:px-0 px-5">


                    <div class="flex items-center space-x-4 flex-row-reverse md:flex-row">
                        <a href="{{route('home')}}" class="flex items-center">
                            <img src="{{ asset('img/tuti.png') }}" class="h-14 mr-3" alt="Tuti" />
                        </a>
                        <div id="mobile-menu" class="hidden md:flex"></div>
                        <button id='openMobileMenu' class="text-white flex md:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                            </svg> <span class="text-xl pt-1 pb-1 hidden lg:block ">Menú </span>
                        </button>
                    </div>

                    <div class="xl:flex hidden items-center space-x-10">

                        <form action="{{route('search')}}" class="relative">
                            <input placeholder="Buscar Producto" value='{{request()->q}}' name='q' type="text" class='bg-[#e8e7e5] border-0 rounded-3xl w-96'>
                            <button type="submit" class="absolute right-0 top-0 bg-orange-500 p-2 rounded-3xl text-white w-10 h-10 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </button>

                        </form>



                    </div>

                    <div class="justify-end space-x-2 xl:flex hidden">

                        @auth

                        <div id='cart-widget' class="relative"></div>
                        <a class=" text-white flex items-center space-x-2" href="{{route('clients.orders.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>



                        </a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('logout')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>

                        </a>
                        @else
                        <a class=" text-white text-lg" href="{{route('form')}}">Registro</a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('register')}}">

                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 576 512" stroke-width="1" stroke="currentColor" class="w-8 h-8 fill-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M512 80c8.8 0 16 7.2 16 16l0 320c0 8.8-7.2 16-16 16L64 432c-8.8 0-16-7.2-16-16L48 96c0-8.8 7.2-16 16-16l448 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zM208 256a64 64 0 1 0 0-128 64 64 0 1 0 0 128zm-32 32c-44.2 0-80 35.8-80 80c0 8.8 7.2 16 16 16l192 0c8.8 0 16-7.2 16-16c0-44.2-35.8-80-80-80l-64 0zM376 144c-13.3 0-24 10.7-24 24s10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-80 0zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-80 0z" />
                            </svg>



                        </a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('login')}}">

                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 448 512" stroke-width="1" stroke="currentColor" class="w-8 h-8 fill-white p-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512l388.6 0c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304l-91.4 0z" />
                            </svg>


                        </a>

                        @endauth
                    </div>

                    <div class="justify-end space-x-2 xl:hidden flex">
                        @auth
                        <a class="  text-white flex items-center space-x-2" href="{{route('cart')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>


                        </a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('clients.orders.index')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>



                        </a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('logout')}}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>

                        </a>
                        @else
                        <a class=" text-white text-lg" href="{{route('form')}}">Registro</a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('register')}}">

                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 576 512" stroke-width="1" stroke="currentColor" class="w-8 h-8 fill-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M512 80c8.8 0 16 7.2 16 16l0 320c0 8.8-7.2 16-16 16L64 432c-8.8 0-16-7.2-16-16L48 96c0-8.8 7.2-16 16-16l448 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zM208 256a64 64 0 1 0 0-128 64 64 0 1 0 0 128zm-32 32c-44.2 0-80 35.8-80 80c0 8.8 7.2 16 16 16l192 0c8.8 0 16-7.2 16-16c0-44.2-35.8-80-80-80l-64 0zM376 144c-13.3 0-24 10.7-24 24s10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-80 0zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-80 0z" />
                            </svg>



                        </a>
                        <a class=" text-white flex items-center space-x-2" href="{{route('login')}}">

                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 448 512" stroke-width="1" stroke="currentColor" class="w-8 h-8 fill-white p-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512l388.6 0c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304l-91.4 0z" />
                            </svg>


                        </a>
                        @endauth
                    </div>
                </div>
            </nav>



            <div class="mx-auto max-w-7xl container xl:px-0 px-5 py-5 pt-0">
                <x-alert />
                @yield('content')
            </div>
        </main>


        @include('elements.footer')
    </div>



    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.6.5/flowbite.min.js"></script>
    <script>
        $('#closeMobileMenu').click(function() {
            $('#mobileMenu').hide();
        });
        $('#openMobileMenu').click(function() {
            $('#mobileMenu').toggle();
        });

        // Handle cart updates
        @if(session('cart_updated'))
        window.setTimeout(function() {
            document.dispatchEvent(new Event('cart:updated'));
        }, 100);
        @endif
    </script>

    @yield('scripts')

</body>


</html>