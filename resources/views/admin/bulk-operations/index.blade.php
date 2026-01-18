@extends('layouts.admin')

@section('title', 'Procesos Masivos')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-1 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Procesos Masivos</h1>
                <p class="text-sm text-gray-500">Ejecuta operaciones en lote sobre múltiples registros</p>
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

    <!-- Bulk Client Data Sync -->
    <div class="col-span-full mb-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">Sincronización Masiva de Datos de Clientes</h3>
                        <p class="text-sm text-gray-500">Actualiza la información de todos los clientes desde el sistema SOAP</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                    <div class="flex">
                        <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800">¿Qué hace este proceso?</h4>
                            <div class="mt-1 text-xs text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Sincroniza datos de todos los clientes desde el backend SOAP</li>
                                    <li>Actualiza: nombre, teléfonos, dirección, zonas, tipo de cliente, grupo de precios, saldo, cupo, etc.</li>
                                    <li>Proceso asíncrono: puede tomar varios minutos dependiendo del número de clientes</li>
                                    <li>Genera un reporte CSV con los resultados de la sincronización</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    @php
                        $clientCount = \App\Models\User::whereHas('roles', function($q) {
                            $q->where('name', 'client');
                        })->whereNotNull('document')->where('document', '!=', '')->count();
                    @endphp
                    <div class="text-sm text-gray-600">
                        <span class="font-medium">Clientes a sincronizar:</span>
                        <span class="text-gray-900 font-semibold">{{ number_format($clientCount) }}</span>
                    </div>
                </div>

                <form action="{{ route('admin.bulk-operations.sync-clients-data') }}" method="POST" 
                      onsubmit="return confirm('¿Estás seguro? Este proceso sincronizará {{ number_format($clientCount) }} clientes. Puede tomar varios minutos.');">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 border border-transparent rounded-lg shadow-sm hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Iniciar Sincronización Masiva
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reports Section -->
    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900">Reportes Generados</h3>
                            <p class="text-sm text-gray-500">Descarga los reportes de operaciones masivas completadas</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                @if(empty($reports))
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">No hay reportes disponibles</p>
                        <p class="text-xs text-gray-400">Los reportes aparecerán aquí cuando se completen las operaciones masivas</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Nombre del Archivo</th>
                                    <th scope="col" class="px-6 py-3">Tamaño</th>
                                    <th scope="col" class="px-6 py-3">Fecha de Generación</th>
                                    <th scope="col" class="px-6 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reports as $report)
                                    <tr class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $report['name'] }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ number_format($report['size'] / 1024, 2) }} KB
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ \Carbon\Carbon::createFromTimestamp($report['modified'])->format('d/m/Y H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('admin.bulk-operations.download-report', $report['name']) }}" 
                                                   class="inline-flex items-center px-3 py-2 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                    </svg>
                                                    Descargar
                                                </a>
                                                <form action="{{ route('admin.bulk-operations.delete-report', $report['name']) }}" 
                                                      method="POST" 
                                                      onsubmit="return confirm('¿Estás seguro de eliminar este reporte?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="inline-flex items-center px-3 py-2 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
