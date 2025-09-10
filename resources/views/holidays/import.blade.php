@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Importar Festivos desde CSV</h1>
            <p class="text-sm text-gray-600 mt-2">Sube un archivo CSV para importar festivos masivamente</p>
        </div>
        <div class="flex items-center justify-between">
            <a href="{{ route('holidays.index') }}" class="text-gray-600 hover:text-gray-800">
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
                <p><strong>Formato requerido:</strong> El archivo CSV debe tener las siguientes columnas:</p>
                <ol class="list-decimal list-inside space-y-1 ml-4">
                    <li><code>ID</code> - Opcional (se ignorará al importar)</li>
                    <li><code>Type</code> - Tipo descriptivo (Festivo/Sábado)</li>
                    <li><code>Type_ID</code> - <strong>Requerido</strong>: 1 para Festivo, 2 para Sábado</li>
                    <li><code>Date</code> - <strong>Requerido</strong>: Fecha en formato YYYY-MM-DD</li>
                    <li><code>Day</code> - Día de la semana (opcional)</li>
                    <li><code>Created_At</code> - Fecha de creación (opcional)</li>
                    <li><code>Updated_At</code> - Fecha de actualización (opcional)</li>
                </ol>
            </div>

            <div class="mt-4 p-3 bg-blue-100 rounded text-sm">
                <strong>Ejemplo de fila válida:</strong><br>
                <code>,Festivo,1,2024-12-25,Miércoles,,</code>
            </div>
        </div>

        <!-- Validation Rules -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h4 class="text-md font-medium text-yellow-900 mb-2">⚠️ Reglas de Validación</h4>
            <ul class="text-sm text-yellow-800 space-y-1">
                <li>• <strong>Type_ID</strong> debe ser 1 (Festivo) o 2 (Sábado)</li>
                <li>• <strong>Date</strong> debe estar en formato YYYY-MM-DD</li>
                <li>• Si Type_ID es 2 (Sábado), la fecha debe ser efectivamente un sábado</li>
                <li>• No se permiten fechas duplicadas con el mismo Type_ID</li>
                <li>• Las filas con errores serán omitidas pero el proceso continuará</li>
            </ul>
        </div>

        <!-- Import Form -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Subir Archivo CSV</h3>

                {{ Aire::open()->route('holidays.import.store')->enctype('multipart/form-data') }}

                <div class="space-y-4">
                    <div>
                        {{ Aire::file('csv_file', 'Archivo CSV')
                            ->helpText('Selecciona un archivo CSV con los festivos a importar (máx. 2MB)')
                            ->required() }}
                    </div>

                    <div class="flex items-center space-x-4">
                        {{ Aire::submit('Importar Festivos')
                            ->variant('primary')
                            ->addClass('bg-green-600 hover:bg-green-700') }}

                        <a href="{{ route('holidays.export', ['template' => '1']) }}"
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
                ID,Type,Type_ID,Date,Day,Created_At,Updated_At<br>
                ,Festivo,1,2024-12-25,Miércoles,,<br>
                ,Festivo,1,2024-12-31,Martes,,<br>
                ,Sábado,2,2024-12-28,Sábado,,<br>
                ,Festivo,1,2025-01-01,Miércoles,,
            </div>
            <p class="text-xs text-gray-600 mt-2">
                Copia este contenido a un archivo .csv para usarlo como plantilla
            </p>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit template download
    const templateLink = document.querySelector('a[href*="export"]');
    if (templateLink) {
        templateLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Create a small sample CSV for template
            const sampleData = "ID,Type,Type_ID,Date,Day,Created_At,Updated_At\n,Festivo,1,2024-12-25,Miércoles,,\n,Festivo,1,2024-12-31,Martes,,\n,Sábado,2,2024-12-28,Sábado,,\n,Festivo,1,2025-01-01,Miércoles,,";

            const blob = new Blob([sampleData], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'holidays_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
});
</script>
@endsection
@endsection
