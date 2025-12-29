@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Importar Calendario de Entrega desde CSV</h1>
            <p class="text-sm text-gray-600 mt-2">Sube un archivo CSV para importar entradas del calendario masivamente</p>
        </div>
        <div class="flex items-center justify-between">
            <a href="{{ route('delivery-calendars.index') }}" class="text-gray-600 hover:text-gray-800">
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
                    <li><code>Año</code> - Ej: "2.025" o "2025"</li>
                    <li><code>Mes</code> - Nombre del mes en español (Ej: "Enero", "Febrero")</li>
                    <li><code>Número de Semana</code> - Número entero (Ej: 3, 4, 5)</li>
                    <li><code>Fecha Inicio</code> - Formato d/m/Y (Ej: "1/1/2025")</li>
                    <li><code>Fecha Fin</code> - Formato d/m/Y (Ej: "19/1/2025")</li>
                    <li><code>Ciclo</code> - A, B o C</li>
                </ol>
            </div>

            <div class="mt-4 p-3 bg-blue-100 rounded text-sm">
                <strong>Ejemplo de fila válida:</strong><br>
                <code>2.025,Enero,3,1/1/2025,19/1/2025,A</code>
            </div>
        </div>

        <!-- Import Form -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Subir Archivo CSV</h3>

                {{ Aire::open()->route('delivery-calendars.import.store')->enctype('multipart/form-data') }}

                <div class="space-y-4">
                    <div>
                        {{ Aire::file('csv_file', 'Archivo CSV')
                            ->helpText('Selecciona un archivo CSV con las entradas del calendario (máx. 2MB)')
                            ->required() }}
                    </div>

                    <div class="flex items-center space-x-4">
                        {{ Aire::submit('Importar Calendario')
                            ->variant('primary')
                            ->addClass('bg-green-600 hover:bg-green-700') }}

                        <a href="{{ route('delivery-calendars.template') }}"
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
                2.025,Enero,3,1/1/2025,19/1/2025,A<br>
                2.025,Enero,4,20/1/2025,26/1/2025,B<br>
                2.025,Enero,5,27/1/2025,2/2/2025,C
            </div>
            <p class="text-xs text-gray-600 mt-2">
                Formato: año,mes,semana,fecha_inicio,fecha_fin,ciclo
            </p>
        </div>
    </div>
</div>

@endsection

