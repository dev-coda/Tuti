@extends('layouts.admin')

@section('content')

    @if(session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast('{{ session('success') }}', 'success', 5000);
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: '{{ session('success') }}', type: 'success', duration: 5000 }
                        }));
                    }
                }, 100);
            });
        </script>
        <div class="hidden p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    if (window.showToast) {
                        window.showToast('{{ session('error') }}', 'error', 5000);
                    } else {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: '{{ session('error') }}', type: 'error', duration: 5000 }
                        }));
                    }
                }, 100);
            });
        </script>
        <div class="hidden p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
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

    <!-- Quick Report Cards -->
    <div class="p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Generar Reportes Rápidos</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <!-- Daily Sales Report Card -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-currency-dollar', 'w-6 h-6 text-white')
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold text-purple-800 bg-purple-200 rounded">Nuevo</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Ventas Diarias</h3>
                <p class="text-sm text-gray-600 mb-4">Reporte completo de ventas, pedidos y ticket promedio con filtros avanzados</p>
                <a href="{{ route('admin.reports.daily-sales') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 transition-colors">
                    Ver Reporte
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                </a>
            </div>

            <!-- KPI Report Card -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-chart-bar', 'w-6 h-6 text-white')
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold text-blue-800 bg-blue-200 rounded">Dashboard</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Reporte de KPIs</h3>
                <p class="text-sm text-gray-600 mb-4">Análisis completo de ventas, productos, categorías y zonas con métricas clave</p>
                <a href="{{ route('admin.kpi.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                    Ver Dashboard
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-2')
                </a>
            </div>

            <!-- Monthly Order Export Card -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-calendar', 'w-6 h-6 text-white')
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-200 rounded">Exportar</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Exportación Mensual de Pedidos</h3>
                <p class="text-sm text-gray-600 mb-4">Exporta todos los pedidos de un mes específico en formato Excel</p>
                <form id="monthlyExportForm" method="POST" action="{{ route('orders.export.monthly') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="export_year" class="block text-xs font-medium text-gray-700 mb-1">Año</label>
                            <select id="export_year" name="year" 
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2"
                                required>
                                @for($y = date('Y'); $y >= 2020; $y--)
                                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label for="export_month" class="block text-xs font-medium text-gray-700 mb-1">Mes</label>
                            <select id="export_month" name="month" 
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2"
                                required>
                                @foreach([
                                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                ] as $num => $name)
                                    <option value="{{ $num }}" {{ $num == date('n') ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" id="monthlyExportBtn"
                        class="w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-4 focus:ring-green-300 transition-colors">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 mr-2')
                        <span id="monthlyExportBtnText">Generar Exportación</span>
                    </button>
                    <div id="monthlyExportMessage" class="hidden text-sm mt-2"></div>
                </form>
            </div>

            <!-- User Email Report Card -->
            @if(isset($availableReports[App\Models\Report::TYPE_USER_EMAIL]))
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-envelope', 'w-6 h-6 text-white')
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs font-semibold text-purple-800 bg-purple-200 rounded">Reporte</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Reporte de Correos Electrónicos</h3>
                <p class="text-sm text-gray-600 mb-4">Estadísticas sobre correos electrónicos de usuarios registrados</p>
                <form method="POST" action="{{ route('admin.reports.generate') }}">
                    @csrf
                    <input type="hidden" name="report_type" value="{{ App\Models\Report::TYPE_USER_EMAIL }}">
                    <button type="submit" 
                        class="w-full inline-flex justify-center items-center px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 transition-colors">
                        @svg('heroicon-o-document-arrow-down', 'w-4 h-4 mr-2')
                        Generar Reporte
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>

    <!-- Legacy Report Form (for other report types) -->
    <div class="p-4 bg-white border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Otros Reportes</h2>
        <form id="reportForm" method="POST" action="{{ route('admin.reports.generate') }}" class="space-y-4">
            @csrf
            
            <!-- Report Type Selector -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label for="report_type" class="block mb-2 text-sm font-medium text-gray-900">
                        Seleccionar Reporte
                    </label>
                    <select id="report_type" name="report_type" 
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        <option value="">-- Seleccione un reporte --</option>
                        @foreach($availableReports as $type => $report)
                            @if($type !== App\Models\Report::TYPE_USER_EMAIL && 
                                $type !== 'kpi_export')
                                <option value="{{ $type }}" {{ $selectedReportType == $type ? 'selected' : '' }}>
                                    {{ $report['name'] }}
                                </option>
                            @endif
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

    <!-- Export Files List (for monthly exports) -->
    <div class="p-4">
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Mis Exportaciones Mensuales</h2>
                <p class="text-sm text-gray-600 mt-1">Exportaciones de pedidos por mes</p>
            </div>
            <div id="exportsListContainer" class="p-4">
                <p class="text-sm text-gray-500">Las exportaciones mensuales se gestionan desde la página de Pedidos.</p>
                <a href="{{ route('orders.index') }}" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800">
                    Ir a Pedidos
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-1')
                </a>
            </div>
        </div>
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
            
            if (report.has_filters && report.filters) {
                // Build filter HTML based on report type
                let filterHTML = '';
                
                if (reportType === 'orders_export') {
                    // Orders export filters: from_date, to_date, brand_id, vendor_id
                    filterHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
                                <input type="date" id="from_date" name="from_date" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    value="{{ request('from_date', now()->subDays(30)->format('Y-m-d')) }}">
                            </div>
                            <div>
                                <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
                                <input type="date" id="to_date" name="to_date" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    value="{{ request('to_date', now()->format('Y-m-d')) }}">
                            </div>
                            <div>
                                <label for="brand_id" class="block text-sm font-medium text-gray-700 mb-1">Marca (Opcional)</label>
                                <select id="brand_id" name="brand_id" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    @foreach($brands as $id => $name)
                                        <option value="{{ $id }}" {{ request('brand_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="vendor_id" class="block text-sm font-medium text-gray-700 mb-1">Proveedor (Opcional)</label>
                                <select id="vendor_id" name="vendor_id" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    @foreach($vendors as $id => $name)
                                        <option value="{{ $id }}" {{ request('vendor_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    `;
                } else if (reportType === 'orders_audit_export') {
                    // Orders audit export filter: from_date
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    const yesterdayFormatted = yesterday.toISOString().split('T')[0];

                    filterHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Desde Fecha
                                    <span class="text-xs font-normal text-gray-500">(Por defecto: ayer)</span>
                                </label>
                                <input type="date" id="from_date" name="from_date" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    value="${yesterdayFormatted}">
                                <p class="mt-1 text-xs text-gray-500">
                                    Incluye todos los pedidos desde esta fecha hasta ahora
                                </p>
                            </div>
                            <div class="flex items-center">
                                <div class="text-sm text-gray-600">
                                    <p class="font-medium">El reporte incluye:</p>
                                    <ul class="mt-1 space-y-1 text-xs">
                                        <li>• Pedidos con package quantity</li>
                                        <li>• Pedidos con bonificaciones</li>
                                        <li>• Precios SOAP &lt; $500</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (reportType === 'kpi_export') {
                    // KPI export filters: start_date, end_date
                    filterHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                                <input type="date" id="start_date" name="start_date" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    value="{{ request('start_date', now()->subYear()->format('Y-m-d')) }}">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                                <input type="date" id="end_date" name="end_date" 
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                    value="{{ request('end_date', now()->format('Y-m-d')) }}">
                            </div>
                        </div>
                    `;
                } else {
                    filterHTML = '<p class="text-sm text-gray-600">Los filtros aparecerán aquí cuando estén disponibles.</p>';
                }
                
                filtersContainer.innerHTML = filterHTML;
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

        // Handle monthly export form submission
        const monthlyExportForm = document.getElementById('monthlyExportForm');
        if (monthlyExportForm) {
            monthlyExportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = document.getElementById('monthlyExportBtn');
                const btnText = document.getElementById('monthlyExportBtnText');
                const messageDiv = document.getElementById('monthlyExportMessage');
                
                // Disable button and show loading
                btn.disabled = true;
                btnText.textContent = 'Generando...';
                
                // Submit form via AJAX
                const formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.className = 'text-sm mt-2 text-green-600';
                        messageDiv.textContent = 'La exportación se está generando. Puedes verificar el estado en la sección "Mis Exportaciones" de la página de Pedidos.';
                        messageDiv.classList.remove('hidden');
                        
                        // Reset form after 3 seconds
                        setTimeout(() => {
                            btn.disabled = false;
                            btnText.textContent = 'Generar Exportación';
                            messageDiv.classList.add('hidden');
                        }, 5000);
                    } else {
                        messageDiv.className = 'text-sm mt-2 text-red-600';
                        messageDiv.textContent = data.message || 'Error al generar la exportación';
                        messageDiv.classList.remove('hidden');
                        btn.disabled = false;
                        btnText.textContent = 'Generar Exportación';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageDiv.className = 'text-sm mt-2 text-red-600';
                    messageDiv.textContent = 'Error al generar la exportación. Por favor intenta nuevamente.';
                    messageDiv.classList.remove('hidden');
                    btn.disabled = false;
                    btnText.textContent = 'Generar Exportación';
                });
            });
        }
    });
</script>
@endsection

