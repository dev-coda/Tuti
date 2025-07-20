@extends('layouts.page')


@section('head')
@include('elements.seo', [
'title'=>'¿Quieres ser cliente de TUTI?',
'description'=> '¿Quieres ser cliente de TUTI?'
])
@include('elements.seo', [
'title'=>'¿Quieres ser cliente de TUTI?',
'description'=> '¿Quieres ser cliente de TUTI?'
])
@endsection



@section('content')


<div class="max-w-5xl container mx-auto xl:space-y-10 space-y-0 mt-5 mb-20">
    <h1 class="xl:text-4xl text-2xl font-bold  text-center">Bienvenido Tendero</h1>

    <div class="grid xl:grid-cols-2 grid-cols-1 gap-10">
        <div class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center">
            <div class="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">Ingreso</h2>
            <p>Ingresa tus credenciales para acceder</p>

            <div class="mt-5 p-5 w-full">
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    {{ Aire::input('email', 'Email')->groupClass('mb-0')->required() }}
                    {{ Aire::password('password', 'Contraseña')->groupClass('mb-5')->required() }}

                    <div class="flex justify-between">
                        <a href="{{ route('register') }}" class="text-sm text-blue-700 hover:underline">Crear cuenta</a>
                        {{-- <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">Olvido su contraseña?</a> --}}
                    </div>

                    {{ Aire::submit('Ingresar')->variant()->primary() }}
                </form>
            </div>
        </div>
        <div class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center">
            <h2 class="text-2xl font-bold">Registrate Aquí</h2>
            <p>Completa los datos para acceder al portafolio de productos</p>


            <div class="mt-5 p-5 w-full">
                {{ Aire::open()->route('form')->post()->addClass('space-y-5') }}
                {{ Aire::input('name', 'Nombres y Apellidos')->groupClass('mb-0')->required() }}
                {{ Aire::email('email', 'Correo electrónico')->groupClass('mb-5')->required() }}
                {{ Aire::input('phone', 'Celular')->groupClass('mb-5')->required() }}
                {{ Aire::input('city', 'Ciudad')->groupClass('mb-5')->required() }}
                {{ Aire::input('nit', 'Nit o Cédula')->groupClass('mb-5')->required() }}
                {{ Aire::input('business_name', 'Nombre de tu tienda')->groupClass('mb-5')->required() }}

                {{ Aire::submit('Quiero ser cliente')->variant()->primary() }}
                {{ Aire::close() }}
            </div>



        </div>




    </div>
</div>


@endsection