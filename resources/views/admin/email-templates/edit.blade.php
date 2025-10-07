@extends('layouts.admin')

@section('title', 'Editar Plantilla de Correo')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Plantilla: {{ $template->name }}</h1>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.email-templates.show', $template) }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    Ver
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
        <p class="text-sm text-gray-500">Modifica los datos de la plantilla de correo electrónico</p>
    </div>

    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <form action="{{ route('admin.email-templates.update', $template) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Información de la Plantilla</h3>
                            <p class="text-sm text-gray-500">Configura los datos básicos de la plantilla</p>
                        </div>
                        <div class="flex items-center space-x-2">
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
                            @php
                                $typeColors = [
                                    'order_status' => 'bg-blue-100 text-blue-800',
                                    'order_confirmation' => 'bg-green-100 text-green-800',
                                    'user_registration' => 'bg-purple-100 text-purple-800',
                                    'contact_form' => 'bg-yellow-100 text-yellow-800'
                                ];
                                $colorClass = $typeColors[$template->type] ?? 'bg-gray-100 text-gray-800';
                            @endphp
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $colorClass }}">
                                {{ $template->getTypes()[$template->type] }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Name and Slug Row -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $template->name) }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-300 @enderror"
                                   placeholder="Ej: Confirmación de Pedido"
                                   required>
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                                Slug <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="slug"
                                   name="slug"
                                   value="{{ old('slug', $template->slug) }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('slug') border-red-300 @enderror"
                                   placeholder="Ej: order_confirmation"
                                   required>
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Identificador único para la plantilla (sin espacios, solo letras, números y guiones)</p>
                        </div>
                    </div>

                    <!-- Type and Status Row -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo <span class="text-red-500">*</span>
                            </label>
                            <select id="type"
                                    name="type"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('type') border-red-300 @enderror"
                                    required>
                                <option value="">Seleccionar tipo...</option>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" {{ old('type', $template->type) == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center">
                            <div class="flex items-center h-5">
                                <input type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                       value="1"
                                       {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                                       class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="is_active" class="font-medium text-gray-700">Plantilla activa</label>
                                <p class="text-gray-500">Solo las plantillas activas pueden ser utilizadas por el sistema</p>
                            </div>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                            Asunto del Correo <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="subject"
                               name="subject"
                               value="{{ old('subject', $template->subject) }}"
                               class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('subject') border-red-300 @enderror"
                               placeholder="Ej: Confirmación de tu pedido #{order_id}"
                               required>
                        @error('subject')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Usa variables como {customer_name}, {order_id}, etc. para personalizar el asunto</p>
                    </div>

                    <!-- Variables Helper -->
                    <div id="variables-helper" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">Variables disponibles para este tipo:</h4>
                            <div id="available-variables" class="flex flex-wrap gap-2">
                                <!-- Variables will be populated by JavaScript -->
                            </div>
                            <p class="mt-2 text-xs text-blue-700">Haz clic en una variable para insertarla en el campo de contenido</p>
                        </div>
                    </div>

                    <!-- Body Content with Rich Text Editor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Contenido del Correo <span class="text-red-500">*</span>
                        </label>
                        <div 
                            class="rich-text-editor-mount" 
                            data-content="{{ htmlspecialchars($template->body ?? '', ENT_QUOTES, 'UTF-8') }}"
                            data-name="body"
                        ></div>
                        @error('body')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Usa el editor para formatear el contenido. Puedes usar variables como {customer_name}, {order_id}, etc.</p>
                    </div>

                    <!-- Header and Footer Images -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Header Image -->
                        <div>
                            <label for="header_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Imagen de Encabezado
                            </label>
                            @if($template->header_image)
                                <div class="mb-3">
                                    <img src="{{ asset('storage/' . $template->header_image) }}" 
                                         alt="Header" 
                                         class="max-w-full h-auto rounded-lg border border-gray-200">
                                    <p class="mt-1 text-xs text-gray-500">Imagen actual del encabezado</p>
                                </div>
                            @endif
                            <input type="file"
                                   id="header_image"
                                   name="header_image"
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('header_image') border-red-300 @enderror">
                            @error('header_image')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Imagen que aparecerá en la parte superior del correo (máx. 2MB)</p>
                        </div>

                        <!-- Footer Image -->
                        <div>
                            <label for="footer_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Imagen de Pie de Página
                            </label>
                            @if($template->footer_image)
                                <div class="mb-3">
                                    <img src="{{ asset('storage/' . $template->footer_image) }}" 
                                         alt="Footer" 
                                         class="max-w-full h-auto rounded-lg border border-gray-200">
                                    <p class="mt-1 text-xs text-gray-500">Imagen actual del pie de página</p>
                                </div>
                            @endif
                            <input type="file"
                                   id="footer_image"
                                   name="footer_image"
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('footer_image') border-red-300 @enderror">
                            @error('footer_image')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Imagen que aparecerá en la parte inferior del correo (máx. 2MB)</p>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center space-x-3">
                        <button type="button"
                                id="preview-btn"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg shadow-sm hover:bg-green-100 focus:ring-4 focus:ring-green-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Vista Previa
                        </button>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="{{ route('admin.email-templates.index') }}" 
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 border border-transparent rounded-lg shadow-sm hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Actualizar Plantilla
                        </button>
                    </div>
                </div>
            </form>
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
    const typeSelect = document.getElementById('type');
    const variablesHelper = document.getElementById('variables-helper');
    const availableVariables = document.getElementById('available-variables');
    const bodyTextarea = document.getElementById('body');
    const previewBtn = document.getElementById('preview-btn');

    // Define variables for each template type
    const templateVariables = {
        'order_status': [
            'order_id', 'order_status', 'customer_name', 'customer_email', 
            'order_total', 'order_date', 'delivery_date', 'tracking_url'
        ],
        'order_confirmation': [
            'order_id', 'customer_name', 'customer_email', 'order_total', 
            'order_products', 'delivery_date', 'order_url'
        ],
        'user_registration': [
            'customer_name', 'customer_email', 'activation_link', 'login_url'
        ],
        'contact_form': [
            'contact_name', 'contact_email', 'contact_phone', 'business_name', 
            'city', 'nit', 'message', 'contact_date'
        ]
    };

    // Handle type selection change
    typeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        
        if (selectedType && templateVariables[selectedType]) {
            // Show variables helper
            variablesHelper.classList.remove('hidden');
            
            // Clear and populate available variables
            availableVariables.innerHTML = '';
            templateVariables[selectedType].forEach(variable => {
                const variableButton = document.createElement('button');
                variableButton.type = 'button';
                variableButton.className = 'inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 border border-blue-200 rounded hover:bg-blue-200 focus:ring-2 focus:ring-blue-500';
                variableButton.textContent = `{${variable}}`;
                variableButton.onclick = () => insertVariable(`{${variable}}`);
                availableVariables.appendChild(variableButton);
            });
        } else {
            variablesHelper.classList.add('hidden');
        }
    });

    // Function to insert variable into textarea
    function insertVariable(variable) {
        const start = bodyTextarea.selectionStart;
        const end = bodyTextarea.selectionEnd;
        const text = bodyTextarea.value;
        
        bodyTextarea.value = text.substring(0, start) + variable + text.substring(end);
        
        // Set cursor position after inserted variable
        bodyTextarea.selectionStart = bodyTextarea.selectionEnd = start + variable.length;
        bodyTextarea.focus();
    }

    // Preview functionality
    previewBtn.addEventListener('click', function() {
        showPreview();
    });

    function showPreview() {
        const modal = document.getElementById('previewModal');
        const subjectDiv = document.getElementById('preview-subject');
        const bodyDiv = document.getElementById('preview-body');
        
        // Get current form values
        const subject = document.getElementById('subject').value;
        const body = document.getElementById('body').value;
        const type = document.getElementById('type').value;
        
        // Show modal with current content
        subjectDiv.textContent = subject;
        bodyDiv.innerHTML = body;
        modal.classList.remove('hidden');
    }

    // Trigger type change if there's a current value
    if (typeSelect.value) {
        typeSelect.dispatchEvent(new Event('change'));
    }
});

function closePreviewModal() {
    document.getElementById('previewModal').classList.add('hidden');
}
</script>
@endsection