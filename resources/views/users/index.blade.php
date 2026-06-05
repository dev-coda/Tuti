@extends('layouts.admin')


@section('content')


<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
    <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Clientes</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.export.users') }}"
                   class="inline-flex items-center px-3 py-2 text-sm font-medium text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300"
                   title="Generar exportación de clientes">
                    @svg('heroicon-o-arrow-down-on-square', 'w-5 h-5 sm:mr-2')
                    <span class="hidden sm:inline">Exportar clientes</span>
                </a>
                <button type="button" onclick="openClientExportsModal()"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100"
                        title="Ver exportaciones generadas">
                    @svg('heroicon-o-clock', 'w-5 h-5 sm:mr-2')
                    <span class="hidden sm:inline">Mis Exportaciones</span>
                </button>
            </div>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100 ">
            <div class="flex items-center mb-4 sm:mb-0">
               <x-search :home="route('users.index')" />
            </div>
        </div>
    </div>
</div>

<div class="mx-4 mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm">
    <h2 class="font-semibold text-gray-900 mb-2">Sincronización con Dynamics (rutero)</h2>
    <p class="text-gray-600 mb-2 text-xs">Los datos de cliente (nombre, teléfonos, saldo, correo si viene en la respuesta, zonas) se actualizan desde el servicio getRuteros. También se ejecuta al crear un pedido.</p>
    <ul class="text-gray-600 space-y-1 mb-3 text-xs sm:text-sm">
        <li>
            <span class="font-medium">Sync diaria (03:20):</span>
            @if ($dailyRuteroSyncEnabled === '1' || $dailyRuteroSyncEnabled === 1 || $dailyRuteroSyncEnabled === true)
                <span class="text-green-700">activada</span>
            @else
                <span class="text-amber-700">desactivada</span> (ajuste <code class="bg-white px-1 rounded">daily_client_rutero_sync_enabled</code> en configuración)
            @endif
        </li>
        <li>
            <span class="font-medium">Última sync masiva completada:</span>
            {{ $lastRuteroBulkAtFormatted ?? 'Aún no registrada' }}
        </li>
        @if ($lastRuteroBulkSession)
            <li><span class="font-medium">Sesión:</span> <code class="text-xs bg-white px-1 rounded">{{ $lastRuteroBulkSession }}</code></li>
        @endif
    </ul>
    <div class="flex flex-wrap items-center gap-2">
        <form method="post" action="{{ route('bulk-operations.sync-clients-data') }}" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Sincronizar ahora
            </button>
        </form>
        @if ($lastRuteroReportExists && $lastRuteroReportFilename)
            <a href="{{ route('bulk-operations.download-report', ['filename' => $lastRuteroReportFilename]) }}"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Descargar CSV del último informe
            </a>
        @else
            <span class="text-xs text-gray-500">El CSV del informe aparecerá cuando termine el trabajo en cola.</span>
        @endif
        <a href="{{ route('bulk-operations.index') }}" class="text-sm text-blue-600 hover:underline">Ver todos los informes</a>
    </div>
</div>

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                    <thead class="bg-gray-100">
                        <tr>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Nombre
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Razón Social
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Teléfono
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Tipo de Cliente
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Saldo
                            </th>

                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase whitespace-nowrap">
                                Sync Dynamics
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                Estado
                            </th>

                            <th scope="col"
                                class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">

                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 ">

                        <tr class="hover:bg-gray-100">
                            @foreach ($users as $user)
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900  hover:text-blue-500" href="{{ route('users.edit', $user) }}">
                                    <span class="text-base font-semibold ">
                                        {{ $user->name }}
                                    </span>
                                     <small class="text-xs  text-slate-500">
                                        {{ $user->document }}
                                    </small>

                                </a>
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->business_name ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->phone ?: $user->mobile_phone ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                {{ $user->customer_type ?: '-' }}
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                ${{ number_format($user->balance ?? 0, 0, ',', '.') }}
                            </td>

                            <td class="p-4 text-xs text-gray-600 whitespace-nowrap">
                                @if ($user->rutero_synced_at)
                                    {{ $user->rutero_synced_at->timezone(config('app.timezone'))->format('d/m/y H:i') }}
                                @else
                                    —
                                @endif
                            </td>

                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                @if($user->is_locked)
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded">Bloqueado</span>
                                @elseif($user->customer_status == 'No')
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-yellow-500 rounded">Inactivo</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded">Activo</span>
                                @endif
                            </td>




                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('users.edit', $user) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 ">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z">
                                        </path>
                                        <path fill-rule="evenodd"
                                            d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                              
                            </td>
                        </tr>

                        @endforeach
            


                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{ $users->withQueryString()->links() }} 

