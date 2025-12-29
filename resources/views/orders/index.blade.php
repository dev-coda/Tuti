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
            <div class="mb-4 flex justify-between">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Pedidos</h1>
                <div class="flex items-center gap-3">
                    <!-- Current export (date range) -->
                    <a href="{{ '/orderexport?from_date=' . (request()->from_date ? request()->from_date : '') . '&to_date=' . (request()->to_date ? request()->to_date : '') . '&brand_id=' . (request()->brand_id ?? '') . '&vendor_id=' . (request()->vendor_id ?? '') }}"
                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                       title="Exportar filtro actual">
                        @svg('heroicon-o-arrow-down-on-square', 'w-5 h-5 mr-1')
                        <span class="hidden sm:inline">Exportar Filtro</span>
                    </a>
                    
                    <!-- Monthly export button -->
                    <button onclick="openMonthlyExportModal()"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors"
                            title="Exportar por mes">
                        @svg('heroicon-o-calendar', 'w-5 h-5 mr-1')
                        <span class="hidden sm:inline">Exportar Mes</span>
                    </button>
                    
                    <!-- View exports history -->
                    <button onclick="openExportsListModal()"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            title="Ver exportaciones">
                        @svg('heroicon-o-document-arrow-down', 'w-5 h-5 mr-1')
                        <span class="hidden sm:inline">Mis Exportaciones</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center mb-4 w-full">
                <form method="GET" action="{{ route('orders.index') }}"
                    class="xl:flex grid grid-cols-1 gap-y-5 w-full xl:space-x-2 space-x-0">

                    <div>

                        <div class="relative w-full sm:w-64 xl:w-96">
                            <input type="text" name='q' placeholder="Buscar" value="{{ request()->q }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 ">
                        </div>
                    </div>

                    <div>
                        <select name="zone"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                            <option value="">Todas las zonas</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone }}" {{ request()->zone == $zone ? 'selected' : '' }}>
                                    {{ $zone }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{ Aire::select($sellers, 'seller_id')->value(request()->seller_id)->groupClass('mb-0') }}
                    {{ Aire::select($brands, 'brand_id')->value(request()->brand_id)->groupClass('mb-0') }}
                    {{ Aire::select($vendors, 'vendor_id')->value(request()->vendor_id)->groupClass('mb-0') }}

                    <div>
                        <label for="from_date" class="block mb-1 text-xs font-medium text-gray-700 sm:hidden">Desde</label>
                        <input type="date" name="from_date" id="from_date" value="{{ request()->from_date }}"
                            placeholder="Fecha desde"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="to_date" class="block mb-1 text-xs font-medium text-gray-700 sm:hidden">Hasta</label>
                        <input type="date" name="to_date" id="to_date" value="{{ request()->to_date }}"
                            placeholder="Fecha hasta"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                    </div>
                    {{ Aire::button('Buscar')->variant()->submit() }}
                    @if(request()->q || request()->seller_id)
                        <a href="{{route('orders.index')}}"
                            class="inline-flex justify-center items-center p-1 text-gray-500 rounded cursor-pointer hover:text-gray-900 hover:bg-gray-100 d">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </a>
                    @endif
                </form>

            </div>


        </div>
    </div>
    <div class="flex flex-col">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div class="overflow-hidden shadow">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                        <thead class="bg-gray-100">
                            <tr>

                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Id
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Fecha
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Cliente
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Estado
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Total
                                </th>

                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Descuentos
                                </th>


                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Productos
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Unidades
                                </th>

                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 ">

                            <tr class="hover:bg-gray-100 text-xs">
                                @foreach ($orders as $order)
                                        <td class="w-20 p-4 text-xs font-normal text-gray-500 whitespace-nowrap">
                                            <a class="flex flex-col text-gray-900  hover:text-blue-500"
                                                href="{{ route('orders.edit', $order) }}">
                                                #{{ $order->id }}
                                            </a>
                                        </td>
                                        <td class="p-4 text-xs font-sm text-gray-900 whitespace-nowra">
                                            {{ $order->created_at->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="p-4 text-xs font-medium text-gray-900 whitespace-nowra">

                                            <div class="flex flex-col">
                                                <a class="flex flex-col text-gray-900  hover:text-blue-500"
                                                    href="{{ route('users.edit', $order->user) }}">
                                                    {{ $order->user->name }}
                                                </a>
                                                @if($order->seller)
                                                    <small class="text-gray-500">Vendedor: {{ $order->seller->name }}</small>
                                                @endif

                                            </div>
                                        </td>

                                        <td class="p-4   text-gray-900 whitespace-nowra">
                                            <x-order-status :status="$order->status_id" />
                                            @if($order->status_id === 7 && $order->scheduled_transmission_date)
                                                <div class="text-xs text-purple-600 mt-1">
                                                    Transmisión: {{ \Carbon\Carbon::parse($order->scheduled_transmission_date)->format('d/m/Y') }}
                                                </div>
                                            @endif
                                            @if($order->status_id === 3)
                                                <form action="{{ route('orders.resend', $order) }}" method="POST" class="inline ml-2">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="inline-flex items-center p-1 border border-gray-300 rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500"
                                                            onclick="return confirm('¿Está seguro que desea reenviar esta orden?')"
                                                            title="Reenviar orden">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>

                                        <td class="p-4  text-gray-900 whitespace-nowra">
                                            {{ number_format($order->total, 2) }}
                                        </td>


                                        <td class="p-4   text-gray-900 whitespace-nowra">
                                            {{ number_format($order->discount, 2) }}
                                        </td>


                                        <td class="p-4  text-gray-900 whitespace-nowra">
                                            {{ $order->products->count() }}
                                        </td>

                                        <td class="p-4  text-gray-900 whitespace-nowra">
                                            {{ $order->products_sum_quantity ?? 0 }}
                                        </td>

                                    </tr>

                                @endforeach



                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($orders->total() > 0)
        <div class="p-4 bg-white border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <p class="text-sm text-gray-700">
                    Mostrando 
                    <span class="font-medium">{{ $orders->firstItem() }}</span> 
                    a 
                    <span class="font-medium">{{ $orders->lastItem() }}</span> 
                    de 
                    <span class="font-medium">{{ number_format($orders->total(), 0, ',', '.') }}</span> 
                    resultados
                </p>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    @else
        <div class="p-4 bg-white border-t border-gray-200">
            <p class="text-sm text-gray-500 text-center">No se encontraron resultados</p>
        </div>
    @endif

<!-- Monthly Export Modal -->
<div id="monthlyExportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Exportar Pedidos por Mes</h3>
            <button onclick="closeMonthlyExportModal()" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

<<<<<<< HEAD
<!-- Monthly Export Modal -->
<div id="monthlyExportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Exportar Pedidos por Mes</h3>
            <button onclick="closeMonthlyExportModal()" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

        <form id="monthlyExportForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Año</label>
                <select name="year" id="exportYear" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                <select name="month" id="exportMonth" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $index => $month)
                        <option value="{{ $index + 1 }}" {{ ($index + 1) == date('n') ? 'selected' : '' }}>{{ $month }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    La exportación se procesará en segundo plano. Recibirás una notificación cuando esté lista.
                </p>
            </div>

            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" onclick="closeMonthlyExportModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Cancelar
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                    Iniciar Exportación
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Exports List Modal -->
<div id="exportsListModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Mis Exportaciones Mensuales</h3>
            <button onclick="closeExportsListModal()" class="text-gray-400 hover:text-gray-600">
                <span class="text-2xl">&times;</span>
            </button>
        </div>

        <div id="exportsList" class="space-y-2">
            <!-- Will be populated by JavaScript -->
            <div class="text-center py-8 text-gray-500">
                Cargando exportaciones...
            </div>
        </div>

        <div class="flex justify-end mt-4">
            <button onclick="closeExportsListModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                Cerrar
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Modal functions
function openMonthlyExportModal() {
    document.getElementById('monthlyExportModal').classList.remove('hidden');
}

function closeMonthlyExportModal() {
    document.getElementById('monthlyExportModal').classList.add('hidden');
}
function openExportsListModal() {
    document.getElementById('exportsListModal').classList.remove('hidden');
    loadExportsList();
}

function closeExportsListModal() {
    document.getElementById('exportsListModal').classList.add('hidden');
}

// Handle monthly export form submission
document.getElementById('monthlyExportForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const year = formData.get('year');
    const month = formData.get('month');
    
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Procesando...';
    
    try {
        const response = await fetch('{{ route("orders.export.monthly") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': formData.get('_token')
            },
            body: JSON.stringify({
                year: parseInt(year),
                month: parseInt(month)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success message
            showNotification(data.message, 'success');
            closeMonthlyExportModal();
            
            // Optionally start polling for export status
            if (data.export_id) {
                pollExportStatus(data.export_id);
            }
        } else {
            showNotification(data.message || 'Error al iniciar exportación', 'error');
        }
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
});

// Load exports list
async function loadExportsList() {
    const container = document.getElementById('exportsList');
    container.innerHTML = '<div class="text-center py-8 text-gray-500">Cargando exportaciones...</div>';
    
    try {
        const response = await fetch('{{ route("admin.exports.list") }}');
        const exports = await response.json();
        
        if (exports.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>No tienes exportaciones mensuales aún</p>
                    <p class="text-sm mt-2">Usa el botón "Exportar Mes" para crear una</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = exports.map(exp => createExportCard(exp)).join('');
    } catch (error) {
        console.error('Error loading exports:', error);
        container.innerHTML = '<div class="text-center py-8 text-red-500">Error al cargar exportaciones</div>';
    }
}

// Create export card HTML
function createExportCard(exp) {
    let statusBadge = '';
    let actionButton = '';
    
    if (exp.status === 'completed') {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">✓ Completado</span>';
        actionButton = `
            <a href="${exp.download_url}" 
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Descargar
            </a>
        `;
    } else if (exp.status === 'processing' || exp.status === 'pending') {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">⏳ Procesando</span>';
        actionButton = `<span class="text-sm text-gray-500">Preparando archivo...</span>`;
    } else if (exp.status === 'failed') {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">✗ Error</span>';
        actionButton = `<span class="text-sm text-red-600">${exp.error_message || 'Error desconocido'}</span>`;
    }
    
    return `
        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h4 class="text-sm font-semibold text-gray-900">${exp.month_name}</h4>
                        ${statusBadge}
                    </div>
                    <div class="text-xs text-gray-600 space-y-1">
                        <p>Creado: ${exp.created_at}</p>
                        ${exp.completed_at ? `<p>Completado: ${exp.completed_at}</p>` : ''}
                        ${exp.total_records ? `<p>Registros: ${exp.total_records.toLocaleString()}</p>` : ''}
                        ${exp.file_size && exp.status === 'completed' ? `<p>Tamaño: ${exp.file_size}</p>` : ''}
                    </div>
                </div>
                <div class="ml-4">
                    ${actionButton}
                </div>
            </div>
        </div>
    `;
}

// Poll export status
function pollExportStatus(exportId) {
    const pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`/exports/${exportId}/status`);
            const data = await response.json();
            
            if (data.is_completed) {
                clearInterval(pollInterval);
                showNotification('Exportación completada y lista para descargar', 'success');
                // Reload exports list if modal is open
                if (!document.getElementById('exportsListModal').classList.contains('hidden')) {
                    loadExportsList();
                }
            } else if (data.has_failed) {
                clearInterval(pollInterval);
                showNotification('Exportación falló: ' + (data.error_message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            console.error('Poll error:', error);
            clearInterval(pollInterval);
        }
    }, 5000); // Poll every 5 seconds
    
    // Stop polling after 5 minutes
    setTimeout(() => clearInterval(pollInterval), 300000);
}

// Show notification
function showNotification(message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-green-100 text-green-700' : 
                    type === 'error' ? 'bg-red-100 text-red-700' : 
                    'bg-blue-100 text-blue-700';
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${bgColor} z-50 max-w-md`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span class="font-medium">${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.remove(), 5000);
}

// Close modals on outside click
document.getElementById('monthlyExportModal').addEventListener('click', function(e) {
    if (e.target === this) closeMonthlyExportModal();
});

document.getElementById('exportsListModal').addEventListener('click', function(e) {
    if (e.target === this) closeExportsListModal();
});
</script>
@endsection