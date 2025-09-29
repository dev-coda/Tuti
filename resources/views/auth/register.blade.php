@extends('layouts.page')

@section('head')
    @include('elements.seo', ['title'=>'Registro' ])
@endsection

@section('content')
<section class="w-full xl:py-14 py-10 xl:px-96 px-0">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class='text-xl font-bold text-gray-800 mb-2'>Registrate aquí</h1>
            <p class="text-gray-600 text-sm">Completa los datos para acceder al portafolio de productos</p>
        </div>
        
        {{ Aire::open()->route('register')->id('registrationForm') }}
            
            <div class='space-y-4'>
                {{ Aire::input('name', 'Nombre y Apellido')->placeholder('Nombre y Apellido')->groupClass('mb-0') }}
                
                {{ Aire::email('email', 'Correo electrónico')->placeholder('Correo electrónico')->groupClass('mb-0') }}
                
                {{ Aire::input('phone', 'Celular')->placeholder('Celular')->groupClass('mb-0') }}
                
                {{ Aire::select(['' => 'Selecciona tu ciudad'] + ($cities ?? []), 'city_id', 'Ciudad')->groupClass('mb-0') }}
                
                {{ Aire::input('document', 'Nit o cédula')->placeholder('Nit o cédula')->groupClass('mb-0') }}
                
                {{ Aire::password('password', 'Contraseña')->placeholder('Contraseña')->groupClass('mb-0') }}
                
                {{ Aire::password('password_confirmation', 'Confirmar Contraseña')->placeholder('Confirmar Contraseña')->groupClass('mb-0') }}
                
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
            </div>

            <div class="mt-6">
                <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                    Enviar solicitud
                </button>
            </div>
            
            <div class="mt-4 p-3 bg-orange-50 rounded-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-orange-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-xs text-orange-700">
  Hemos enviado tu solicitud para hacer parte de la familia de <strong>Tuti</strong>, un asesor te contactará para activar tu usuario.                        </p>
                    </div>
                </div>
            </div>
        {{ Aire::close() }}
    </div>
</section>

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
    
    $(function(){
        // Load cities on page load
        loadCities();
        
        function loadCities() {
            const url = `{{ route('cities.index') }}`;
            
            axios.get(url).then(function(response){
                const cities = response.data;
                const citySelect = $('#city_id');
                
                citySelect.empty();
                citySelect.append('<option value="">Selecciona tu ciudad</option>');
                
                cities.forEach(city => {
                    citySelect.append(`<option value="${city.id}">${city.name}</option>`);
                });
            }).catch(function(error) {
                console.error('Error loading cities:', error);
            });
        }
    });
</script>
@endsection