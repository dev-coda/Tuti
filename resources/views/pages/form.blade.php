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
                        <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">¿Olvidó su contraseña?</a>
                        {{-- <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">Olvido su contraseña?</a> --}}
                    </div>

                    {{ Aire::submit('Ingresar')->variant()->primary() }}
                </form>
            </div>
        </div>
        <div class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center">
            <h2 class="text-2xl font-bold">Registrate aquí</h2>
            <p class="text-center mb-4">Completa los datos para acceder al portafolio de productos</p>

            <div class="mt-5 p-5 w-full">
                {{ Aire::open()->route('form')->post()->addClass('space-y-4') }}
                
                {{ Aire::input('name', 'Nombre y Apellido')->placeholder('Nombre y Apellido')->groupClass('mb-0')->required() }}
                
                {{ Aire::email('email', 'Correo electrónico')->placeholder('Correo electrónico')->groupClass('mb-0')->required() }}
                
                {{ Aire::input('phone', 'Celular')->placeholder('Celular')->groupClass('mb-0')->required() }}
                
                {{ Aire::select($cities ?? [], 'city_id', 'Ciudad')->groupClass('mb-0')->required() }}
                
                {{ Aire::input('nit', 'Nit o cédula')->placeholder('Nit o cédula')->groupClass('mb-0')->required() }}
                
                <div class="flex items-start mt-4">
                    <div class="flex items-center h-5">
                        <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1" class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-blue-300" required>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms_accepted" class="text-gray-700">
                            Acepto términos y condiciones
                            <a href="#" class="text-blue-600 hover:underline ml-1" onclick="showTermsModal()">Ver términos y condiciones aquí</a>
                        </label>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                        Enviar solicitud
                    </button>
                </div>
                
                {{ Aire::close() }}
                
                <div class="mt-4 p-3 bg-orange-50 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-orange-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-xs text-orange-700">
                                Recuerda que será necesario comunicarse con un miembro de la familia de <strong>Tuti</strong>, un asesor comercial para activar tu usuario.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>




    </div>
</div>

<!-- Terms and Conditions Modal -->
<div id="termsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Términos y Condiciones</h3>
                <button onclick="hideTermsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="text-sm text-gray-600 max-h-96 overflow-y-auto">
                <p class="mb-4">Al registrarse en nuestra plataforma, usted acepta los siguientes términos y condiciones:</p>
                <ol class="list-decimal list-inside space-y-2">
                    <li>La información proporcionada debe ser veraz y actualizada.</li>
                    <li>Se compromete a mantener la confidencialidad de sus credenciales de acceso.</li>
                    <li>El uso de la plataforma debe ser para fines comerciales legítimos.</li>
                    <li>Nos reservamos el derecho de suspender cuentas que violen estos términos.</li>
                    <li>Los precios y disponibilidad de productos están sujetos a cambios sin previo aviso.</li>
                    <li>La activación de la cuenta requiere aprobación del equipo comercial de Tuti.</li>
                </ol>
                <p class="mt-4 text-xs text-gray-500">Última actualización: {{ date('d/m/Y') }}</p>
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="hideTermsModal()" class="px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    function showTermsModal() {
        document.getElementById('termsModal').classList.remove('hidden');
    }
    
    function hideTermsModal() {
        document.getElementById('termsModal').classList.add('hidden');
    }
    
    // Close modal when clicking outside of it
    document.getElementById('termsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideTermsModal();
        }
    });
</script>
@endsection