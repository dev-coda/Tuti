@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Editar Promoción</h1>
                        <p class="text-gray-600">Modifica la configuración de la promoción avanzada</p>
                    </div>
                    <a href="{{ route('promocion.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Volver
                    </a>
                </div>

                <form action="{{ route('promocion.update', $promocion) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Nombre de la Promoción</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $promocion->name) }}" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
                                <textarea name="description" id="description" rows="3"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">{{ old('description', $promocion->description) }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Discount Configuration -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Configuración del Descuento</h3>
                            
                            <div>
                                <label for="discount_type" class="block text-sm font-medium text-gray-700">Tipo de Descuento</label>
                                <select name="discount_type" id="discount_type" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="percentage" {{ old('discount_type', $promocion->discount_type) == 'percentage' ? 'selected' : '' }}>Porcentaje (%)</option>
                                    <option value="fixed_amount" {{ old('discount_type', $promocion->discount_type) == 'fixed_amount' ? 'selected' : '' }}>Valor Fijo ($)</option>
                                </select>
                                @error('discount_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="discount_value" class="block text-sm font-medium text-gray-700">Valor del Descuento</label>
                                <input type="number" name="discount_value" id="discount_value" value="{{ old('discount_value', $promocion->discount_value) }}" 
                                    step="0.01" min="0" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('discount_value')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Validity Period -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Período de Validez</h3>
                            
                            <div>
                                <label for="valid_from" class="block text-sm font-medium text-gray-700">Válido Desde</label>
                                <input type="datetime-local" name="valid_from" id="valid_from" value="{{ old('valid_from', $promocion->valid_from->format('Y-m-d\TH:i')) }}" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('valid_from')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="valid_to" class="block text-sm font-medium text-gray-700">Válido Hasta</label>
                                <input type="datetime-local" name="valid_to" id="valid_to" value="{{ old('valid_to', $promocion->valid_to->format('Y-m-d\TH:i')) }}" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('valid_to')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Usage Limits -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Límites de Uso</h3>
                            
                            <div>
                                <label for="usage_limit" class="block text-sm font-medium text-gray-700">Límite de Usos (Opcional)</label>
                                <input type="number" name="usage_limit" id="usage_limit" value="{{ old('usage_limit', $promocion->usage_limit) }}" 
                                    min="1"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <p class="mt-1 text-sm text-gray-500">Dejar vacío para sin límite</p>
                                @error('usage_limit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Usos Actuales</label>
                                <div class="mt-1 text-sm text-gray-900">{{ $promocion->current_usage }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Application Scope -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900">Ámbito de Aplicación</h3>
                        
                        <div>
                            <label for="level" class="block text-sm font-medium text-gray-700">Aplicar a</label>
                            <select name="level" id="level" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="products" {{ old('level', $promocion->level) == 'products' ? 'selected' : '' }}>Productos Específicos</option>
                                <option value="categories" {{ old('level', $promocion->level) == 'categories' ? 'selected' : '' }}>Categorías</option>
                                <option value="brands" {{ old('level', $promocion->level) == 'brands' ? 'selected' : '' }}>Marcas</option>
                                <option value="vendors" {{ old('level', $promocion->level) == 'vendors' ? 'selected' : '' }}>Proveedores</option>
                                <option value="zones" {{ old('level', $promocion->level) == 'zones' ? 'selected' : '' }}>Zonas</option>
                            </select>
                            @error('level')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="level_ids_section" class="hidden">
                            <label for="level_ids" class="block text-sm font-medium text-gray-700">Seleccionar Elementos</label>
                            
                            <!-- Search Input -->
                            <div class="mt-1 mb-3">
                                <input type="text" id="element_search" placeholder="Buscar elementos..." 
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            
                            <!-- Selected Elements Display -->
                            <div id="selected_elements" class="mb-3">
                                <div class="text-sm text-gray-500">Elementos seleccionados:</div>
                                <div id="selected_list" class="flex flex-wrap gap-2 mt-1"></div>
                            </div>
                            
                            <!-- Available Elements List -->
                            <div id="available_elements" class="max-h-60 overflow-y-auto border border-gray-300 rounded-md">
                                <div class="p-3 text-center text-gray-500">Selecciona un tipo para ver los elementos disponibles</div>
                            </div>
                            
                            <!-- Hidden input for form submission -->
                            <input type="hidden" name="level_ids" id="level_ids_input" value="{{ json_encode(old('level_ids', $promocion->level_ids ?? [])) }}">
                            
                            @error('level_ids')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Minimum Requirements -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Requisitos Mínimos</h3>
                            
                            <div>
                                <label for="minimum_cart_value" class="block text-sm font-medium text-gray-700">Valor Mínimo del Carrito (Opcional)</label>
                                <input type="number" name="minimum_cart_value" id="minimum_cart_value" value="{{ old('minimum_cart_value', $promocion->minimum_cart_value) }}" 
                                    step="0.01" min="0"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('minimum_cart_value')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="minimum_cart_units" class="block text-sm font-medium text-gray-700">Unidades Mínimas del Carrito (Opcional)</label>
                                <input type="number" name="minimum_cart_units" id="minimum_cart_units" value="{{ old('minimum_cart_units', $promocion->minimum_cart_units) }}" 
                                    min="1"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('minimum_cart_units')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-900">Estado</h3>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="active" id="active" value="1" {{ old('active', $promocion->active) ? 'checked' : '' }}
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="active" class="ml-2 block text-sm text-gray-900">
                                    Activo
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Actualizar Promoción
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const levelSelect = document.getElementById('level');
    const levelIdsSection = document.getElementById('level_ids_section');
    const elementSearch = document.getElementById('element_search');
    const availableElements = document.getElementById('available_elements');
    const selectedList = document.getElementById('selected_list');
    const levelIdsInput = document.getElementById('level_ids_input');
    
    let selectedElements = [];
    let currentType = '';
    let searchTimeout = null;

    // Initialize with existing selected elements
    const existingIds = JSON.parse(levelIdsInput.value || '[]');
    selectedElements = existingIds.map(id => ({ id: parseInt(id), name: `Elemento ${id}`, description: 'Cargando...' }));

    levelSelect.addEventListener('change', function() {
        const value = this.value;
        currentType = value;
        
        if (value === 'zones') {
            levelIdsSection.classList.add('hidden');
            return;
        }

        levelIdsSection.classList.remove('hidden');
        loadElements(value);
    });

    elementSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (currentType) {
                loadElements(currentType, this.value);
            }
        }, 300);
    });

    function loadElements(type, search = '') {
        const url = new URL('{{ route("promociones.elements") }}', window.location.origin);
        url.searchParams.append('type', type);
        if (search) {
            url.searchParams.append('search', search);
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                displayElements(data);
                // Update selected elements with real data
                updateSelectedElementsWithRealData(data);
            })
            .catch(error => {
                console.error('Error loading elements:', error);
                availableElements.innerHTML = '<div class="p-3 text-center text-red-500">Error al cargar elementos</div>';
            });
    }

    function updateSelectedElementsWithRealData(elements) {
        selectedElements = selectedElements.map(selected => {
            const realElement = elements.find(el => el.id === selected.id);
            if (realElement) {
                return {
                    id: realElement.id,
                    name: realElement.name,
                    description: realElement.description
                };
            }
            return selected;
        });
        updateSelectedDisplay();
    }

    function displayElements(elements) {
        if (elements.length === 0) {
            availableElements.innerHTML = '<div class="p-3 text-center text-gray-500">No se encontraron elementos</div>';
            return;
        }

        const elementsHtml = elements.map(element => {
            const isSelected = selectedElements.some(selected => selected.id === element.id);
            return `
                <div class="p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer ${isSelected ? 'bg-blue-50' : ''}" 
                     onclick="toggleElement(${element.id}, '${element.name.replace(/'/g, "\\'")}', '${element.description.replace(/'/g, "\\'")}')">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900">${element.name}</div>
                            <div class="text-sm text-gray-500">${element.description}</div>
                        </div>
                        ${isSelected ? '<span class="text-blue-600">✓</span>' : ''}
                    </div>
                </div>
            `;
        }).join('');

        availableElements.innerHTML = elementsHtml;
    }

    function toggleElement(id, name, description) {
        const existingIndex = selectedElements.findIndex(element => element.id === id);
        
        if (existingIndex > -1) {
            // Remove element
            selectedElements.splice(existingIndex, 1);
        } else {
            // Add element
            selectedElements.push({ id, name, description });
        }
        
        updateSelectedDisplay();
        updateHiddenInput();
        loadElements(currentType, elementSearch.value); // Refresh to update selection state
    }

    function updateSelectedDisplay() {
        if (selectedElements.length === 0) {
            selectedList.innerHTML = '<span class="text-gray-400 text-sm">Ningún elemento seleccionado</span>';
            return;
        }

        const selectedHtml = selectedElements.map(element => `
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                ${element.name}
                <button type="button" onclick="removeElement(${element.id})" class="ml-1 text-blue-600 hover:text-blue-800">
                    ×
                </button>
            </span>
        `).join('');

        selectedList.innerHTML = selectedHtml;
    }

    function removeElement(id) {
        selectedElements = selectedElements.filter(element => element.id !== id);
        updateSelectedDisplay();
        updateHiddenInput();
        loadElements(currentType, elementSearch.value); // Refresh to update selection state
    }

    function updateHiddenInput() {
        const ids = selectedElements.map(element => element.id);
        levelIdsInput.value = JSON.stringify(ids);
    }

    // Make functions globally available
    window.toggleElement = toggleElement;
    window.removeElement = removeElement;

    // Initialize display
    updateSelectedDisplay();

    // Trigger change event on page load
    levelSelect.dispatchEvent(new Event('change'));
});
</script>
@endsection
