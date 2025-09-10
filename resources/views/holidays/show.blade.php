@extends('layouts.admin')

@section('content')
<div class="p-4">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Detalles del Festivo</h1>
                <a href="{{ route('holidays.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    ← Volver
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <p class="mt-1 text-lg">{{ $holiday->type }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fecha</label>
                    <p class="mt-1 text-lg">{{ $holiday->date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Día de la Semana</label>
                    <p class="mt-1 text-lg">{{ $holiday->day }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Creado</label>
                    <p class="mt-1 text-sm text-gray-600">{{ $holiday->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            <div class="mt-6 flex space-x-4">
                <a href="{{ route('holidays.edit', $holiday) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Editar
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
