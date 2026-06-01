@extends('layouts.page')

@section('head')
@include('elements.seo', [
    'title' => 'Servicio al cliente',
    'description' => 'Canales de atención y formulario PQRS de TRONEX-TUTI',
])
@endsection

@section('content')
<section class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900">Servicio al cliente</h1>
        <p class="text-gray-600 mt-2">Estamos para ayudarte. Usa nuestros canales de contacto o envíanos tu PQRS.</p>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm">
            <p class="font-semibold mb-2">Por favor revisa la información:</p>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Información de contacto</h2>

                <div class="space-y-4 text-sm text-gray-700">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Dirección</p>
                        <p>Cra. 67 #1 S-92, Guayabal, Medellín, Guayabal, Medellín, Antioquia</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Teléfono</p>
                        <p>44488090</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Celular / WhatsApp</p>
                        <a
                            href="https://web.whatsapp.com/send?phone=573000000000"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-green-700 hover:text-green-800 font-medium underline underline-offset-2"
                        >
                            Abrir WhatsApp Web
                        </a>
                        <p class="text-xs text-gray-500 mt-1">Número de WhatsApp pendiente de confirmación.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Formulario PQRS</h2>

                <form method="POST" action="{{ route('customer-service.store') }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre y apellidos</label>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                            <input type="text" name="city" value="{{ old('city') }}" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono o celular</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de solicitud</label>
                        <select name="request_type" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                            <option value="">Selecciona una opción</option>
                            <option value="pregunta" @selected(old('request_type') === 'pregunta')>Pregunta</option>
                            <option value="queja" @selected(old('request_type') === 'queja')>Queja</option>
                            <option value="reclamo" @selected(old('request_type') === 'reclamo')>Reclamo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Asunto</label>
                        <input type="text" name="subject" value="{{ old('subject') }}" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mensaje</label>
                        <textarea name="message" rows="5" class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" required>{{ old('message') }}</textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center justify-center px-6 py-3 bg-orange-600 text-white text-sm font-semibold rounded-lg hover:bg-orange-700 transition-colors">
                            Enviar solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
