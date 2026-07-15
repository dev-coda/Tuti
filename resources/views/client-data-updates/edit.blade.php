@extends('layouts.page')

@section('head')
@include('elements.seo', ['title' => ($isClientSelfService ?? false) ? 'Actualizar tus datos' : 'Actualizar datos del cliente'])
@endsection

@section('content')
@php
    $displayEmail = $client->clientDisplayEmail() ?? '';
    $isClientSelfService = $isClientSelfService ?? false;
    $defaultAddress = old('address', $ruteroRoute['address'] ?? $zone?->address ?? '');
@endphp

<section class="w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        @if($isClientSelfService)
            <a href="{{ route('logout') }}"
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-4">
                Cerrar sesión
            </a>
        @else
            <a href="{{ route('clients.orders.index', array_filter(['tab' => $returnTab, 'ruta' => $returnRoute ?: null, 'ruta_q' => $returnSearch ?: null])) }}"
               class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-4">
                ← Volver a Mi Ruta
            </a>
        @endif

        <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">
            {{ $isClientSelfService ? 'Actualiza tus datos' : 'Actualizar datos del cliente' }}
        </h1>

        @if($isClientSelfService)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-medium">Es necesario actualizar tus datos de contacto.</p>
                <p class="mt-1">Tu cuenta tiene un correo temporal o inválido. Completa el formulario con tu información real para continuar con la activación.</p>
            </div>
        @else
            <p class="text-sm text-gray-500 mt-1">Revisa y corrige la información del cliente. Los cambios quedarán registrados para revisión administrativa.</p>
        @endif

        @if($ruteroUnavailable ?? false)
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                No pudimos consultar la zona y la ruta en Tronex. Intenta más tarde o contáctanos por nuestros canales oficiales.
            </div>
        @endif

        @if(session('success'))
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
    </div>

    <form method="POST"
          action="{{ $isClientSelfService ? route('client-data-updates.client.store') : route('client-data-updates.store', $zone) }}"
          class="space-y-6">
        {{-- Seller flow always has a zone; client self-service may not. --}}
        @csrf
        @unless($isClientSelfService)
            <input type="hidden" name="return_tab" value="{{ $returnTab }}">
            <input type="hidden" name="return_route" value="{{ $returnRoute }}">
            <input type="hidden" name="return_search" value="{{ $returnSearch }}">
        @endunless

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Información del cliente</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="document" class="block text-xs font-medium text-gray-500 mb-1">Cédula o NIT</label>
                    <input type="text" name="document" id="document" value="{{ old('document', $client->document) }}" readonly
                           class="w-full border-gray-200 rounded-lg text-sm bg-gray-50 px-3 py-2">
                    @error('document') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="business_name" class="block text-xs font-medium text-gray-500 mb-1">Razón social</label>
                    <input type="text" name="business_name" id="business_name" value="{{ old('business_name', $client->business_name) }}"
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('business_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="name" class="block text-xs font-medium text-gray-500 mb-1">Nombre completo *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $client->name) }}" required
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="block text-xs font-medium text-gray-500 mb-1">
                        Correo electrónico{{ $isClientSelfService ? ' *' : '' }}
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email', $displayEmail) }}"
                           @if($isClientSelfService) required @endif
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $client->phone) }}"
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mobile_phone" class="block text-xs font-medium text-gray-500 mb-1">Celular</label>
                    <input type="text" name="mobile_phone" id="mobile_phone" value="{{ old('mobile_phone', $client->mobile_phone) }}"
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('mobile_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="whatsapp" class="block text-xs font-medium text-gray-500 mb-1">WhatsApp</label>
                    <input type="text" name="whatsapp" id="whatsapp" value="{{ old('whatsapp', $client->whatsapp) }}"
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('whatsapp') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Sucursal / ubicación</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="address" class="block text-xs font-medium text-gray-500 mb-1">Dirección *</label>
                    <input type="text" name="address" id="address" value="{{ $defaultAddress }}" required
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city_name" class="block text-xs font-medium text-gray-500 mb-1">Ciudad</label>
                    <input type="text" name="city_name" id="city_name" value="{{ old('city_name', $client->city?->name) }}"
                           class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    @error('city_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="block text-xs font-medium text-gray-500 mb-1">Zona (Tronex)</span>
                    <p class="text-sm text-gray-900 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">{{ $ruteroRoute['zone_code'] ?? '—' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-medium text-gray-500 mb-1">Ruta (Tronex)</span>
                    <p class="text-sm text-gray-900 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">{{ $ruteroRoute['route'] ?? '—' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-medium text-gray-500 mb-1">Día de visita (Tronex)</span>
                    <p class="text-sm text-gray-900 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">{{ $ruteroRoute['day'] ?? '—' }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-500">Zona, ruta y día se consultan en Tronex y no pueden editarse manualmente.</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6">
            <label for="seller_notes" class="block text-xs font-medium text-gray-500 mb-1">
                {{ $isClientSelfService ? 'Comentarios adicionales (opcional)' : 'Notas adicionales (opcional)' }}
            </label>
            <textarea name="seller_notes" id="seller_notes" rows="4"
                      class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                      placeholder="{{ $isClientSelfService ? 'Indica cualquier detalle relevante para tu actualización.' : 'Indica cualquier detalle relevante para la actualización.' }}">{{ old('seller_notes') }}</textarea>
            @error('seller_notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    @disabled($ruteroUnavailable ?? false)
                    class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Enviar actualización
            </button>
            @unless($isClientSelfService)
                <a href="{{ route('clients.orders.index', array_filter(['tab' => $returnTab, 'ruta' => $returnRoute ?: null, 'ruta_q' => $returnSearch ?: null])) }}"
                   class="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
            @endunless
        </div>
    </form>
</section>
@endsection
