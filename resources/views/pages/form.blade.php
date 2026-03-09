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
                <form method="POST" action="{{ route('login') }}" class="space-y-5" id="login-form">
                    @csrf
                    <div>
                        <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                        <input type="email" id="login-email" name="email" placeholder="Correo electrónico" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required value="{{ old('email') }}">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                        <input type="password" id="login-password" name="password" placeholder="Ingresa tu contraseña" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                    </div>

                    <div class="flex justify-between">
                        <a href="{{ route('password.request') }}" class="text-sm text-orange-600 hover:underline">¿Olvidó su contraseña?</a>
                    </div>

                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        <span>Ingresar</span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 uppercase tracking-wider text-xs font-semibold">O bien</span>
                    </div>
                </div>

                <!-- Magic Link Button -->
                <button type="button" id="magic-link-btn" class="w-full border-2 border-gray-300 hover:border-orange-400 text-gray-700 hover:text-orange-600 font-semibold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 magic-link-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" />
                    </svg>
                    <svg class="animate-spin h-5 w-5 magic-link-spinner hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="magic-link-text">Ingresar sin contraseña</span>
                </button>
                <p class="text-center text-xs text-gray-500 mt-2" id="magic-link-hint">Te enviaremos un código de verificación a tu correo.</p>
                <p class="text-center text-xs text-red-600 mt-2 hidden" id="magic-link-error"></p>
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
                            <label for="address_house_number" class="block text-sm font-medium text-gray-700 mb-1">Número *</label>
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

