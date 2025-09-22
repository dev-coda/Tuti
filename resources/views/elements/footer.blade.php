{{-- <section class="bg-gray-400 py-10 mt-10">
    <div class="container mx-auto max-w-screen-xl" >
        
        <div class="w-full flex justify-center flex-col items-center text-dark">
            <p>Banner invitando a registrarse</p>
            <p>O hablando del proceso de pedido</p>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-20 h-20 ">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
        </div>
        
        
    </div>
</section> --}}
<div class="bg-slate-700 py-10 mt-10 text-white">
    <div class="container mx-auto max-w-7xl">
        <div class="grid xl:grid-cols-4 grid-cols-1 pb-10 px-5 xl:gap-y-0 gap-y-5">
            <div class="xl:col-span-1 col-span-1">
                <div class="flex xl:justify-start justify-center  ">
                    <div class="flex flex-col items-center">
                        <a href="">
                            <img src="{{asset('img/tuti.png')}}" class="h-28">
                        </a>

                    </div>
                </div>
            </div>
            <div class="flex md:flex-start flex-col items-center gap-3 justify-center md:items-start">
                <div class="flex flex-col items-center gap-3 justify-center">
                    <div class="flex items-center justify-center gap-3">
                        <a href="https://www.facebook.com/tuti.tutienda" target="_blank" class="flex h-8 w-8">
                            <svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;" version="1.1" viewBox="0 0 512 512" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <path d="M512,257.555c0,-141.385 -114.615,-256 -256,-256c-141.385,0 -256,114.615 -256,256c0,127.777 93.616,233.685 216,252.89l0,-178.89l-65,0l0,-74l65,0l0,-56.4c0,-64.16 38.219,-99.6 96.695,-99.6c28.009,0 57.305,5 57.305,5l0,63l-32.281,0c-31.801,0 -41.719,19.733 -41.719,39.978l0,48.022l71,0l-11.35,74l-59.65,0l0,178.89c122.385,-19.205 216,-125.113 216,-252.89Z" style="fill-rule:nonzero; fill:white;" />
                            </svg>
                        </a>

                        <a href="https://www.instagram.com/tuti.tutienda/" target="_blank" class="flex h-10 w-10">
                            <svg viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
                                <rect fill="none" height="256" width="256" />
                                <circle style="fill-rule:nonzero; fill:white;" cx="128" cy="128" r="32" />
                                <path d="M172,28H84A56,56,0,0,0,28,84v88a56,56,0,0,0,56,56h88a56,56,0,0,0,56-56V84A56,56,0,0,0,172,28ZM128,176a48,48,0,1,1,48-48A48,48,0,0,1,128,176Zm52-88a12,12,0,1,1,12-12A12,12,0,0,1,180,88Z" style="fill-rule:nonzero; fill:white;" />
                            </svg>
                        </a>
                    </div>
                    <div>Síguenos en redes sociales</div>
                </div>

            </div>
            <div class="flex flex-col items-center gap-3 justify-center md:items-start">
                <h3 class="text-xl mb-3">NOSOTROS</h3>
                <ul class="text-sm space-y-2">
                    <li><a href="{{ route('content.terms') }}" class="hover:text-gray-300 transition-colors">Términos y condiciones</a></li>
                    <li><a href="{{ route('content.privacy') }}" class="hover:text-gray-300 transition-colors">Políticas de privacidad</a></li>
                    <li><a href="{{route('form')}}" class="hover:text-gray-300 transition-colors">Contacto</a></li>
                </ul>
            </div>

            <div class="flex flex-col items-center gap-3 justify-center md:items-start">
                <h3 class="text-xl mb-3">PÁGINAS DE INTERÉS</h3>
                <ul class="text-sm space-y-2">
                    <li><a href="{{route('register')}}" class="hover:text-gray-300 transition-colors">Registro</a></li>
                    <li><a href="{{route('form')}}" class="hover:text-gray-300 transition-colors">Quiero ser cliente</a></li>
                    <li><a href="{{ route('content.faq') }}" class="hover:text-gray-300 transition-colors">Preguntas frecuentes</a></li>
                </ul>
            </div>


        </div>

    </div>
</div>