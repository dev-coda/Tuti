<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tuti</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/seller.css'])


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





    <!-- drawer component -->
    <div id="drawer-example" class="fixed top-0 left-0 z-40 h-screen overflow-y-auto transition-transform -translate-x-full bg-white w-80 dark:bg-gray-800" tabindex="-1" aria-labelledby="drawer-label">

        <button type="button" data-drawer-hide="drawer-example" aria-controls="drawer-example" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 absolute top-2.5 end-2.5 flex items-center justify-center dark:hover:bg-gray-600 dark:hover:text-white">
            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
            </svg>
            <span class="sr-only">Close menu</span>
        </button>

        <div class="">
            <div class="bg-gray-100">
                <div class="p-5 leading-4">
                    <h2 class="text-secondary">Tendero Flórez</h2>
                    <small class="text-gray-500">ID 000000000</small>
                </div>
            </div>
            <ul class="px-5 py-6 space-y-4">
                <li>
                    <a class="flex items-center space-x-2" href="{{route('sellers.home')}}">
                        <span class="icon-home text-blue1"></span>
                        <span class="text-gray-400">Inicio</span>
                    </a>
                </li>
                <li>
                    <a class="flex items-center space-x-2" href="{{route('sellers.orders')}}">
                        <span class="icon-list text-blue1"></span>
                        <span class="text-gray-400">Mis pedidos</span>
                    </a>
                </li>

                <li>
                    <a class="flex items-center space-x-2" href="{{route('sellers.faq')}}">
                        <span class="icon-question text-blue1"></span>
                        <span class="text-gray-400">Preguntas Frecuentes</span>
                    </a>
                </li>

                <li>
                    <a class="flex items-center space-x-2" href="{{route('sellers.contact')}}">
                        <span class="icon-phone text-blue1"></span>
                        <span class="text-gray-400">Contacto</span>
                    </a>
                </li>


                <li>
                    <a class="flex items-center space-x-2" href="{{route('logout')}}">
                        <span class="icon-logout text-blue1"></span>
                        <span class="text-gray-400">Cerrar sesión</span>
                    </a>
                </li>


            </ul>


        </div>

    </div>


    <header class="py-5 px-2 flex items-center space-x-4">

        <div>
            <button id='openMobileMenu' class="text-orange-500 flex" type="button" data-drawer-target="drawer-example" data-drawer-show="drawer-example" aria-controls="drawer-example">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" />
                </svg>
            </button>
        </div>

        <form class="grow relative">
            <svg class="w-5 h-5 text-offert absolute top-2 left-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>

            <input type="search" class="w-full rounded-full border-1 bg-gray-100 border-gray-200 px-2 py-1" />
        </form>

    </header>
    <main class="relative">
        @yield('content')
    </main>


    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.6.5/flowbite.min.js"></script>

    @yield('scripts')
</body>


</html>