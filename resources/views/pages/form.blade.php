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

    <!-- Mobile Tabs Navigation -->
    <div class="xl:hidden mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex">
                <button id="login-tab" class="flex-1 py-4 px-6 text-sm font-semibold text-center border-b-2 border-blue-500 text-blue-600 bg-blue-50 transition-all duration-200">
                    <div class="flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                        </svg>
                        <span>Ingreso</span>
                    </div>
                </button>
                <button id="register-tab" class="flex-1 py-4 px-6 text-sm font-semibold text-center border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all duration-200">
                    <div class="flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                        </svg>
                        <span>Registro</span>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <div class="grid xl:grid-cols-2 grid-cols-1 gap-10 xl:gap-10 gap-0">
        <div id="login-section" class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center xl:flex xl:flex-col xl:items-center xl:justify-center">
            <div class="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">Ingreso</h2>
            <p>Ingresa aquí solo si ya estás registrado</p>

            <div class="mt-5 p-5 w-full">
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    {{ Aire::input('email')->placeholder('Correo electrónico')->groupClass('mb-0')->required() }}
                    {{ Aire::password('password')->placeholder('Contraseña')->groupClass('mb-5')->required() }}

                    <div class="flex justify-between">
                        <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">¿Olvidó su contraseña?</a>
                        {{-- <a href="{{ route('password.request') }}" class="text-sm text-blue-700 hover:underline">Olvido su contraseña?</a> --}}
                    </div>

                    {{ Aire::submit('Ingresar')->variant()->primary() }}
                </form>
            </div>
        </div>
        <div id="register-section" class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center xl:flex xl:flex-col xl:items-center xl:justify-center hidden">
            <h2 class="text-2xl font-bold">Regístrate en TUTI</h2>
            <p class="text-center mb-4">Diligencia el formulario e inicia el proceso de activación como cliente TUTI</p>

            <div class="mt-5 p-5 w-full">
                {{ Aire::open()->route('form')->post()->addClass('space-y-4') }}
                
                {{ Aire::input('reg_name')->placeholder('Responsable de compra (persona natural o jurídica)')->groupClass('mb-0')->required() }}
                
                {{ Aire::input('reg_nit')->placeholder('Cédula o NIT')->groupClass('mb-0')->required() }}
                
                {{ Aire::email('reg_email')->placeholder('Correo electrónico')->groupClass('mb-0')->required() }}
                
                {{ Aire::input('reg_phone')->placeholder('Celular')->groupClass('mb-0')->required() }}
                
                {{ Aire::select($cities ?? [], 'reg_city_id')->groupClass('mb-0')->required() }}
                
                <!-- Colombian Address Fields -->
                <div class="mt-4 space-y-4 border-t pt-4">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Dirección</h3>
                    
                    <!-- Tipo de vía y Número de vía -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="address_street_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de vía *</label>
                            <select id="address_street_type" name="address_street_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Seleccione</option>
                                <option value="Calle">Calle</option>
                                <option value="Carrera">Carrera</option>
                                <option value="Avenida">Avenida</option>
                                <option value="Diagonal">Diagonal</option>
                                <option value="Transversal">Transversal</option>
                                <option value="Circular">Circular</option>
                                <option value="Vía">Vía</option>
                                <option value="Autopista">Autopista</option>
                                <option value="Boulevard">Boulevard</option>
                                <option value="Pasaje">Pasaje</option>
                                <option value="Peatonal">Peatonal</option>
                                <option value="Glorieta">Glorieta</option>
                                <option value="Variante">Variante</option>
                                <option value="Kilómetro">Kilómetro</option>
                                <option value="Vereda">Vereda</option>
                                <option value="Camino">Camino</option>
                                <option value="Carretera">Carretera</option>
                            </select>
                        </div>
                        <div>
                            <label for="address_street_number" class="block text-sm font-medium text-gray-700 mb-1">Número de vía *</label>
                            <input type="text" id="address_street_number" name="address_street_number" pattern="[0-9]*" inputmode="numeric" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: 15" onkeypress="return /[0-9]/i.test(event.key)" required>
                        </div>
                        <div>
                            <label for="address_street_complement" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                            <select id="address_street_complement" name="address_street_complement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Ninguno</option>
                                <option value="Bis">Bis</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                                <option value="F">F</option>
                                <option value="G">G</option>
                                <option value="H">H</option>
                                <option value="Este">Este</option>
                                <option value="Oeste">Oeste</option>
                                <option value="Norte">Norte</option>
                                <option value="Sur">Sur</option>
                                <option value="Sur Este">Sur Este</option>
                                <option value="Sur Oeste">Sur Oeste</option>
                                <option value="Norte Este">Norte Este</option>
                                <option value="Norte Oeste">Norte Oeste</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Número de placa y Complemento de placa -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="address_house_number" class="block text-sm font-medium text-gray-700 mb-1">Número de placa *</label>
                            <input type="text" id="address_house_number" name="address_house_number" pattern="[0-9]*" inputmode="numeric" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: 45" onkeypress="return /[0-9]/i.test(event.key)" required>
                        </div>
                        <div>
                            <label for="address_house_complement" class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                            <input type="text" id="address_house_complement" name="address_house_complement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: Apto 301, Casa 2">
                        </div>
                    </div>
                    
                    <!-- Referencias adicionales -->
                    <div>
                        <label for="address_references" class="block text-sm font-medium text-gray-700 mb-1">Referencias adicionales</label>
                        <textarea id="address_references" name="address_references" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: Cerca al parque, edificio azul"></textarea>
                    </div>
                    
                    <!-- Hidden field to store the constructed address -->
                    <input type="hidden" id="reg_address" name="reg_address">
                </div>
                
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

                <div class="mt-6">
                    <button id="submit-request" type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-orange-500" disabled>
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
                                Hemos enviado tu solicitud para hacer parte de la familia de <strong>Tuti</strong>, un asesor te contactará para activar tu usuario.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>




    </div>
