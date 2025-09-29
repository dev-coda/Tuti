@extends('layouts.admin')

@section('title', 'Nueva Plantilla de Correo')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Nueva Plantilla de Correo</h1>
            <a href="{{ route('admin.email-templates.index') }}" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Volver
            </a>
        </div>
        <p class="text-sm text-gray-500">Crea una nueva plantilla de correo electrónico para el sistema</p>
    </div>

    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <form action="{{ route('admin.email-templates.store') }}" method="POST" class="space-y-6">
                @csrf
                
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                    <p class="text-sm text-gray-500">Configura los datos básicos de la plantilla</p>
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
                                   value="{{ old('name') }}"
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
                                   value="{{ old('slug') }}"
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
                                    <option value="{{ $key }}" {{ old('type') == $key ? 'selected' : '' }}>
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
                                       {{ old('is_active', true) ? 'checked' : '' }}
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
                               value="{{ old('subject') }}"
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

                    <!-- Body Content -->
                    <div>
                        <label for="body" class="block text-sm font-medium text-gray-700 mb-2">
                            Contenido del Correo <span class="text-red-500">*</span>
                        </label>
                        <textarea id="body"
                                  name="body"
                                  rows="15"
                                  class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('body') border-red-300 @enderror"
                                  placeholder="Escribe el contenido del correo aquí..."
                                  required>{{ old('body') }}</textarea>
                        @error('body')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Puedes usar HTML para formatear el contenido. Usa variables como {customer_name}, {order_id}, etc.</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end px-6 py-4 bg-gray-50 border-t border-gray-200 space-x-3">
                    <a href="{{ route('admin.email-templates.index') }}" 
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-700 border border-transparent rounded-lg shadow-sm hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const variablesHelper = document.getElementById('variables-helper');
    const availableVariables = document.getElementById('available-variables');
    const bodyTextarea = document.getElementById('body');

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

    // Auto-generate slug from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    
    nameInput.addEventListener('input', function() {
        if (!slugInput.value || slugInput.value === slugInput.defaultValue) {
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '_')
                .replace(/-+/g, '_')
                .replace(/_{2,}/g, '_')
                .replace(/^_|_$/g, '');
            slugInput.value = slug;
        }
    });

    // Trigger type change if there's an old value
    if (typeSelect.value) {
        typeSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endsection