<!-- Magic Link Verification Modal -->
<div id="magic-link-modal" class="fixed inset-0 z-50 hidden">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="magic-modal-backdrop"></div>
    
    <!-- Modal Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative transform transition-all" id="magic-modal-content">
            <!-- Close Button -->
            <button type="button" id="magic-modal-close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Icon -->
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                </div>
            </div>

            <!-- Title -->
            <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Ingreso sin contraseña</h3>
            <p class="text-center text-sm text-gray-500 mb-1">Hemos enviado un código de verificación a</p>
            <p class="text-center text-sm font-semibold text-gray-700 mb-6" id="magic-modal-email"></p>

            <!-- Code Input -->
            <div class="mb-4">
                <label for="magic-code-input" class="block text-sm font-medium text-gray-700 mb-2">Código de verificación</label>
                <input 
                    type="text" 
                    id="magic-code-input" 
                    maxlength="6" 
                    placeholder="000000" 
                    inputmode="numeric"
                    pattern="[0-9]*"
                    class="w-full text-center text-2xl tracking-[0.5em] font-mono py-3 px-4 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    autocomplete="one-time-code"
                >
                <p id="magic-code-error" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>

            <!-- Verify Button -->
            <button type="button" id="magic-verify-btn" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Verificar e ingresar
            </button>

            <!-- Resend Link -->
            <div class="text-center mt-4">
                <button type="button" id="magic-resend-btn" class="text-sm text-orange-600 hover:text-orange-700 hover:underline font-medium">
                    Reenviar código
                </button>
                <p id="magic-resend-timer" class="text-sm text-gray-400 hidden"></p>
            </div>

            <!-- Loading State -->
            <div id="magic-loading" class="hidden absolute inset-0 bg-white bg-opacity-80 rounded-xl flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <svg class="animate-spin h-8 w-8 text-orange-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-gray-500">Procesando...</span>
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

        // =============================================
        // Magic Link Login Functionality
        // =============================================
        const magicLinkBtn = document.getElementById('magic-link-btn');
        const magicLinkIcon = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-icon') : null;
        const magicLinkSpinner = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-spinner') : null;
        const magicLinkTextEl = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-text') : null;
        const magicLinkHint = document.getElementById('magic-link-hint');
        const magicLinkError = document.getElementById('magic-link-error');
        const magicModal = document.getElementById('magic-link-modal');
        const magicModalClose = document.getElementById('magic-modal-close');
        const magicModalBackdrop = document.getElementById('magic-modal-backdrop');
        const magicModalEmail = document.getElementById('magic-modal-email');
        const magicCodeInput = document.getElementById('magic-code-input');
        const magicCodeError = document.getElementById('magic-code-error');
        const magicVerifyBtn = document.getElementById('magic-verify-btn');
        const magicResendBtn = document.getElementById('magic-resend-btn');
        const magicResendTimer = document.getElementById('magic-resend-timer');
        const magicLoading = document.getElementById('magic-loading');
        const loginEmailInput = document.getElementById('login-email');

        let magicLinkEmail = '';
        let resendCooldown = null;

        // CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        // Show/hide loading state on the magic link BUTTON (visible to user)
        function setMagicBtnLoading(loading) {
            if (!magicLinkBtn) return;
            if (loading) {
                magicLinkBtn.disabled = true;
                magicLinkBtn.classList.add('opacity-60', 'cursor-not-allowed');
                if (magicLinkIcon) magicLinkIcon.classList.add('hidden');
                if (magicLinkSpinner) magicLinkSpinner.classList.remove('hidden');
                if (magicLinkTextEl) magicLinkTextEl.textContent = 'Enviando código...';
            } else {
                magicLinkBtn.disabled = false;
                magicLinkBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                if (magicLinkIcon) magicLinkIcon.classList.remove('hidden');
                if (magicLinkSpinner) magicLinkSpinner.classList.add('hidden');
                if (magicLinkTextEl) magicLinkTextEl.textContent = 'Ingresar sin contraseña';
            }
        }

        // Show an error message BELOW the magic link button (visible to user)
        function showBtnError(message) {
            if (magicLinkError) {
                magicLinkError.textContent = message;
                magicLinkError.classList.remove('hidden');
            }
            if (magicLinkHint) magicLinkHint.classList.add('hidden');
        }

        function hideBtnError() {
            if (magicLinkError) magicLinkError.classList.add('hidden');
            if (magicLinkHint) magicLinkHint.classList.remove('hidden');
        }

        // Loading/error inside the modal (for verify flow)
        function showMagicLoading(show) {
            if (show) {
                magicLoading.classList.remove('hidden');
            } else {
                magicLoading.classList.add('hidden');
            }
        }

        function showMagicError(message) {
            magicCodeError.textContent = message;
            magicCodeError.classList.remove('hidden');
            magicCodeInput.classList.add('border-red-500');
        }

        function hideMagicError() {
            magicCodeError.classList.add('hidden');
            magicCodeInput.classList.remove('border-red-500');
        }

        function openMagicModal() {
            magicModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            magicCodeInput.value = '';
            magicVerifyBtn.disabled = true;
            hideMagicError();
            setTimeout(() => magicCodeInput.focus(), 100);
        }

        function closeMagicModal() {
            magicModal.classList.add('hidden');
            document.body.style.overflow = '';
            if (resendCooldown) {
                clearInterval(resendCooldown);
                resendCooldown = null;
            }
        }

        function startResendCooldown() {
            let seconds = 60;
            magicResendBtn.classList.add('hidden');
            magicResendTimer.classList.remove('hidden');
            magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';

            resendCooldown = setInterval(() => {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(resendCooldown);
                    resendCooldown = null;
                    magicResendBtn.classList.remove('hidden');
                    magicResendTimer.classList.add('hidden');
                } else {
                    magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';
                }
            }, 1000);
        }

        async function sendMagicCode(email, showOnBtn) {
            if (showOnBtn) {
                setMagicBtnLoading(true);
                hideBtnError();
            } else {
                showMagicLoading(true);
            }
            try {
                const response = await fetch('{{ route("magic-link.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ email: email }),
                });

                const data = await response.json();
                
                if (!response.ok) {
                    const errMsg = data.message || 'Error al enviar el código. Intenta de nuevo.';
                    if (showOnBtn) {
                        showBtnError(errMsg);
                    } else {
                        showMagicError(errMsg);
                    }
                    return false;
                }

                return true;
            } catch (error) {
                console.error('Error sending magic code:', error);
                const errMsg = 'Error de conexión. Por favor intenta de nuevo.';
                if (showOnBtn) {
                    showBtnError(errMsg);
                } else {
                    showMagicError(errMsg);
                }
                return false;
            } finally {
                if (showOnBtn) {
                    setMagicBtnLoading(false);
                } else {
                    showMagicLoading(false);
                }
            }
        }

        async function verifyMagicCode(email, code) {
            showMagicLoading(true);
            hideMagicError();
            try {
                const response = await fetch('{{ route("magic-link.verify") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ email: email, code: code }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Redirect on success
                    window.location.href = data.redirect || '/';
                    return;
                }

                showMagicError(data.message || 'Código inválido o expirado.');
            } catch (error) {
                console.error('Error verifying magic code:', error);
                showMagicError('Error de conexión. Por favor intenta de nuevo.');
            } finally {
                showMagicLoading(false);
            }
        }

        // Magic Link Button Click
        if (magicLinkBtn) {
            magicLinkBtn.addEventListener('click', async function() {
                const email = loginEmailInput?.value?.trim();
                
                if (!email || !email.includes('@')) {
                    // Focus and highlight the email field, show error near button
                    loginEmailInput.focus();
                    loginEmailInput.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                    showBtnError('Por favor ingresa tu correo electrónico arriba.');
                    setTimeout(() => {
                        loginEmailInput.classList.remove('border-red-500', 'ring-2', 'ring-red-200');
                    }, 3000);
                    return;
                }

                hideBtnError();
                magicLinkEmail = email;
                magicModalEmail.textContent = email;

                const sent = await sendMagicCode(email, true);
                if (sent) {
                    openMagicModal();
                    startResendCooldown();
                }
            });
        }

        // Code Input - Enable verify button when 6 digits entered
        if (magicCodeInput) {
            magicCodeInput.addEventListener('input', function() {
                // Only allow digits
                this.value = this.value.replace(/[^0-9]/g, '');
                magicVerifyBtn.disabled = this.value.length !== 6;
                if (this.value.length > 0) {
                    hideMagicError();
                }
            });

            // Auto-submit on 6 digits
            magicCodeInput.addEventListener('keyup', function(e) {
                if (this.value.length === 6 && e.key !== 'Enter') {
                    magicVerifyBtn.click();
                }
            });

            // Handle Enter key
            magicCodeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.length === 6) {
                    e.preventDefault();
                    magicVerifyBtn.click();
                }
            });
        }

        // Verify Button Click
        if (magicVerifyBtn) {
            magicVerifyBtn.addEventListener('click', function() {
                const code = magicCodeInput.value.trim();
                if (code.length === 6 && magicLinkEmail) {
                    verifyMagicCode(magicLinkEmail, code);
                }
            });
        }

        // Resend Button Click
        if (magicResendBtn) {
            magicResendBtn.addEventListener('click', async function() {
                if (magicLinkEmail) {
                    magicCodeInput.value = '';
                    magicVerifyBtn.disabled = true;
                    hideMagicError();
                    const sent = await sendMagicCode(magicLinkEmail, false);
                    if (sent) {
                        startResendCooldown();
                    }
                }
            });
        }

        // Close Modal
        if (magicModalClose) {
            magicModalClose.addEventListener('click', closeMagicModal);
        }
        if (magicModalBackdrop) {
            magicModalBackdrop.addEventListener('click', closeMagicModal);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !magicModal.classList.contains('hidden')) {
                closeMagicModal();
            }
        });
    });
</script>
@endsection