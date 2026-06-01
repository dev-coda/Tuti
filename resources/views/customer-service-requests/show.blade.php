@extends('layouts.admin')

@section('content')
<div class="p-4">
    <a href="{{ route('admin.customer-service-requests.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-4">
        ← Volver a PQRS
    </a>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 max-w-4xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Detalle PQRS #{{ $requestEntry->id }}</h1>
            <span class="text-sm text-gray-500">{{ $requestEntry->created_at->format('d/m/Y H:i') }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Nombre y apellidos</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->full_name }}</p>
            </div>
            <div>
                <p class="text-gray-500">Tipo de solicitud</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->request_type_label }}</p>
            </div>
            <div>
                <p class="text-gray-500">Correo electrónico</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->email }}</p>
            </div>
            <div>
                <p class="text-gray-500">Ciudad</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->city }}</p>
            </div>
            <div>
                <p class="text-gray-500">Teléfono / Celular</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->phone }}</p>
            </div>
            <div>
                <p class="text-gray-500">Estado</p>
                <p class="font-medium text-gray-900">{{ $requestEntry->read_at ? 'Leído' : 'Nuevo' }}</p>
            </div>
        </div>

        <div class="mt-6">
            <p class="text-gray-500 text-sm">Asunto</p>
            <p class="font-medium text-gray-900">{{ $requestEntry->subject }}</p>
        </div>

        <div class="mt-4">
            <p class="text-gray-500 text-sm">Mensaje</p>
            <div class="mt-1 p-4 rounded-lg bg-gray-50 border border-gray-200 text-sm text-gray-800 whitespace-pre-line">{{ $requestEntry->message }}</div>
        </div>
    </div>
</div>
@endsection
