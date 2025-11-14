@extends('layouts.admin')

@section('content')

    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
        <div class="flex flex-col w-full mb-1">
            <div class="mb-4">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Reportes</h1>
                <p class="text-sm text-gray-600 mt-1">Genera y descarga reportes del sistema</p>
            </div>
        </div>
    </div>

    <div class="p-4 bg-white border-b border-gray-200">
        <form id="reportForm" method="POST" action="{{ route('admin.reports.generate') }}" class="space-y-4">
            @csrf
            
            <!-- Report Type Selector -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label for="report_type" class="block mb-2 text-sm font-medium text-gray-900">
                        Seleccionar Reporte
                    </label>
                    <select id="report_type" name="report_type" 
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                        required>
                        <option value="">-- Seleccione un reporte --</option>
                        @foreach($availableReports as $type => $report)
                            <option value="{{ $type }}" {{ $selectedReportType == $type ? 'selected' : '' }}>
                                {{ $report['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @if($selectedReportType && isset($availableReports[$selectedReportType]))
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $availableReports[$selectedReportType]['description'] }}
                        </p>
                    @endif
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 mr-2')
                        Generar Reporte
                    </button>
                </div>
            </div>

            <!-- Dynamic Filters Section (hidden by default, shown when report is selected) -->
            <div id="filtersSection" class="hidden mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Filtros del Reporte</h3>
                <div id="filtersContainer">
                    <!-- Filters will be dynamically inserted here -->
                </div>
            </div>
        </form>
    </div>

    <!-- Reports List -->
    <div class="p-4">
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Mis Reportes</h2>
                <p class="text-sm text-gray-600 mt-1">Los reportes expiran después de 7 días</p>
            </div>

            @if($reports->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3">Nombre</th>
                                <th scope="col" class="px-6 py-3">Tipo</th>
                                <th scope="col" class="px-6 py-3">Estado</th>
                                <th scope="col" class="px-6 py-3">Creado</th>
                                <th scope="col" class="px-6 py-3">Expira</th>
                                <th scope="col" class="px-6 py-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $report)
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">
                                        {{ $report->name }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">
                                            {{ $availableReports[$report->type]['name'] ?? $report->type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($report->status === 'completed')
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800">
                                                Completado
                                            </span>
                                        @elseif($report->status === 'processing')
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-yellow-100 text-yellow-800">
                                                Procesando
                                            </span>
                                        @elseif($report->status === 'pending')
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800">
                                                Pendiente
                                            </span>
                                        @elseif($report->status === 'failed')
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-red-100 text-red-800">
                                                Error
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $report->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($report->expires_at)
                                            @if($report->isExpired())
                                                <span class="text-red-600 text-xs">Expirado</span>
                                            @else
                                                {{ $report->expires_at->format('d/m/Y H:i') }}
                                            @endif
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            @if($report->isReady())
                                                <a href="{{ route('admin.reports.download', $report) }}" 
                                                   class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition-colors"
                                                   title="Descargar">
                                                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                                </a>
                                            @elseif(in_array($report->status, ['pending', 'processing']))
                                                <button onclick="checkReportStatus({{ $report->id }})"
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors"
                                                        title="Verificar estado">
                                                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                                </button>
                                            @endif
                                            
                                            <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" 
                                                  class="inline"
                                                  onsubmit="return confirm('¿Está seguro de eliminar este reporte?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100 transition-colors"
                                                        title="Eliminar">
                                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4 border-t border-gray-200">
                    {{ $reports->links() }}
                </div>
            @else
                <div class="p-8 text-center">
                    <p class="text-gray-500">No has generado ningún reporte aún.</p>
                    <p class="text-sm text-gray-400 mt-2">Selecciona un reporte arriba y haz clic en "Generar Reporte" para comenzar.</p>
                </div>
            @endif
        </div>
    </div>

@endsection

@section('scripts')
<script>
    const availableReports = @json($availableReports);
    
    // Show/hide filters based on selected report
    document.getElementById('report_type').addEventListener('change', function() {
        const reportType = this.value;
        const filtersSection = document.getElementById('filtersSection');
        const filtersContainer = document.getElementById('filtersContainer');
        
        if (reportType && availableReports[reportType]) {
            const report = availableReports[reportType];
            
            if (report.has_filters) {
                // Load filters dynamically (for future reports)
                filtersContainer.innerHTML = '<p class="text-sm text-gray-600">Los filtros aparecerán aquí cuando estén disponibles.</p>';
                filtersSection.classList.remove('hidden');
            } else {
                filtersSection.classList.add('hidden');
            }
        } else {
            filtersSection.classList.add('hidden');
        }
    });

    // Check report status (for pending/processing reports)
    function checkReportStatus(reportId) {
        fetch(`/admin/reports/${reportId}/status`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.is_ready) {
                // Reload page to show download button
                location.reload();
            } else if (data.status === 'failed') {
                alert('Error al generar el reporte: ' + (data.error_message || 'Error desconocido'));
                location.reload();
            } else {
                // Still processing, check again in 3 seconds
                setTimeout(() => checkReportStatus(reportId), 3000);
            }
        })
        .catch(error => {
            console.error('Error checking report status:', error);
        });
    }

    // Auto-check status for processing reports on page load
    document.addEventListener('DOMContentLoaded', function() {
        @foreach($reports as $report)
            @if(in_array($report->status, ['pending', 'processing']))
                setTimeout(() => checkReportStatus({{ $report->id }}), 2000);
            @endif
        @endforeach
    });
</script>
@endsection

