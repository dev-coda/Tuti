@extends('layouts.admin')

@section('title', 'Configuración de Correo')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Configuración de Correo</h1>
                <p class="text-sm text-gray-500">Configura los parámetros de envío de correo electrónico del sistema</p>
            </div>
            <a href="{{ route('settings.index') }}" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Volver a Configuración
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="col-span-full">
            <div class="p-4 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
                <div class="flex">
                    <svg class="w-5 h-5 mr-2 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="col-span-full">
            <div class="p-4 mb-4 text-sm text-red-800 bg-red-100 border border-red-200 rounded-lg">
                <div class="flex">
                    <svg class="w-5 h-5 mr-2 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    {{ session('error') }}
                </div>
            </div>
        </div>
    @endif

    <div class="col-span-full">
        <form action="{{ route('settings.mailer.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- General Configuration -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Configuración General</h3>
                            <p class="text-sm text-gray-500">Configuración básica del sistema de correo</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="mail_mailer" class="block text-sm font-medium text-gray-700 mb-2">
                                Driver de Correo <span class="text-red-500">*</span>
                            </label>
                            <select id="mail_mailer"
                                    name="mail_mailer"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mail_mailer') border-red-300 @enderror"
                                    required>
                                <option value="smtp" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'smtp' ? 'selected' : '' }}>SMTP</option>
                                <option value="mailgun" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                                <option value="sendmail" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                <option value="log" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'log' ? 'selected' : '' }}>Log (para pruebas)</option>
                            </select>
                            @error('mail_mailer')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Selecciona el método de envío de correo</p>
                        </div>

                        <div>
                            <label for="mail_from_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre del Remitente <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="mail_from_name"
                                   name="mail_from_name"
                                   value="{{ $mailerSettings['mail_from_name']->value ?? 'Tuti' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mail_from_name') border-red-300 @enderror"
                                   placeholder="Tuti"
                                   required>
                            @error('mail_from_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="mail_from_address" class="block text-sm font-medium text-gray-700 mb-2">
                            Dirección de Remitente <span class="text-red-500">*</span>
                        </label>
                        <input type="email"
                               id="mail_from_address"
                               name="mail_from_address"
                               value="{{ $mailerSettings['mail_from_address']->value ?? 'noreply@tuti.com' }}"
                               class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mail_from_address') border-red-300 @enderror"
                               placeholder="noreply@tuti.com"
                               required>
                        @error('mail_from_address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Dirección de correo que aparecerá como remitente</p>
                    </div>
                </div>
            </div>

            <!-- Mailgun Configuration -->
            <div id="mailgun-config" class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Configuración de Mailgun</h3>
                            <p class="text-sm text-gray-500">Configuración para el servicio de correo Mailgun</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="mailgun_domain" class="block text-sm font-medium text-gray-700 mb-2">
                                Dominio de Mailgun
                            </label>
                            <input type="text"
                                   id="mailgun_domain"
                                   name="mailgun_domain"
                                   value="{{ $mailerSettings['mailgun_domain']->value ?? '' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mailgun_domain') border-red-300 @enderror"
                                   placeholder="mg.tuti.com">
                            @error('mailgun_domain')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Tu dominio verificado en Mailgun</p>
                        </div>

                        <div>
                            <label for="mailgun_secret" class="block text-sm font-medium text-gray-700 mb-2">
                                Clave Secreta de Mailgun
                            </label>
                            <div class="relative">
                                <input type="password"
                                       id="mailgun_secret"
                                       name="mailgun_secret"
                                       value="{{ $mailerSettings['mailgun_secret']->value ?? '' }}"
                                       class="block w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mailgun_secret') border-red-300 @enderror"
                                       placeholder="Clave API de Mailgun">
                                <button type="button"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                        onclick="togglePassword('mailgun_secret')">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            @error('mailgun_secret')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="mailgun_endpoint" class="block text-sm font-medium text-gray-700 mb-2">
                            Endpoint de Mailgun
                        </label>
                        <select id="mailgun_endpoint"
                                name="mailgun_endpoint"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('mailgun_endpoint') border-red-300 @enderror">
                            <option value="api.mailgun.net" {{ ($mailerSettings['mailgun_endpoint']->value ?? 'api.mailgun.net') == 'api.mailgun.net' ? 'selected' : '' }}>api.mailgun.net (US)</option>
                            <option value="api.eu.mailgun.net" {{ ($mailerSettings['mailgun_endpoint']->value ?? 'api.mailgun.net') == 'api.eu.mailgun.net' ? 'selected' : '' }}>api.eu.mailgun.net (EU)</option>
                        </select>
                        @error('mailgun_endpoint')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Selecciona la región de tu cuenta Mailgun</p>
                    </div>
                </div>
            </div>

            <!-- SMTP Configuration -->
            <div id="smtp-config" class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Configuración SMTP</h3>
                            <p class="text-sm text-gray-500">Configuración para servidores SMTP personalizados</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">
                                Servidor SMTP
                            </label>
                            <input type="text"
                                   id="smtp_host"
                                   name="smtp_host"
                                   value="{{ $mailerSettings['smtp_host']->value ?? 'smtp.mailgun.org' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('smtp_host') border-red-300 @enderror"
                                   placeholder="smtp.gmail.com">
                            @error('smtp_host')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">
                                Puerto SMTP
                            </label>
                            <input type="number"
                                   id="smtp_port"
                                   name="smtp_port"
                                   value="{{ $mailerSettings['smtp_port']->value ?? '587' }}"
                                   min="1"
                                   max="65535"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('smtp_port') border-red-300 @enderror"
                                   placeholder="587">
                            @error('smtp_port')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">
                                Encriptación
                            </label>
                            <select id="smtp_encryption"
                                    name="smtp_encryption"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('smtp_encryption') border-red-300 @enderror">
                                <option value="tls" {{ ($mailerSettings['smtp_encryption']->value ?? 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ ($mailerSettings['smtp_encryption']->value ?? 'tls') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="">Ninguna</option>
                            </select>
                            @error('smtp_encryption')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">
                                Usuario SMTP
                            </label>
                            <input type="text"
                                   id="smtp_username"
                                   name="smtp_username"
                                   value="{{ $mailerSettings['smtp_username']->value ?? '' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('smtp_username') border-red-300 @enderror"
                                   placeholder="usuario@ejemplo.com">
                            @error('smtp_username')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Contraseña SMTP
                            </label>
                            <div class="relative">
                                <input type="password"
                                       id="smtp_password"
                                       name="smtp_password"
                                       value="{{ $mailerSettings['smtp_password']->value ?? '' }}"
                                       class="block w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('smtp_password') border-red-300 @enderror"
                                       placeholder="Contraseña de la cuenta">
                                <button type="button"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                        onclick="togglePassword('smtp_password')">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                            @error('smtp_password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Email Section -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Probar Configuración</h3>
                            <p class="text-sm text-gray-500">Envía un correo de prueba para verificar la configuración</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    @php
                        $mailDriver = $mailerSettings['mail_mailer']->value ?? 'mailgun';
                        $mailgunDomain = $mailerSettings['mailgun_domain']->value ?? '';
                        $mailgunSecret = $mailerSettings['mailgun_secret']->value ?? '';
                        $smtpHost = $mailerSettings['smtp_host']->value ?? '';
                        $smtpUsername = $mailerSettings['smtp_username']->value ?? '';
                        $smtpPassword = $mailerSettings['smtp_password']->value ?? '';
                        
                        $showMailgunWarning = $mailDriver === 'mailgun' && (empty($mailgunDomain) || empty($mailgunSecret));
                        $showSmtpWarning = $mailDriver === 'smtp' && (empty($smtpHost) || empty($smtpUsername) || empty($smtpPassword));
                    @endphp
                    
                    @if($showMailgunWarning)
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-red-800">Credenciales de Mailgun Incompletas</h4>
                                    <p class="mt-1 text-xs text-red-700">
                                        Para usar Mailgun, debes configurar tanto el <strong>Dominio</strong> como la <strong>Clave Secreta</strong>. 
                                        <br>• Obtén tu API Key en: <a href="https://app.mailgun.com/settings/api_security" target="_blank" class="underline">https://app.mailgun.com/settings/api_security</a>
                                        <br>• Verifica tu dominio en: <a href="https://app.mailgun.com/domains" target="_blank" class="underline">https://app.mailgun.com/domains</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    @if($showSmtpWarning)
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-red-800">Credenciales SMTP Incompletas</h4>
                                    <p class="mt-1 text-xs text-red-700">
                                        Para usar SMTP, debes configurar el <strong>Servidor</strong>, <strong>Usuario</strong> y <strong>Contraseña</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="test_email" class="block text-sm font-medium text-gray-700 mb-2">
                                Enviar correo de prueba a:
                            </label>
                            <input type="email"
                                   id="test_email"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="correo@ejemplo.com">
                        </div>
                        <div class="flex items-end">
                            <button type="button" 
                                    id="test-email-btn"
                                    class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-lg shadow-sm hover:bg-orange-700 focus:ring-4 focus:ring-orange-300">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                Enviar Prueba
                            </button>
                        </div>
                    </div>
                    
                    <div id="test-result" class="mt-4 hidden"></div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3">
                <a href="{{ route('settings.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 border border-transparent rounded-lg shadow-sm hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Guardar Configuración
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mailDriverSelect = document.getElementById('mail_mailer');
    const mailgunConfig = document.getElementById('mailgun-config');
    const smtpConfig = document.getElementById('smtp-config');
    const testEmailBtn = document.getElementById('test-email-btn');
    const testEmailInput = document.getElementById('test_email');
    const testResult = document.getElementById('test-result');

    // Toggle password visibility
    window.togglePassword = function(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.nextElementSibling;
        const icon = button.querySelector('svg');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>';
        } else {
            field.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        }
    };

    // Show/hide configuration sections based on mail driver
    function toggleConfigSections() {
        const selectedDriver = mailDriverSelect.value;
        
        if (selectedDriver === 'mailgun') {
            mailgunConfig.classList.remove('hidden');
            smtpConfig.classList.add('hidden');
        } else if (selectedDriver === 'smtp') {
            mailgunConfig.classList.add('hidden');
            smtpConfig.classList.remove('hidden');
        } else {
            mailgunConfig.classList.add('hidden');
            smtpConfig.classList.add('hidden');
        }
    }

    // Initial toggle
    toggleConfigSections();
    
    // Listen for changes
    mailDriverSelect.addEventListener('change', toggleConfigSections);

    // Test email functionality
    testEmailBtn.addEventListener('click', function() {
        const email = testEmailInput.value.trim();

        if (!email) {
            showTestResult('Por favor ingresa una dirección de correo para la prueba', 'error');
            return;
        }

        // Disable button and show loading
        testEmailBtn.disabled = true;
        testEmailBtn.innerHTML = '<svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Enviando...';

        // Send test email via AJAX
        fetch('/admin/test-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTestResult('Correo de prueba enviado exitosamente', 'success');
            } else {
                showTestResult('Error al enviar correo de prueba: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showTestResult('Error de conexión: ' + error.message, 'error');
        })
        .finally(() => {
            // Re-enable button
            testEmailBtn.disabled = false;
            testEmailBtn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>Enviar Prueba';
        });
    });

    function showTestResult(message, type) {
        testResult.classList.remove('hidden');
        
        const bgColor = type === 'success' ? 'bg-green-100 border-green-200 text-green-800' : 'bg-red-100 border-red-200 text-red-800';
        const iconColor = type === 'success' ? 'text-green-400' : 'text-red-400';
        const icon = type === 'success' 
            ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>'
            : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>';
        
        testResult.innerHTML = `
            <div class="p-4 text-sm border rounded-lg ${bgColor}">
                <div class="flex">
                    <svg class="w-5 h-5 mr-2 ${iconColor}" fill="currentColor" viewBox="0 0 20 20">
                        ${icon}
                    </svg>
                    ${message}
                </div>
            </div>
        `;
    }
});
</script>
@endsection