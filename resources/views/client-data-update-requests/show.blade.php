@extends('layouts.admin')

@section('content')
<div class="p-4">
    <a href="{{ route('admin.client-data-update-requests.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-4">
        ← Volver a Actualización de datos
    </a>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 max-w-4xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Solicitud #{{ $updateRequest->id }}</h1>
            <span class="text-sm text-gray-500">{{ $updateRequest->created_at->format('d/m/Y H:i') }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
            <div>
                <p class="text-gray-500">Vendedor</p>
                <p class="font-medium text-gray-900">{{ $updateRequest->seller->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Estado</p>
                <p class="font-medium text-gray-900">{{ $updateRequest->read_at ? 'Leído' : 'Nuevo' }}</p>
            </div>
        </div>

        <h2 class="text-lg font-semibold text-gray-900 mb-3">Datos solicitados</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><p class="text-gray-500">Cédula o NIT</p><p class="font-medium text-gray-900">{{ $updateRequest->document ?: '-' }}</p></div>
            <div><p class="text-gray-500">Razón social</p><p class="font-medium text-gray-900">{{ $updateRequest->business_name ?: '-' }}</p></div>
            <div><p class="text-gray-500">Nombre completo</p><p class="font-medium text-gray-900">{{ $updateRequest->name ?: '-' }}</p></div>
            <div><p class="text-gray-500">Correo</p><p class="font-medium text-gray-900">{{ $updateRequest->email ?: '-' }}</p></div>
            <div><p class="text-gray-500">Teléfono</p><p class="font-medium text-gray-900">{{ $updateRequest->phone ?: '-' }}</p></div>
            <div><p class="text-gray-500">Celular</p><p class="font-medium text-gray-900">{{ $updateRequest->mobile_phone ?: '-' }}</p></div>
            <div><p class="text-gray-500">WhatsApp</p><p class="font-medium text-gray-900">{{ $updateRequest->whatsapp ?: '-' }}</p></div>
            <div><p class="text-gray-500">Ciudad</p><p class="font-medium text-gray-900">{{ $updateRequest->city_name ?: '-' }}</p></div>
            <div class="md:col-span-2"><p class="text-gray-500">Dirección</p><p class="font-medium text-gray-900">{{ $updateRequest->address ?: '-' }}</p></div>
            <div><p class="text-gray-500">Zona</p><p class="font-medium text-gray-900">{{ $updateRequest->zone_code ?: '-' }}</p></div>
            <div><p class="text-gray-500">Ruta</p><p class="font-medium text-gray-900">{{ $updateRequest->route ?: '-' }}</p></div>
            <div><p class="text-gray-500">Día de visita</p><p class="font-medium text-gray-900">{{ $updateRequest->day ?: '-' }}</p></div>
        </div>

        @if($updateRequest->seller_notes)
            <div class="mt-6">
                <p class="text-gray-500 text-sm">Notas del vendedor</p>
                <div class="mt-1 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-800 whitespace-pre-line">{{ $updateRequest->seller_notes }}</div>
            </div>
        @endif

        @if(!empty($updateRequest->previous_data))
            <div class="mt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Datos anteriores</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    @foreach($updateRequest->previous_data as $field => $value)
                        <div>
                            <p class="text-gray-500">{{ ucfirst(str_replace('_', ' ', $field)) }}</p>
                            <p class="font-medium text-gray-900">{{ $value ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
