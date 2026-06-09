@extends('layouts.admin')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-1 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Servicio al Cliente</h1>
                <p class="text-sm text-gray-500 mt-1">Edita la información de contacto que se muestra en la página pública de servicio al cliente.</p>
            </div>
            <a href="{{ route('customer-service') }}" target="_blank" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Ver página pública
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="col-span-full mb-4">
            <div class="flex items-center p-4 text-sm text-green-800 border border-green-300 rounded-lg bg-green-50">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <form method="POST" action="{{ route('admin.customer-service.contact.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500"
                        required
                    >{{ old('address', $contact['customer_service_address']) }}</textarea>
                    @error('address')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="{{ old('phone', $contact['customer_service_phone']) }}"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500"
                        required
                    >
                    @error('phone')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="whatsapp" class="block text-sm font-medium text-gray-700 mb-1">Celular / WhatsApp</label>
                    <input
                        type="text"
                        id="whatsapp"
                        name="whatsapp"
                        value="{{ old('whatsapp', $contact['customer_service_whatsapp']) }}"
                        placeholder="573001234567"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                    <p class="text-xs text-gray-500 mt-1">Ingresa el número con código de país, sin espacios ni símbolos.</p>
                    @error('whatsapp')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="whatsapp_note" class="block text-sm font-medium text-gray-700 mb-1">Nota de WhatsApp (opcional)</label>
                    <input
                        type="text"
                        id="whatsapp_note"
                        name="whatsapp_note"
                        value="{{ old('whatsapp_note', $contact['customer_service_whatsapp_note']) }}"
                        class="w-full border-gray-300 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500"
                    >
                    @error('whatsapp_note')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700">
                    Guardar cambios
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
