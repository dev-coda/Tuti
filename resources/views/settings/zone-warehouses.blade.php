@extends('layouts.admin')

@section('title', 'Asignaciones Zona-Bodega')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Asignaciones Zona-Bodega</h1>
                <p class="text-sm text-gray-500">Gestiona las asignaciones de zonas de entrega a bodegas</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('settings.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Volver a Configuraciones
                </a>
                <form action="{{ route('settings.zone-warehouses.sync') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-lg shadow-sm hover:bg-orange-700 focus:ring-4 focus:ring-orange-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Sincronizar desde Config
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="col-span-full mb-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Mappings -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Asignaciones</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $allMappings->count() }}</p>
                    </div>
                </div>
            </div>

            <!-- DB Mappings -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 bg-green-100 rounded-lg">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">En Base de Datos</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $dbMappings->count() }}</p>
                    </div>
                </div>
            </div>

            <!-- Unique Bodegas -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 bg-purple-100 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Bodegas Únicas</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $bodegas->count() }}</p>
                    </div>
                </div>
            </div>

            <!-- Config Mappings -->
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 bg-orange-100 rounded-lg">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">En Config File</p>
                        <p class="text-2xl font-bold text-gray-900">{{ count(config('zone_warehouses.mappings', [])) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bodegas Summary -->
    <div class="col-span-full mb-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Distribución por Bodega</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($bodegas as $bodega => $count)
                <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                    <div class="text-xs text-gray-600 mb-1">{{ $bodega }}</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $count }}</div>
                    <div class="text-xs text-gray-500">{{ $count == 1 ? 'zona' : 'zonas' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Add New Mapping -->
    <div class="col-span-full mb-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Agregar Nueva Asignación</h3>
            <form action="{{ route('settings.zone-warehouses.store') }}" method="POST" class="flex items-end gap-4">
                @csrf
                <div class="flex-1">
                    <label for="zone_code" class="block text-sm font-medium text-gray-700 mb-2">Código de Zona</label>
                    <input type="text" 
                           id="zone_code" 
                           name="zone_code" 
                           required
                           placeholder="ej: 102, BOG01, etc."
                           class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex-1">
                    <label for="bodega_code" class="block text-sm font-medium text-gray-700 mb-2">Código de Bodega</label>
                    <input type="text" 
                           id="bodega_code" 
                           name="bodega_code" 
                           required
                           placeholder="ej: MDTAT, BG00, etc."
                           class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg shadow-sm hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Agregar
                </button>
            </form>
        </div>
    </div>

    <!-- Mappings Table -->
    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Todas las Asignaciones ({{ $allMappings->count() }})</h3>
                <p class="mt-1 text-sm text-gray-500">Lista completa de asignaciones zona-bodega</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3">Código de Zona</th>
                            <th scope="col" class="px-6 py-3">Código de Bodega</th>
                            <th scope="col" class="px-6 py-3">Fuente</th>
                            <th scope="col" class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allMappings as $mapping)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $mapping['zone_code'] }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    {{ $mapping['bodega_code'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($mapping['source'] === 'database')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"></path>
                                        <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"></path>
                                        <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"></path>
                                    </svg>
                                    Base de Datos
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                    Config File
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($mapping['source'] === 'database' && isset($mapping['id']))
                                <form action="{{ route('settings.zone-warehouses.destroy', $mapping['id']) }}" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de eliminar esta asignación?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                                @else
                                <span class="text-gray-400 text-xs">Solo config</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay asignaciones</h3>
                                <p class="mt-1 text-sm text-gray-500">Comienza agregando una nueva asignación o sincroniza desde el archivo de configuración.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="col-span-full mt-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-blue-800">¿Cómo funciona?</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Cada <strong>código de zona</strong> representa una zona de entrega geográfica asignada a los usuarios</li>
                            <li>Cada <strong>código de bodega</strong> representa un almacén físico de donde se despachan productos</li>
                            <li>Las asignaciones determinan qué bodega atiende cada zona para verificación de inventario</li>
                            <li>Las asignaciones en <span class="font-semibold">Base de Datos</span> se pueden modificar y eliminar</li>
                            <li>Las asignaciones en <span class="font-semibold">Config File</span> se pueden sincronizar a la base de datos</li>
                            <li>Los usuarios sin zona asignada verán todos los productos como no disponibles</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

