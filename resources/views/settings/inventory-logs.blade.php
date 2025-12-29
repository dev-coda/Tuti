@extends('layouts.admin')

@section('title', 'Logs de Sincronización de Inventario')

@section('content')
<div class="grid grid-cols-1 p-4 xl:gap-4">
    <div class="mb-4 col-span-full">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Logs de Sincronización de Inventario</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Última sincronización: 
                    <span class="font-medium text-gray-700">
                        {{ $lastSync ? \Carbon\Carbon::parse($lastSync)->format('d/m/Y H:i:s') : 'N/A' }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('settings.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    ← Volver
                </a>
                <button onclick="location.reload()" 
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Actualizar
                </button>
            </div>
        </div>
    </div>

    {{-- Logs Display --}}
    <div class="col-span-full">
        @if($logs->isEmpty())
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-8 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay logs de sincronización</h3>
                <p class="text-gray-500">Ejecuta una sincronización de inventario para ver los logs aquí.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4">
                @foreach($logs as $log)
                <div class="bg-white border-2 {{ $log->status === 'error' ? 'border-red-200' : 'border-gray-200' }} rounded-lg shadow-sm overflow-hidden">
                    {{-- Header --}}
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                    {{ $log->status === 'success' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $log->status === 'error' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $log->status === 'warning' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ strtoupper($log->status) }}
                                </span>
                                <h3 class="text-lg font-semibold text-gray-900">Bodega: {{ $log->bodega_code }}</h3>
                            </div>
                            <span class="text-sm text-gray-500">{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                        </div>
                    </div>

                    {{-- Stats --}}
                    @if($log->status === 'success')
                    <div class="px-6 py-4 grid grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $log->skus_received }}</div>
                            <div class="text-xs text-gray-500 uppercase">SKUs Recibidos</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $log->products_updated }}</div>
                            <div class="text-xs text-gray-500 uppercase">Productos Actualizados</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">{{ $log->products_set_to_zero }}</div>
                            <div class="text-xs text-gray-500 uppercase">Productos a Cero</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-600">{{ $log->skus_received + $log->products_set_to_zero }}</div>
                            <div class="text-xs text-gray-500 uppercase">Total Procesados</div>
                        </div>
                    </div>
                    @endif

                    {{-- Error Message --}}
                    @if($log->error_message)
                    <div class="px-6 py-4 bg-red-50 border-t border-red-100">
                        <p class="text-sm text-red-800">
                            <span class="font-semibold">Error:</span> {{ $log->error_message }}
                        </p>
                    </div>
                    @endif

                    {{-- SKUs in Response (Collapsible) --}}
                    @if($log->skus_in_response && count($log->skus_in_response) > 0)
                    <div class="px-6 py-4 border-t border-gray-100">
                        <details class="group">
                            <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900 flex items-center gap-2">
                                <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                Ver SKUs recibidos ({{ count($log->skus_in_response) }})
                            </summary>
                            <div class="mt-3 p-4 bg-gray-50 rounded-lg max-h-60 overflow-y-auto">
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach($log->skus_in_response as $sku)
                                        <span class="px-2 py-1 text-xs bg-white border border-gray-200 rounded">{{ $sku }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    </div>
                    @endif

                    {{-- SOAP Response (Collapsible) --}}
                    @if($log->soap_response)
                    <div class="px-6 py-4 border-t border-gray-100">
                        <details class="group">
                            <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900 flex items-center gap-2">
                                <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                Ver respuesta SOAP completa
                            </summary>
                            <div class="mt-3 p-4 bg-gray-900 rounded-lg max-h-96 overflow-auto">
                                <pre class="text-xs text-green-400 font-mono">{{ $log->soap_response }}</pre>
                            </div>
                        </details>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
