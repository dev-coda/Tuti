@extends('layouts.admin')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Ver {{$setting->name}}</h1>
            <div class="flex space-x-2">
                <a href="{{ route('settings.edit', $setting) }}"
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                    </svg>
                    Editar
                </a>
                <a href="{{ route('settings.index') }}"
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-gray-900 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-gray-200">
                    Volver
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold">Información de la Configuración</h3>

            <div class="grid grid-cols-6 gap-6">
                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                        {{ $setting->name }}
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Clave</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                        <code class="text-sm">{{ $setting->key }}</code>
                    </div>
                </div>

                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md min-h-[100px]">
                        @if(strlen($setting->value) > 100)
                            <div class="whitespace-pre-wrap break-words">{{ $setting->value }}</div>
                        @else
                            {{ $setting->value }}
                        @endif
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Creado</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                        {{ $setting->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Última Modificación</label>
                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                        {{ $setting->updated_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
