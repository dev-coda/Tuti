@extends('layouts.admin')

@section('title', 'Configuraciones del Sistema')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Configuraciones del Sistema</h1>
                <p class="text-sm text-gray-500">Gestiona las configuraciones generales del sistema</p>
            </div>
            <div class="flex items-center space-x-3">
                @php($lastSync = \App\Models\Setting::where('key','inventory_last_synced_at')->value('value'))
                <div class="text-sm text-gray-600 bg-gray-50 px-3 py-2 rounded-lg">
                    <span class="font-medium">Última sincronización:</span>
                    <span class="text-gray-900">{{ $lastSync ? \Carbon\Carbon::parse($lastSync)->format('d/m/Y H:i') : 'N/A' }}</span>
                </div>
                <form action="{{ route('settings.sync-inventory') }}" method="POST" id="syncForm" onsubmit="return handleSyncSubmit()">
                    @csrf
                    <button type="submit" id="syncButton" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-lg shadow-sm hover:bg-orange-700 focus:ring-4 focus:ring-orange-300 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg id="syncIcon" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span id="syncText">Sincronizar Inventario</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-span-full mb-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones Rápidas</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('settings.mailer') }}" 
                   class="inline-flex items-center px-4 py-3 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 focus:ring-2 focus:ring-blue-500">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <div class="font-medium">Configuración de Correo</div>
                        <div class="text-xs text-blue-600">SMTP, Mailgun, etc.</div>
                    </div>
                </a>

                <a href="{{ route('admin.email-templates.index') }}" 
                   class="inline-flex items-center px-4 py-3 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 focus:ring-2 focus:ring-purple-500">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div>
                        <div class="font-medium">Plantillas de Correo</div>
                        <div class="text-xs text-purple-600">Editar plantillas</div>
                    </div>
                </a>

                <a href="/updateproductprices" 
                   class="inline-flex items-center px-4 py-3 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 focus:ring-2 focus:ring-green-500">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                    <div>
                        <div class="font-medium">Actualizar Precios</div>
                        <div class="text-xs text-green-600">Sincronizar precios</div>
                    </div>
                </a>

                <button onclick="showSearch()" 
                        class="inline-flex items-center px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 focus:ring-2 focus:ring-gray-500">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <div>
                        <div class="font-medium">Buscar Configuración</div>
                        <div class="text-xs text-gray-600">Filtrar configuraciones</div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Table -->
    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            @if($settings->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-500">
                        <thead class="text-xs text-gray-700 bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-medium">Nombre</th>
                                <th scope="col" class="px-6 py-3 font-medium">Valor</th>
                                <th scope="col" class="px-6 py-3 font-medium">Última Modificación</th>
                                <th scope="col" class="px-6 py-3 font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($settings as $setting)
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                @if(str_contains($setting->key, 'email'))
                                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                    </svg>
                                                @elseif(str_contains($setting->key, 'phone'))
                                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                    </svg>
                                                @elseif(str_contains($setting->key, 'google'))
                                                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                            <div class="ml-3">
                                                <div class="font-medium text-gray-900">{{ $setting->name }}</div>
                                                <div class="text-xs text-gray-500">{{ $setting->key }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs">
                                            @if(strlen($setting->value) > 50)
                                                <span class="text-sm text-gray-900" title="{{ $setting->value }}">
                                                    {{ Str::limit($setting->value, 50) }}
                                                </span>
                                                <button onclick="showFullValue('{{ $setting->name }}', '{{ addslashes($setting->value) }}')" 
                                                        class="ml-1 text-xs text-blue-600 hover:text-blue-800">
                                                    Ver completo
                                                </button>
                                            @else
                                                <span class="text-sm text-gray-900">{{ $setting->value }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-500">
                                            {{ $setting->updated_at->format('d/m/Y H:i') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('settings.edit', $setting) }}"
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 focus:ring-2 focus:ring-blue-500">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-between px-6 py-3 bg-white border-t border-gray-200">
                    <div class="flex items-center text-sm text-gray-700">
                        Mostrando {{ $settings->firstItem() }} a {{ $settings->lastItem() }} de {{ $settings->total() }} resultados
                    </div>
                    <div>
                        {{ $settings->links() }}
                    </div>
                </div>
            @else
                <div class="p-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay configuraciones</h3>
                    <p class="text-gray-500">No se encontraron configuraciones del sistema.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Full Value Modal -->
<div id="valueModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeValueModal()"></div>
        
        <div class="inline-block w-full max-w-2xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="valueModalTitle">Valor Completo</h3>
                <button onclick="closeValueModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="mb-4">
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                    <pre id="valueModalContent" class="text-sm text-gray-900 whitespace-pre-wrap"></pre>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button onclick="closeValueModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showFullValue(name, value) {
    document.getElementById('valueModalTitle').textContent = name;
    document.getElementById('valueModalContent').textContent = value;
    document.getElementById('valueModal').classList.remove('hidden');
}

function closeValueModal() {
    document.getElementById('valueModal').classList.add('hidden');
}

function showSearch() {
    // This would integrate with your existing search component
    const searchInput = document.querySelector('input[type="search"]');
    if (searchInput) {
        searchInput.focus();
    }
}

function handleSyncSubmit() {
    const button = document.getElementById('syncButton');
    const icon = document.getElementById('syncIcon');
    const text = document.getElementById('syncText');
    
    // Show loading state temporarily
    button.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'Iniciando sincronización...';
    
    // Re-enable after a short delay (since it's async)
    setTimeout(() => {
        button.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'Sincronizar Inventario';
    }, 2000);
    
    return true;
}
</script>
@endsection