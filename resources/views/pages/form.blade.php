@extends('layouts.page')


@section('head')
    @include('elements.seo', [
        'title'=>'¿Quieres ser cliente de TUTI?', 
        'description'=> '¿Quieres ser cliente de TUTI?'
        ])
@endsection



@section('content')
    

<div class="max-w-5xl container mx-auto xl:space-y-10 space-y-0 mt-5 mb-20">
    <h1 class="xl:text-4xl text-2xl font-bold  text-center">¿Quieres ser cliente de TUTI?</h1>

    <div class="grid xl:grid-cols-2 grid-cols-1 gap-10">
        <div class="xl:order-1 order-2 space-y-5">
            <p>Déjanos tus datos y un asesor de nuestro equipo se contactará contigo para continuar el proceso</p>



            {{ Aire::open()->route('form')->post()->addClass('space-y-5')}}
                {{ Aire::input('name', 'Nombres y Apellidos')->groupClass('mb-0')->required() }}
                {{ Aire::email('email', 'Correo electrónico')->groupClass('mb-5')->required() }}
                {{ Aire::input('phone', 'Celular')->groupClass('mb-5')->required() }}
                {{ Aire::input('city', 'Ciudad')->groupClass('mb-5')->required() }}
                {{ Aire::input('nit', 'Nit o Cédula')->groupClass('mb-5')->required() }}
                {{ Aire::input('business_name', 'Nombre de tu tienda')->groupClass('mb-5')->required() }}                

                {{ Aire::submit('Quiero ser cliente')->variant()->primary() }}
            {{ Aire::close() }}


        </div>


        <div class="xl:order-2 order-1">
            <p class="xl:p-10 p-0">
                <img src="{{asset('img/tendera.png')}}" alt="">
            </p>
        </div>
    </div>
</div>


@endsection