@extends('layouts.page')

@section('content')
<section class="container mx-auto max-w-xl py-12">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8">
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
        </div>
        <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">Cambia tu contraseña</h1>
        <p class="text-gray-600 text-center mb-6">
            Por seguridad, debes crear una contraseña personal antes de continuar.
        </p>

        <form action="{{ route('password.forced-change.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña *</label>
                <input type="password" name="password" id="password" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="Mínimo 8 caracteres">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña *</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="Repite tu contraseña">
            </div>
            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                Guardar y continuar
            </button>
        </form>
    </div>
</section>
@endsection
