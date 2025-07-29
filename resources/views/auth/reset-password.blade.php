@extends('layouts.page')

@section('content')
<div class="xl:px-96 px-0">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
        Restablecer Contraseña
    </h2>
    
    <form class="mt-8 space-y-6" method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        {{ Aire::input('email', 'Email')->value(old('email', $request->email))->readonly() }}

        {{ Aire::password('password', 'Nueva Contraseña') }}

        {{ Aire::password('password_confirmation', 'Confirmar Nueva Contraseña') }}

        {{ Aire::submit('Restablecer Contraseña')->addClass('font-bold')->variant()->submit() }}
        
        <div class="mt-4">
            <a href="{{ route('form') }}" class="text-sm text-blue-700 hover:underline">
                ← Volver al inicio de sesión
            </a>
        </div>
    </form>
</div>
@endsection
