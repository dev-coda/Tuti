@extends('layouts.admin')

@section('title', 'Editar Método de Envío')

@section('content')
{{ Aire::open()->route('shipping-methods.update', $shippingMethod)->bind($shippingMethod)->put() }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Método de Envío</h1>
        <p class="text-sm text-gray-500 mt-1">Actualiza la información del método de envío</p>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información del Método</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('name', 'Nombre')->groupClass('col-span-6')->helpText('Nombre que verán los clientes') }}
                
                {{ Aire::textarea('description', 'Descripción')->rows(3)->groupClass('col-span-6')->helpText('Descripción breve del método de envío') }}
                
                {{ Aire::input('sort_order', 'Orden de visualización')->type('number')->groupClass('col-span-3')->helpText('Número más bajo aparece primero') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('enabled', 'Habilitado')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está habilitado, el método estará disponible para los clientes
                        </span>
                    </div>
                </div>

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('shipping-methods.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-1">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Detalles</h3>
            
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Código</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $shippingMethod->code }}</dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Estado actual</dt>
                    <dd class="mt-1">
                        @if($shippingMethod->enabled)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Habilitado
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Deshabilitado
                            </span>
                        @endif
                    </dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Creado</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $shippingMethod->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Última actualización</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $shippingMethod->updated_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        <!-- Warning Box -->
        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div class="text-sm text-yellow-800">
                    <p class="font-medium mb-1">Advertencia</p>
                    <p>Al deshabilitar este método, los clientes no podrán seleccionarlo al realizar pedidos.</p>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection
