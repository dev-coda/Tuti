@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Importar Ciclos de Rutas desde CSV</h1>
            <p class="text-sm text-gray-600 mt-2">Sube un archivo CSV para importar ciclos de rutas masivamente</p>
        </div>
        <div class="flex items-center justify-between">
            <a href="{{ route('route-cycles.index') }}" class="text-gray-600 hover:text-gray-800">
                ← Volver al listado
            </a>
        </div>
    </div>
</div>

<div class="p-4">
    <div class="max-w-2xl">
        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">📋 Instrucciones para el CSV</h3>
            <div class="text-sm text-blue-800 space-y-2">
                <p><strong>Formato requerido:</strong> El archivo CSV debe tener las siguientes columnas (sin encabezado):</p>
                <ol class="list-decimal list-inside space-y-1 ml-4">
                    <li><code>Ruta</code> - Número de ruta (Ej: "1300", "1645")</li>
                    <li><code>Ciclo</code> - A, B o C</li>
                </ol>
            </div>

            <div class="mt-4 p-3 bg-blue-100 rounded text-sm">
                <strong>Ejemplo de filas válidas:</strong><br>
                <code>1300,A</code><br>
                <code>1301,C</code><br>
                <code>1302,A</code>
            </div>
        </div>

        <!-- Import Form -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Subir Archivo CSV</h3>

                {{ Aire::open()->route('route-cycles.import.store')->enctype('multipart/form-data') }}

                <div class="space-y-4">
                    <div>
                        {{ Aire::file('csv_file', 'Archivo CSV')
                            ->helpText('Selecciona un archivo CSV con los ciclos de rutas (máx. 2MB)')
                            ->required() }}
                    </div>

                    <div class="flex items-center space-x-4">
                        {{ Aire::submit('Importar Ciclos')
                            ->variant('primary')
                            ->addClass('bg-green-600 hover:bg-green-700') }}

                        <a href="{{ route('route-cycles.template') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            📥 Descargar Plantilla CSV
                        </a>
                    </div>
                </div>

                {{ Aire::close() }}
            </div>
        </div>

        <!-- Sample Data -->
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-6">
            <h4 class="text-md font-medium text-gray-900 mb-3">📄 Ejemplo de Archivo CSV</h4>
            <div class="bg-white border rounded p-3 text-sm font-mono text-gray-800 overflow-x-auto">
                1300,A<br>
                1301,C<br>
                1302,A<br>
                1303,B
            </div>
            <p class="text-xs text-gray-600 mt-2">
                Formato: ruta,ciclo
            </p>
        </div>
    </div>
</div>

@endsection

