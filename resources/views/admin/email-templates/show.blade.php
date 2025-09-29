@extends('layouts.admin')

@section('title', 'Ver Plantilla de Correo')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ $template->name }}</h1>
                <p class="text-sm text-gray-500">Detalles de la plantilla de correo electrónico</p>
            </div>
            <div class="flex items-center space-x-3">
                <button type="button"
                        id="preview-btn"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg shadow-sm hover:bg-green-100 focus:ring-4 focus:ring-green-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Vista Previa
                </button>
                <a href="{{ route('admin.email-templates.edit', $template) }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg shadow-sm hover:bg-blue-100 focus:ring-4 focus:ring-blue-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
                <a href="{{ route('admin.email-templates.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Volver
                </a>
            </div>
        </div>
    </div>

    <div class="col-span-full">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Detalles de la Plantilla</h3>
                    </div>
                    
                    <div class="p-6 space-y-6">
                        <!-- Template Info Grid -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                                <p class="text-sm text-gray-900">{{ $template->name }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Slug</label>
                                <span class="inline-flex items-center px-2 py-1 text-xs font-mono bg-gray-100 text-gray-800 rounded">
                                    {{ $template->slug }}
                                </span>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                @php
                                    $typeColors = [
                                        'order_status' => 'bg-blue-100 text-blue-800',
                                        'order_confirmation' => 'bg-green-100 text-green-800',
                                        'user_registration' => 'bg-purple-100 text-purple-800',
                                        'contact_form' => 'bg-yellow-100 text-yellow-800'
                                    ];
                                    $colorClass = $typeColors[$template->type] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                    {{ $template->getTypes()[$template->type] }}
                                </span>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                                @if($template->is_active)
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Activa
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                        Inactiva
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Asunto del Correo</label>
                            <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <p class="text-sm text-gray-900">{{ $template->subject }}</p>
                            </div>
                        </div>

                        <!-- Variables -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Variables Personalizadas</label>
                            @if($template->variables && count($template->variables) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($template->variables as $variable)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 border border-blue-200 rounded">
                                            { {{ $variable }} }
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500 italic">No se han definido variables personalizadas</p>
                            @endif
                        </div>

                        <!-- Content -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contenido del Correo</label>
                            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg max-h-96 overflow-y-auto">
                                <pre class="text-sm text-gray-900 whitespace-pre-wrap">{{ $template->body }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Variables Available -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900">Variables Disponibles</h4>
                        <p class="text-xs text-gray-500">Variables predeterminadas para este tipo de plantilla</p>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-2">
                            @foreach($template->getDefaultVariables() as $variable)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded">
                                    { {{ $variable }} }
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Template Stats -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900">Información de la Plantilla</h4>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Creada:</span>
                            <span class="text-sm text-gray-900">{{ $template->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Actualizada:</span>
                            <span class="text-sm text-gray-900">{{ $template->updated_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Longitud del contenido:</span>
                            <span class="text-sm text-gray-900">{{ strlen($template->body) }} caracteres</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 border-b border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900">Acciones Rápidas</h4>
                    </div>
                    <div class="p-4 space-y-2">
                        <a href="{{ route('admin.email-templates.edit', $template) }}" 
                           class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 focus:ring-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Editar Plantilla
                        </a>
                        <button type="button"
                                id="preview-btn-sidebar"
                                class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 focus:ring-2 focus:ring-green-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Vista Previa
                        </button>
                        @if(!in_array($template->slug, ['order_confirmation', 'user_registration', 'contact_form']))
                            <form action="{{ route('admin.email-templates.destroy', $template) }}" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta plantilla?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 focus:ring-2 focus:ring-red-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Eliminar Plantilla
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closePreviewModal()"></div>
        
        <div class="inline-block w-full max-w-4xl px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Vista Previa de Plantilla</h3>
                <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Asunto:</label>
                    <div id="preview-subject" class="mt-1 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contenido:</label>
                    <div id="preview-body" class="mt-1 p-4 bg-gray-50 border border-gray-200 rounded-lg max-h-96 overflow-y-auto text-sm"></div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button onclick="closePreviewModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const previewBtns = document.querySelectorAll('#preview-btn, #preview-btn-sidebar');

    previewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            showPreview({{ $template->id }});
        });
    });
});

function showPreview(templateId) {
    const modal = document.getElementById('previewModal');
    const subjectDiv = document.getElementById('preview-subject');
    const bodyDiv = document.getElementById('preview-body');
    
    // Show loading state
    subjectDiv.textContent = 'Cargando...';
    bodyDiv.textContent = 'Cargando...';
    modal.classList.remove('hidden');

    fetch(`/admin/email-templates/${templateId}/preview`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        subjectDiv.textContent = data.subject;
        bodyDiv.innerHTML = data.body;
    })
    .catch(error => {
        console.error('Error:', error);
        subjectDiv.textContent = 'Error al cargar la vista previa';
        bodyDiv.textContent = 'Error al cargar la vista previa';
    });
}

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
}
</script>
@endsection