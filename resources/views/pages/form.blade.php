@extends('layouts.page')


@section('head')
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

                <!-- Tronex existing client -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 uppercase tracking-wider text-xs font-semibold">O bien</span>
                    </div>
                </div>
                <button type="button" id="tronex-btn" class="w-full border-2 border-blue-200 hover:border-blue-400 text-blue-700 hover:text-blue-900 font-semibold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2 bg-blue-50 hover:bg-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>¿Ya eres cliente Tronex?</span>
                </button>
                <p class="text-center text-xs text-gray-500 mt-2">Si ya compras con Tronex, ingresa tu cédula para crear tu cuenta Tuti.</p>
            </div>
        </div>
        <div id="register-section" class="border border-3 border-blue-900 p-5 rounded-lg flex flex-col items-center justify-center xl:flex xl:flex-col xl:items-center xl:justify-center hidden">
            <h2 class="text-2xl font-bold">Regístrate en TUTI</h2>
            <p class="text-center mb-4">Diligencia el formulario e inicia el proceso de activación como cliente TUTI</p>

            <!-- Existing client alert -->
            <div id="existing-client-alert" class="hidden w-full mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-yellow-800">
                            Este número de identificación o correo ya se encuentra registrado en nuestra plataforma. Ya cuentas con usuario y contraseña para comprar en TUTI.
                        </p>
                        <p class="text-sm text-yellow-700 mt-1">
                            Por favor inicia sesión con tus credenciales existentes.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-2 p-5 w-full">
                <form action="{{ route('form_post') }}" method="POST" enctype="multipart/form-data" class="space-y-4" id="registration-form">
                    @csrf

                    <!-- Person Type Selector -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de persona</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer transition-all duration-200 person-type-option border-blue-500 bg-blue-50" data-value="natural">
                                <input type="radio" name="reg_person_type" value="natural" class="sr-only" checked>
                                <span class="text-sm font-semibold text-blue-700">Persona Natural</span>
                            </label>
                            <label class="relative flex items-center justify-center p-3 border-2 rounded-lg cursor-pointer transition-all duration-200 person-type-option border-gray-300 bg-white" data-value="juridica">
                                <input type="radio" name="reg_person_type" value="juridica" class="sr-only">
                                <span class="text-sm font-semibold text-gray-600">Persona Jurídica</span>
                            </label>
                        </div>
                        @error('reg_person_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Cédula o NIT -->
                    <div>
                        <label for="reg_nit" class="block text-sm font-medium text-gray-700 mb-1" id="nit-label">Cédula</label>
                        <input type="text" name="reg_nit" id="reg_nit" placeholder="Número de cédula" value="{{ old('reg_nit') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        @error('reg_nit')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nombre / Razón social -->
                    <div>
                        <label for="reg_name" class="block text-sm font-medium text-gray-700 mb-1" id="name-label">Nombre completo</label>
                        <input type="text" name="reg_name" id="reg_name" placeholder="Nombre completo" value="{{ old('reg_name') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        @error('reg_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="reg_email" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                        <input type="email" name="reg_email" id="reg_email" placeholder="Correo electrónico" value="{{ old('reg_email') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        @error('reg_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Celular -->
                    <div>
                        <label for="reg_phone" class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                        <input type="tel" name="reg_phone" id="reg_phone" placeholder="Celular" value="{{ old('reg_phone') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        @error('reg_phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Departamento -->
                    <div>
                        <label for="reg_department" class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <select name="reg_department" id="reg_department"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" required>
                            @foreach($states as $id => $name)
                                <option value="{{ $name }}" data-state-id="{{ $id }}" @selected(old('reg_department') === $name)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('reg_department')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Ciudad -->
                    <div>
                        <label for="reg_city_id" class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                        <select name="reg_city_id" id="reg_city_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" required>
                            @foreach($cities as $id => $name)
                                <option value="{{ $id }}" @selected(old('reg_city_id') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('reg_city_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Colombian Address Fields -->
                    <div class="space-y-4 border-t pt-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Dirección</h3>
                        
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
                        
                        <div>
                            <label for="address_references" class="block text-sm font-medium text-gray-700 mb-1">Referencias adicionales</label>
                            <textarea id="address_references" name="address_references" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: Cerca al parque, edificio azul"></textarea>
                        </div>
                        
                        <input type="hidden" id="reg_address" name="reg_address">
                    </div>

                    <!-- Document Uploads -->
                    <div class="border-t pt-4">
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Documentos adjuntos</h3>
                        <p class="text-sm text-gray-500 mb-3" id="documents-hint">
                            Persona natural: copia de cédula y/o RUT
                        </p>

                        <div class="space-y-2">
                            <div id="file-inputs-container">
                                <div class="flex items-center gap-2 file-input-row">
                                    <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png"
                                        class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg cursor-pointer">
                                    <button type="button" class="text-red-500 hover:text-red-700 remove-file-btn p-1" title="Quitar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <button type="button" id="add-file-btn" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Agregar otro archivo
                            </button>
                            <p class="text-xs text-gray-400">Formatos: PDF, JPG, PNG. Máximo 10 archivos, 5MB por archivo.</p>
                        </div>
                        @error('documents.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                
                    <!-- Terms and conditions -->
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
                </form>
                
                @if(session('success'))
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="ml-3 text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
                @endif

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
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="magic-modal-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative transform transition-all" id="magic-modal-content">
            <button type="button" id="magic-modal-close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Ingreso sin contraseña</h3>
            <p class="text-center text-sm text-gray-500 mb-1">Hemos enviado un código de verificación a</p>
            <p class="text-center text-sm font-semibold text-gray-700 mb-6" id="magic-modal-email"></p>
            <div class="mb-4">
                <label for="magic-code-input" class="block text-sm font-medium text-gray-700 mb-2">Código de verificación</label>
                <input type="text" id="magic-code-input" maxlength="6" placeholder="000000" inputmode="numeric" pattern="[0-9]*"
                    class="w-full text-center text-2xl tracking-[0.5em] font-mono py-3 px-4 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" autocomplete="one-time-code">
                <p id="magic-code-error" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
            <button type="button" id="magic-verify-btn" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Verificar e ingresar
            </button>
            <div class="text-center mt-4">
                <button type="button" id="magic-resend-btn" class="text-sm text-orange-600 hover:text-orange-700 hover:underline font-medium">
                    Reenviar código
                </button>
                <p id="magic-resend-timer" class="text-sm text-gray-400 hidden"></p>
            </div>
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

<!-- Tronex Client Modal -->
<div id="tronex-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="tronex-modal-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative transform transition-all" id="tronex-modal-content">
            <button type="button" id="tronex-modal-close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Cliente Tronex</h3>
            <p class="text-center text-sm text-gray-500 mb-6">Ingresa tu número de cédula para crear tu cuenta en Tuti</p>
            <div class="mb-4">
                <label for="tronex-cedula-input" class="block text-sm font-medium text-gray-700 mb-2">Cédula</label>
                <input type="text" id="tronex-cedula-input" placeholder="Número de cédula" inputmode="numeric"
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autocomplete="off">
                <p id="tronex-error" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
            <button type="button" id="tronex-submit-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                Crear cuenta Tuti
            </button>
            <div id="tronex-loading" class="hidden absolute inset-0 bg-white bg-opacity-80 rounded-xl flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-gray-500">Verificando...</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // =============================================
    // Person Type Switching
    // =============================================
    const personTypeOptions = document.querySelectorAll('.person-type-option');
    const nitLabel = document.getElementById('nit-label');
    const nitInput = document.getElementById('reg_nit');
    const nameLabel = document.getElementById('name-label');
    const nameInput = document.getElementById('reg_name');
    const documentsHint = document.getElementById('documents-hint');

    function updatePersonType(type) {
        personTypeOptions.forEach(opt => {
            const isActive = opt.dataset.value === type;
            opt.classList.toggle('border-blue-500', isActive);
            opt.classList.toggle('bg-blue-50', isActive);
            opt.classList.toggle('border-gray-300', !isActive);
            opt.classList.toggle('bg-white', !isActive);
            opt.querySelector('span').classList.toggle('text-blue-700', isActive);
            opt.querySelector('span').classList.toggle('text-gray-600', !isActive);
        });

        if (type === 'juridica') {
            nitLabel.textContent = 'NIT';
            nitInput.placeholder = 'Número de NIT';
            nameLabel.textContent = 'Razón social';
            nameInput.placeholder = 'Razón social';
            documentsHint.textContent = 'Persona jurídica: RUT, cédula del representante legal, cámara de comercio, certificado de composición accionaria';
        } else {
            nitLabel.textContent = 'Cédula';
            nitInput.placeholder = 'Número de cédula';
            nameLabel.textContent = 'Nombre completo';
            nameInput.placeholder = 'Nombre completo';
            documentsHint.textContent = 'Persona natural: copia de cédula y/o RUT';
        }
    }

    personTypeOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            updatePersonType(radio.value);
        });
    });

    // =============================================
    // Existing Client Check
    // =============================================
    const existingAlert = document.getElementById('existing-client-alert');
    let checkTimeout = null;

    function checkExistingClient() {
        const nit = nitInput?.value?.trim();
        const email = document.getElementById('reg_email')?.value?.trim();

        if ((!nit || nit.length < 5) && (!email || !email.includes('@'))) return;

        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(async () => {
            try {
                const response = await fetch('{{ route("form.check-existing") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ nit, email }),
                });
                const data = await response.json();
                existingAlert.classList.toggle('hidden', !data.exists);
            } catch (e) {
                // silently fail
            }
        }, 600);
    }

    if (nitInput) nitInput.addEventListener('blur', checkExistingClient);
    const regEmail = document.getElementById('reg_email');
    if (regEmail) regEmail.addEventListener('blur', checkExistingClient);

    // =============================================
    // Department -> City cascade
    // =============================================
    const departmentSelect = document.getElementById('reg_department');
    const citySelect = document.getElementById('reg_city_id');

    if (departmentSelect) {
        departmentSelect.addEventListener('change', async function() {
            const selectedOption = this.options[this.selectedIndex];
            const stateId = selectedOption?.dataset?.stateId;
            if (!stateId) return;

            citySelect.innerHTML = '<option value="">Cargando ciudades...</option>';
            try {
                const response = await fetch(`{{ route("form.cities-by-state") }}?state_id=${stateId}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const cities = await response.json();
                citySelect.innerHTML = '<option value="">Selecciona tu ciudad</option>';
                for (const [id, name] of Object.entries(cities)) {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = name;
                    citySelect.appendChild(option);
                }
            } catch (e) {
                citySelect.innerHTML = '<option value="">Error al cargar ciudades</option>';
            }
        });
    }

    // =============================================
    // File Upload Management
    // =============================================
    const fileContainer = document.getElementById('file-inputs-container');
    const addFileBtn = document.getElementById('add-file-btn');
    let fileCount = 1;

    if (addFileBtn) {
        addFileBtn.addEventListener('click', function() {
            if (fileCount >= 10) return;
            fileCount++;
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 file-input-row mt-2';
            row.innerHTML = `
                <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png"
                    class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg cursor-pointer">
                <button type="button" class="text-red-500 hover:text-red-700 remove-file-btn p-1" title="Quitar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>`;
            fileContainer.appendChild(row);
            addFileBtn.classList.toggle('hidden', fileCount >= 10);
        });
    }

    // Delegate remove-file-btn clicks (covers first row + dynamically added rows)
    if (fileContainer) {
        fileContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.remove-file-btn');
            if (!btn) return;
            const row = btn.closest('.file-input-row');
            if (!row || fileContainer.querySelectorAll('.file-input-row').length <= 1) return; // keep at least one
            row.remove();
            fileCount--;
            if (addFileBtn) addFileBtn.classList.toggle('hidden', fileCount >= 10);
        });
    }

    // =============================================
    // Terms & Submit
    // =============================================
    const termsAccepted = document.getElementById('terms_accepted');
    const submitRequest = document.getElementById('submit-request');

    if (termsAccepted && submitRequest) {
        const syncSubmitState = () => { submitRequest.disabled = !termsAccepted.checked; };
        syncSubmitState();
        termsAccepted.addEventListener('change', syncSubmitState);
    }

    // =============================================
    // Address Construction
    // =============================================
    function constructAddress() {
        const streetType = document.getElementById('address_street_type')?.value || '';
        const streetNumber = document.getElementById('address_street_number')?.value || '';
        const streetComplement = document.getElementById('address_street_complement')?.value || '';
        const houseNumber = document.getElementById('address_house_number')?.value || '';
        const houseComplement = document.getElementById('address_house_complement')?.value || '';
        const references = document.getElementById('address_references')?.value || '';

        const parts = [];
        if (streetType && streetNumber) {
            let main = `${streetType} ${streetNumber}`;
            if (streetComplement) main += ` ${streetComplement}`;
            parts.push(main);
        }
        if (houseNumber) {
            let house = `# ${houseNumber}`;
            if (houseComplement) house += ` ${houseComplement}`;
            parts.push(house);
        }
        if (references) parts.push(`Ref: ${references}`);

        const addressField = document.getElementById('reg_address');
        if (addressField) addressField.value = parts.join(', ');
    }

    ['address_street_type', 'address_street_number', 'address_street_complement',
     'address_house_number', 'address_house_complement', 'address_references'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('change', constructAddress); el.addEventListener('input', constructAddress); }
    });

    const form = document.getElementById('registration-form');
    if (form) {
        form.addEventListener('submit', function() { constructAddress(); });
    }

    // =============================================
    // Tab functionality for mobile
    // =============================================
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const loginSection = document.getElementById('login-section');
    const registerSection = document.getElementById('register-section');

    function switchToLogin() {
        loginTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        loginTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        registerTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
        registerTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        if (window.innerWidth < 1280) {
            loginSection.classList.remove('hidden');
            registerSection.classList.add('hidden');
        }
    }

    function switchToRegister() {
        registerTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        registerTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        loginTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
        loginTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        if (window.innerWidth < 1280) {
            registerSection.classList.remove('hidden');
            loginSection.classList.add('hidden');
        }
    }

    if (loginTab && registerTab) {
        loginTab.addEventListener('click', switchToLogin);
        registerTab.addEventListener('click', switchToRegister);
        if (window.innerWidth < 1280) switchToLogin();
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1280) {
                loginSection.classList.remove('hidden');
                registerSection.classList.remove('hidden');
            } else {
                if (loginTab.classList.contains('border-blue-500')) switchToLogin();
                else switchToRegister();
            }
        });
    }

    // =============================================
    // Magic Link Login
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

    function setMagicBtnLoading(loading) {
        if (!magicLinkBtn) return;
        magicLinkBtn.disabled = loading;
        magicLinkBtn.classList.toggle('opacity-60', loading);
        magicLinkBtn.classList.toggle('cursor-not-allowed', loading);
        if (magicLinkIcon) magicLinkIcon.classList.toggle('hidden', loading);
        if (magicLinkSpinner) magicLinkSpinner.classList.toggle('hidden', !loading);
        if (magicLinkTextEl) magicLinkTextEl.textContent = loading ? 'Enviando código...' : 'Ingresar sin contraseña';
    }

    function showBtnError(message) {
        if (magicLinkError) { magicLinkError.textContent = message; magicLinkError.classList.remove('hidden'); }
        if (magicLinkHint) magicLinkHint.classList.add('hidden');
    }
    function hideBtnError() {
        if (magicLinkError) magicLinkError.classList.add('hidden');
        if (magicLinkHint) magicLinkHint.classList.remove('hidden');
    }
    function showMagicLoading(show) { magicLoading.classList.toggle('hidden', !show); }
    function showMagicError(msg) {
        magicCodeError.textContent = msg; magicCodeError.classList.remove('hidden');
        magicCodeInput.classList.add('border-red-500');
    }
    function hideMagicError() { magicCodeError.classList.add('hidden'); magicCodeInput.classList.remove('border-red-500'); }
    function openMagicModal() {
        magicModal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
        magicCodeInput.value = ''; magicVerifyBtn.disabled = true; hideMagicError();
        setTimeout(() => magicCodeInput.focus(), 100);
    }
    function closeMagicModal() {
        magicModal.classList.add('hidden'); document.body.style.overflow = '';
        if (resendCooldown) { clearInterval(resendCooldown); resendCooldown = null; }
    }
    function startResendCooldown() {
        let seconds = 60;
        magicResendBtn.classList.add('hidden'); magicResendTimer.classList.remove('hidden');
        magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';
        resendCooldown = setInterval(() => {
            seconds--;
            if (seconds <= 0) { clearInterval(resendCooldown); resendCooldown = null; magicResendBtn.classList.remove('hidden'); magicResendTimer.classList.add('hidden'); }
            else magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';
        }, 1000);
    }

    async function sendMagicCode(email, showOnBtn) {
        if (showOnBtn) { setMagicBtnLoading(true); hideBtnError(); } else { showMagicLoading(true); }
        try {
            const response = await fetch('{{ route("magic-link.send") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ email }),
            });
            const data = await response.json();
            if (!response.ok) { const err = data.message || 'Error al enviar el código.'; if (showOnBtn) showBtnError(err); else showMagicError(err); return false; }
            return true;
        } catch (e) {
            const err = 'Error de conexión. Por favor intenta de nuevo.';
            if (showOnBtn) showBtnError(err); else showMagicError(err); return false;
        } finally {
            if (showOnBtn) setMagicBtnLoading(false); else showMagicLoading(false);
        }
    }

    async function verifyMagicCode(email, code) {
        showMagicLoading(true); hideMagicError();
        try {
            const response = await fetch('{{ route("magic-link.verify") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ email, code }),
            });
            const data = await response.json();
            if (response.ok && data.success) { window.location.href = data.redirect || '/'; return; }
            showMagicError(data.message || 'Código inválido o expirado.');
        } catch (e) { showMagicError('Error de conexión.'); }
        finally { showMagicLoading(false); }
    }

    if (magicLinkBtn) {
        magicLinkBtn.addEventListener('click', async function() {
            const email = loginEmailInput?.value?.trim();
            if (!email || !email.includes('@')) {
                loginEmailInput.focus(); loginEmailInput.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                showBtnError('Por favor ingresa tu correo electrónico arriba.');
                setTimeout(() => loginEmailInput.classList.remove('border-red-500', 'ring-2', 'ring-red-200'), 3000);
                return;
            }
            hideBtnError(); magicLinkEmail = email; magicModalEmail.textContent = email;
            const sent = await sendMagicCode(email, true);
            if (sent) { openMagicModal(); startResendCooldown(); }
        });
    }

    if (magicCodeInput) {
        magicCodeInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            magicVerifyBtn.disabled = this.value.length !== 6;
            if (this.value.length > 0) hideMagicError();
        });
        magicCodeInput.addEventListener('keyup', function(e) { if (this.value.length === 6 && e.key !== 'Enter') magicVerifyBtn.click(); });
        magicCodeInput.addEventListener('keydown', function(e) { if (e.key === 'Enter' && this.value.length === 6) { e.preventDefault(); magicVerifyBtn.click(); } });
    }
    if (magicVerifyBtn) { magicVerifyBtn.addEventListener('click', function() { if (magicCodeInput.value.trim().length === 6 && magicLinkEmail) verifyMagicCode(magicLinkEmail, magicCodeInput.value.trim()); }); }
    if (magicResendBtn) { magicResendBtn.addEventListener('click', async function() { if (magicLinkEmail) { magicCodeInput.value = ''; magicVerifyBtn.disabled = true; hideMagicError(); const sent = await sendMagicCode(magicLinkEmail, false); if (sent) startResendCooldown(); } }); }
    if (magicModalClose) magicModalClose.addEventListener('click', closeMagicModal);
    if (magicModalBackdrop) magicModalBackdrop.addEventListener('click', closeMagicModal);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && !magicModal.classList.contains('hidden')) closeMagicModal(); });

    // =============================================
    // Tronex existing client
    // =============================================
    const tronexBtn = document.getElementById('tronex-btn');
    const tronexModal = document.getElementById('tronex-modal');
    const tronexModalClose = document.getElementById('tronex-modal-close');
    const tronexModalBackdrop = document.getElementById('tronex-modal-backdrop');
    const tronexCedulaInput = document.getElementById('tronex-cedula-input');
    const tronexSubmitBtn = document.getElementById('tronex-submit-btn');
    const tronexError = document.getElementById('tronex-error');
    const tronexLoading = document.getElementById('tronex-loading');

    function openTronexModal() {
        if (tronexModal) {
            tronexModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            tronexCedulaInput.value = '';
            tronexError.classList.add('hidden');
            tronexError.textContent = '';
            setTimeout(() => tronexCedulaInput?.focus(), 100);
        }
    }
    function closeTronexModal() {
        if (tronexModal) {
            tronexModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    function showTronexError(msg) {
        if (tronexError) { tronexError.textContent = msg; tronexError.classList.remove('hidden'); }
        if (window.showToast) window.showToast(msg, 'error', 6000);
    }

    if (tronexBtn) tronexBtn.addEventListener('click', openTronexModal);
    if (tronexModalClose) tronexModalClose.addEventListener('click', closeTronexModal);
    if (tronexModalBackdrop) tronexModalBackdrop.addEventListener('click', closeTronexModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && tronexModal && !tronexModal.classList.contains('hidden')) closeTronexModal();
    });

    if (tronexSubmitBtn && tronexCedulaInput) {
        tronexSubmitBtn.addEventListener('click', async function() {
            const cedula = tronexCedulaInput.value.trim().replace(/\D/g, '');
            if (!cedula || cedula.length < 5) {
                showTronexError('Ingresa un número de cédula válido.');
                return;
            }
            tronexError.classList.add('hidden');
            tronexLoading.classList.remove('hidden');
            tronexSubmitBtn.disabled = true;
            try {
                const response = await fetch('{{ route("tronex.migrate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ document: cedula }),
                });
                const data = await response.json();
                if (data.success && data.redirect) {
                    closeTronexModal();
                    window.location.href = data.redirect;
                    return;
                }
                showTronexError(data.message || 'No se pudo crear la cuenta. Intenta de nuevo.');
            } catch (err) {
                showTronexError('Error de conexión. Intenta de nuevo.');
            } finally {
                tronexLoading.classList.add('hidden');
                tronexSubmitBtn.disabled = false;
            }
        });
        tronexCedulaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); tronexSubmitBtn.click(); }
        });
    }
});
</script>
@endsection
