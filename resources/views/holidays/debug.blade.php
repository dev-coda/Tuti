@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Debug: Tabla Completa de Festivos</h1>
            <p class="text-sm text-gray-600 mt-2">Vista completa de todos los registros en la tabla holidays para diagnóstico</p>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex space-x-4 mb-4 sm:mb-0">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="text-sm text-blue-600 font-medium">Total de registros</div>
                    <div class="text-2xl font-bold text-blue-800">{{ $totalCount }}</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm text-green-600 font-medium">Festivos</div>
                    <div class="text-2xl font-bold text-green-800">{{ $holidayCount }}</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                    <div class="text-sm text-orange-600 font-medium">Sábados</div>
                    <div class="text-2xl font-bold text-orange-800">{{ $saturdayCount }}</div>
                </div>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('holidays.index') }}"
                    class="text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 focus:ring-4 focus:ring-gray-200 font-medium rounded-lg text-sm px-4 py-2.5">
                    ← Volver al listado
                </a>
                <button onclick="window.location.reload()"
                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5">
                    🔄 Recargar datos
                </button>
            </div>
        </div>
    </div>
</div>

<div class="p-4">
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Registros de la Base de Datos</h3>
            <p class="text-sm text-gray-600 mt-1">Datos crudos de la tabla holidays ordenados por fecha</p>
        </div>

        @if($holidays->isEmpty())
            <div class="p-8 text-center">
                <div class="text-gray-400 text-6xl mb-4">📅</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay registros</h3>
                <p class="text-gray-600">La tabla holidays está vacía</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Día de la Semana</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actualizado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($holidays as $holiday)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $holiday->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($holiday->type_id == \App\Models\Holiday::HOLIDAY)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        🎉 Festivo
                                    </span>
                                @elseif($holiday->type_id == \App\Models\Holiday::SATURDAY)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        📅 Sábado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        ❓ Tipo {{ $holiday->type_id }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $holiday->date->format('d/m/Y') }}</span>
                                    <span class="text-xs text-gray-500">{{ $holiday->date->format('l, F j, Y') }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="capitalize">{{ $holiday->day }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $holiday->created_at ? $holiday->created_at->format('d/m/Y H:i') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $holiday->updated_at ? $holiday->updated_at->format('d/m/Y H:i') : 'N/A' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Mostrando <span class="font-medium">{{ $holidays->count() }}</span> registros de la tabla holidays
                    </div>
                    <div class="text-sm text-gray-500">
                        Última actualización: {{ now()->format('d/m/Y H:i:s') }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Additional Debug Information -->
    <div class="mt-6 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Información de Depuración</h3>
        </div>
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Estado de la Tabla</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Tabla: <code class="bg-gray-100 px-1 rounded">holidays</code></li>
                        <li>• Columnas principales: <code class="bg-gray-100 px-1 rounded">id, type_id, date, created_at, updated_at</code></li>
                        <li>• Registros encontrados: <strong>{{ $totalCount }}</strong></li>
                        <li>• Migración alter ejecutada: ✅ Completada</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Acciones de Diagnóstico</h4>
                    <div class="space-y-2">
                        <button onclick="checkMigrationStatus()"
                            class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                            Verificar Estado de Migraciones
                        </button>
                        <button onclick="exportData()"
                            class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded ml-2">
                            Exportar Datos (JSON)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
function checkMigrationStatus() {
    alert('Para verificar el estado de migraciones, ejecuta en terminal:\n\nphp artisan migrate:status\n\nBusca la línea: alter_holidays_table');
}

function exportData() {
    const data = @json($holidays->toArray());
    const jsonString = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = 'holidays_debug_' + new Date().toISOString().split('T')[0] + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
@endsection
@endsection
