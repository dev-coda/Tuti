<div class="flex xl:hidden">
    <div class="fixed bg-white w-full h-full z-50" id='mobileMenu' style="display: none">
        <header class="border-b py-2 px-5">
            <div class="flex justify-between items-center">
                <img src="{{ asset('img/tuti.png') }}" class="h-14 mr-3" />
                <button class="text-2xl" id="closeMobileMenu">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>

                </button>
            </div>
        </header>
        <section class="p-5">
            <div class="mb-5">
                <form action="{{route('search')}}" class="relative">
                    <input placeholder="Busqueda" value='{{request()->q}}' name='q' type="text" class='bg-[#e8e7e5] border-0 rounded w-full'>
                    <svg class=" absolute right-2 top-2 text-gray-500 w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </form>
            </div>
            <div class="space-x-5 flex justify-between">

                @auth

                <a class="rounded py-1 px-2 text-white bg-secondary flex items-center space-x-2 flex-grow" href="{{route('cart')}}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>

                    <span>Carrito</span>
                </a>
                <a class="rounded py-1 px-2 text-white bg-secondary flex items-center space-x-2 flex-grow" href="{{route('clients.orders.index')}}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>


                    <span>Ordenes</span>
                </a>
                <a class="rounded py-1 px-2 text-white bg-secondary flex items-center space-x-2 flex-grow" href="{{route('logout')}}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                    <span>Salir</span>
                </a>
                @else
                <a class="rounded py-1 px-2 text-white bg-secondary" href="{{route('login')}}">Login</a>
                <a class="rounded py-1 px-2 text-white bg-secondary" href="{{route('register')}}">Acceder</a>
                <a class="rounded py-1 px-2 text-white bg-secondary" href="{{route('form')}}">Quiero ser cliente</a>
                @endauth
            </div>

            <div class="col-span-3 xl:block my-5" id="accordion-collapse" data-accordion="collapse" data-active-classes='text-gray-700'>

                @foreach ($categories as $category)

                <h2 id="accordion-collapse-heading-c{{$category->id}}">

                    <button type="button" class="bg-blue2  @if($loop->first) rounded-t @endif @if($loop->last) rounded-b @endif flex items-center justify-between w-full py-2 px-4 font-medium rtl:text-right text-gray-500  focus:ring-0 focus:ring-gray-200  gap-3" data-accordion-target="#accordion-collapse-body-c{{$category->id}}" aria-expanded="true" aria-controls="accordion-collapse-body-c{{$category->id}}">
                        <div class="flex items-center space-x-2">
                            <span class="icon-energy"></span>
                            <span>{{$category->name}}</span>
                        </div>

                        <svg data-accordion-icon class="w-3 h-3 rotate-180 shrink-0" aria-hidden="false" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5 5 1 1 5" />
                        </svg>
                    </button>
                </h2>
                <div id="accordion-collapse-body-c{{$category->id}}" class="hidden bg-blue3" aria-labelledby="accordion-collapse-heading-c{{$category->id}}">
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

            <ul class="text-xl space-y-2">
                <li>
                    <a href="{{route('form')}}">Quiero ser cliente</a>
                </li>

                <li><a href="#">Preguntas frecuentes</a></li>
                <li><a href="#">Sobre nosotros</a></li>
                <li><a href="#">Políticas de privacidad</a></li>
                <li><a href="#">Términos y condiciones</a></li>
                <li><a href="#">Contáctanos</a></li>


            </ul>

        </section>
    </div>
</div>