<!-- Client Exports List Modal -->
<div id="clientExportsModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Mis Exportaciones de Clientes</h3>
            <button onclick="closeClientExportsModal()" class="text-gray-400 hover:text-gray-600">
                @svg('heroicon-o-x-mark', 'w-6 h-6')
            </button>
        </div>
        <div id="clientExportsList" class="space-y-2 max-h-[60vh] overflow-y-auto">
            <div class="text-center py-8 text-gray-500">Cargando exportaciones...</div>
        </div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeClientExportsModal()"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Cerrar
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let clientExportsPollInterval = null;

function openClientExportsModal() {
    document.getElementById('clientExportsModal').classList.remove('hidden');
    loadClientExportsList();
}

function closeClientExportsModal() {
    document.getElementById('clientExportsModal').classList.add('hidden');
    stopClientExportsPolling();
}

async function loadClientExportsList() {
    const container = document.getElementById('clientExportsList');

    try {
        const response = await fetch('{{ route('admin.exports.clients.list') }}');
        const exports = await response.json();

        if (!exports.length) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <p>Aún no tienes exportaciones de clientes.</p>
                    <p class="text-sm mt-2">Usa el botón "Exportar clientes" para generar una.</p>
                </div>`;
            stopClientExportsPolling();
            return;
        }

        container.innerHTML = exports.map(createClientExportCard).join('');

        // Keep polling while any export is still processing.
        if (exports.some(exp => exp.is_processing)) {
            startClientExportsPolling();
        } else {
            stopClientExportsPolling();
        }
    } catch (error) {
        console.error('Error loading client exports:', error);
        container.innerHTML = '<div class="text-center py-8 text-red-500">Error al cargar las exportaciones</div>';
    }
}

function startClientExportsPolling() {
    if (clientExportsPollInterval) return;
    clientExportsPollInterval = setInterval(loadClientExportsList, 5000);
}

function stopClientExportsPolling() {
    if (clientExportsPollInterval) {
        clearInterval(clientExportsPollInterval);
        clientExportsPollInterval = null;
    }
}

function createClientExportCard(exp) {
    let statusBadge = '';
    let actionButton = '';

    if (exp.status === 'completed') {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Completado</span>';
        actionButton = `
            <a href="${exp.download_url}"
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700">
                Descargar
            </a>`;
    } else if (exp.is_processing) {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-yellow-800 bg-yellow-100 rounded-full">Procesando</span>';
        actionButton = `<span class="text-sm text-gray-500">Preparando archivo...</span>`;
    } else if (exp.status === 'failed') {
        statusBadge = '<span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Error</span>';
        actionButton = `<span class="text-sm text-red-600">${exp.error_message || 'Error desconocido'}</span>`;
    }

    return `
        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h4 class="text-sm font-semibold text-gray-900">${exp.label}</h4>
                        ${statusBadge}
                    </div>
                    <div class="text-xs text-gray-600 space-y-1">
                        <p>Creado: ${exp.created_at}</p>
                        ${exp.completed_at ? `<p>Completado: ${exp.completed_at}</p>` : ''}
                        ${exp.total_records ? `<p>Registros: ${exp.total_records.toLocaleString()}</p>` : ''}
                        ${exp.file_size && exp.status === 'completed' ? `<p>Tamaño: ${exp.file_size}</p>` : ''}
                    </div>
                </div>
                <div class="ml-4">${actionButton}</div>
            </div>
        </div>`;
}

document.getElementById('clientExportsModal').addEventListener('click', function (e) {
    if (e.target === this) closeClientExportsModal();
});
</script>
@endsection
