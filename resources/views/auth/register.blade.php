@extends('layouts.page')

@section('head')
    @include('elements.seo', ['title'=>'Registro' ])
@endsection

@section('content')
<section class="w-full xl:py-14 py-10 xl:px-96 px-0">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class='text-xl font-bold text-gray-800 mb-2'>Regístrate en TUTI</h1>
            <p class="text-gray-600 text-sm">Diligencia el formulario e inicia el proceso de activación como cliente TUTI</p>
        </div>
        
        {{ Aire::open()->route('register')->id('registrationForm') }}
            
            <div class='space-y-4'>
                {{ Aire::input('name')->placeholder('Responsable de compra (persona natural o jurídica)')->groupClass('mb-0') }}
                
                {{ Aire::input('document')->placeholder('Cédula o NIT')->groupClass('mb-0') }}
                
                {{ Aire::email('email')->placeholder('Correo electrónico')->groupClass('mb-0') }}
                
                {{ Aire::input('phone')->placeholder('Celular')->groupClass('mb-0') }}
                
                {{ Aire::select($cities ?? [], 'city_id')->groupClass('mb-0') }}
                
                {{ Aire::password('password')->placeholder('Contraseña')->groupClass('mb-0') }}
                
                {{ Aire::password('password_confirmation')->placeholder('Confirmar Contraseña')->groupClass('mb-0') }}
                
                <div class="flex items-start mt-4">
                    <div class="flex items-center h-5">
                        <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1" class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-blue-300" required>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms_accepted" class="text-gray-700">
                            Acepto términos y condiciones
                            <a href="{{ route('content.terms') }}" target="_blank" class="text-blue-600 hover:underline ml-1">Ver términos y condiciones aquí</a>
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

@endsection

@section('scripts')
<script>
    
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