</div>

@endsection

@section('scripts')
<script>

    // Tab functionality for mobile
    document.addEventListener('DOMContentLoaded', function() {
        const termsAccepted = document.getElementById('terms_accepted');
        const submitRequest = document.getElementById('submit-request');

        if (termsAccepted && submitRequest) {
            const syncSubmitState = () => {
                submitRequest.disabled = !termsAccepted.checked;
            };
            syncSubmitState();
            termsAccepted.addEventListener('change', syncSubmitState);
        }

        // Construct Colombian address string from form fields
        function constructAddress() {
            const streetType = document.getElementById('address_street_type')?.value || '';
            const streetNumber = document.getElementById('address_street_number')?.value || '';
            const streetComplement = document.getElementById('address_street_complement')?.value || '';
            const houseNumber = document.getElementById('address_house_number')?.value || '';
            const houseComplement = document.getElementById('address_house_complement')?.value || '';
            const references = document.getElementById('address_references')?.value || '';

            const addressParts = [];

            // Build main address: Tipo de vía + Número + Complemento
            if (streetType && streetNumber) {
                let mainAddress = `${streetType} ${streetNumber}`;
                if (streetComplement) {
                    mainAddress += ` ${streetComplement}`;
                }
                addressParts.push(mainAddress);
            }

            // Add house number
            if (houseNumber) {
                let housePart = `# ${houseNumber}`;
                if (houseComplement) {
                    housePart += ` ${houseComplement}`;
                }
                addressParts.push(housePart);
            }

            // Add references
            if (references) {
                addressParts.push(`Ref: ${references}`);
            }

            const fullAddress = addressParts.join(', ');
            const addressField = document.getElementById('reg_address');
            if (addressField) {
                addressField.value = fullAddress;
            }

            return fullAddress;
        }

        // Update address on any address field change
        const addressFields = [
            'address_street_type',
            'address_street_number',
            'address_street_complement',
            'address_house_number',
            'address_house_complement',
            'address_references'
        ];

        addressFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('change', constructAddress);
                field.addEventListener('input', constructAddress);
            }
        });

        // Construct address before form submission
        const submitButton = document.getElementById('submit-request');
        if (submitButton) {
            const form = submitButton.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    constructAddress();
                });
            }
        }

        const loginTab = document.getElementById('login-tab');
        const registerTab = document.getElementById('register-tab');
        const loginSection = document.getElementById('login-section');
        const registerSection = document.getElementById('register-section');

        function switchToLogin() {
            // Update tab appearance
            loginTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
            loginTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            registerTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
            registerTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            
            // Show/hide sections only on mobile
            if (window.innerWidth < 1280) { // xl breakpoint
                loginSection.classList.remove('hidden');
                registerSection.classList.add('hidden');
            }
        }

        function switchToRegister() {
            // Update tab appearance
            registerTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
            registerTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            loginTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
            loginTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            
            // Show/hide sections only on mobile
            if (window.innerWidth < 1280) { // xl breakpoint
                registerSection.classList.remove('hidden');
                loginSection.classList.add('hidden');
            }
        }

        // Add event listeners
        if (loginTab && registerTab) {
            loginTab.addEventListener('click', switchToLogin);
            registerTab.addEventListener('click', switchToRegister);
            
            // Set default state - login tab active on mobile
            if (window.innerWidth < 1280) { // xl breakpoint
                switchToLogin();
            }
            
            // Handle window resize to ensure proper layout
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1280) { // Desktop
                    // Show both sections on desktop
                    loginSection.classList.remove('hidden');
                    registerSection.classList.remove('hidden');
                } else { // Mobile
                    // Apply current tab state on mobile
                    if (loginTab.classList.contains('border-blue-500')) {
                        switchToLogin();
                    } else if (registerTab.classList.contains('border-blue-500')) {
                        switchToRegister();
                    } else {
                        switchToLogin(); // Default to login
                    }
                }
            });
        }
    });
</script>
@endsection