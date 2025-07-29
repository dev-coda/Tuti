@extends('layouts.page')

@section('content')
<div class="xl:px-96 px-0">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
        Recuperar Contraseña
    </h2>
    
    <div class="mb-4 text-sm text-gray-600 mt-4">
        ¿Olvidó su contraseña? No hay problema. Ingrese su dirección de correo electrónico y le enviaremos un enlace para restablecer su contraseña.
    </div>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form class="mt-8 space-y-6" method="POST" action="{{ route('password.email') }}">
        @csrf

        {{ Aire::input('email', 'Email')->value(old('email')) }}

        {{ Aire::submit('Enviar Enlace de Recuperación')->addClass('font-bold')->variant()->submit() }}
        
        <div class="mt-4">
            <a href="{{ route('form') }}" class="text-sm text-blue-700 hover:underline">
                ← Volver al inicio de sesión
            </a>
        </div>
    </form>
</div>
@endsection
