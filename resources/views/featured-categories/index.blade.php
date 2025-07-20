@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Categorías Destacadas</h1>
        </div>
        <div class="items-center flex space-x-5">
            <!-- Toggle Switch -->
            <div class="flex items-center space-x-3">
                <label class="inline-flex items-center cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="useMostPopularToggle"
                        @if($useMostPopular) checked @endif
                        class="sr-only peer"
                        onchange="toggleMostPopular(this.checked)"
                    >
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-900">Usar más populares</span>
                </label>
            </div>

            <button 
                id="addCategoryBtn"
                @if($featuredCategories->count() >= 3 || $useMostPopular) disabled @endif
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed">
                Agregar categoría
            </button>
            <span class="text-sm text-gray-600">{{ $featuredCategories->count() }} / 3 categorías</span>
        </div>
    </div>
</div>

<!-- Section Title Configuration -->
<div class="p-4 bg-gray-50 border-b border-gray-200">
    <div class="w-full">
        <h3 class="text-lg font-medium text-gray-900 mb-3">Configuración del título de la sección</h3>
        <div class="flex items-center space-x-3">
            <div class="flex-1">
                <label for="sectionTitle" class="block text-sm font-medium text-gray-700 mb-1">
                    Título que aparece en el sitio web
                </label>
                <input 
                    type="text" 
                    id="sectionTitle"
                    value="{{ $sectionTitle }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Ej: Categorías, Categorías Destacadas, Explora nuestras categorías, etc."
                    maxlength="255"
                >
            </div>
            <button 
                type="button"
                onclick="updateSectionTitle()"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                Actualizar
            </button>
        </div>
        <p class="text-sm text-gray-500 mt-1">
            Este título aparecerá en la sección de categorías destacadas en la página principal del sitio web.
        </p>
    </div>
</div>

@if($useMostPopular)
<div class="p-4 bg-yellow-50 border-l-4 border-yellow-400">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                Actualmente se están mostrando las categorías más populares automáticamente. Las categorías destacadas seleccionadas manualmente no se están utilizando.
            </p>
        </div>
    </div>
</div>
@endif

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Categoría
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Personalización
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Descripción
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($featuredCategories as $featured)
                        <tr class="hover:bg-gray-100 @if($useMostPopular) opacity-50 @endif">
                            <td class="flex items-center p-4 mr-12 space-x-4 max-w-sm lg:mr-0">
                                @if($featured->display_image)
                                    <img class="w-10 h-10 rounded-lg object-cover" src="{{ $featured->display_image }}" alt="{{ $featured->display_title }}">
                                    @if($featured->custom_image)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Personalizada
                                        </span>
                                    @endif
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="font-medium text-gray-900 truncate">
                                    <div>{{ $featured->category->name }}</div>
                                    @if($featured->custom_title)
                                        <div class="text-sm text-blue-600">Mostrar como: "{{ $featured->custom_title }}"</div>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4">
                                <button 
                                    onclick="openCustomizationModal({{ $featured->id }})"
                                    @if($useMostPopular) disabled @endif
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-blue-600 rounded-lg hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                    </svg>
                                    Personalizar
                                </button>
                                @if($featured->custom_title || $featured->custom_image || $featured->custom_url)
                                    <div class="mt-2 text-xs text-green-600">
                                        Personalizado
                                    </div>
                                @endif
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 max-w-xs truncate">
                                {{ $featured->category->description ?: '-' }}
                            </td>
                            <td class="p-4 space-x-2">
                                {{ Aire::open()->route('featured-categories.destroy', $featured) }}
                                    <button 
                                        type="submit" 
                                        @if($useMostPopular) disabled @endif
                                        onclick="return confirm('¿Está seguro de eliminar esta categoría de destacadas?')" 
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-red-600 rounded-lg hover:bg-red-800 focus:ring-4 focus:ring-red-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Eliminar
                                    </button>
                                {{ Aire::close() }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-500">
                                @if($useMostPopular)
                                    Las categorías más populares se están mostrando automáticamente
                                @else
                                    No hay categorías destacadas
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeModal()"></div>

        <!-- Modal panel -->
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                    Buscar categoría
                </h3>
                
                <input 
                    type="text" 
                    id="searchInput"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por nombre..."
                >
                
                <div id="searchResults" class="mt-4 max-h-60 overflow-y-auto">
                    <!-- Search results will appear here -->
                </div>
                
                <div id="selectedCategory" class="mt-4 p-3 bg-gray-100 rounded-md hidden">
                    <!-- Selected category will appear here -->
                </div>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                <button
                    type="button"
                    id="saveBtn"
                    disabled
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Guardar
                </button>
                <button
                    type="button"
                    onclick="closeModal()"
                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customization Modal -->
<div id="customizationModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeCustomizationModal()"></div>

        <!-- Modal panel -->
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                    Personalizar categoría destacada
                </h3>
                
                <form id="customizationForm" enctype="multipart/form-data">
                    <input type="hidden" id="featuredCategoryId" value="">
                    
                    <!-- Custom Image Section -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Imagen personalizada
                        </label>
                        <div class="flex items-center space-x-4">
                            <div id="currentImagePreview" class="hidden">
                                <img id="currentImage" src="" alt="Imagen actual" class="w-20 h-20 object-cover rounded-lg">
                                <button type="button" onclick="removeCustomImage()" class="mt-1 text-xs text-red-600 hover:text-red-800">
                                    Eliminar imagen personalizada
                                </button>
                            </div>
                            <div>
                                <input type="file" id="customImageInput" name="custom_image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF hasta 2MB. Deja vacío para usar la imagen original de la categoría.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Title Section -->
                    <div class="mb-6">
                        <label for="customTitle" class="block text-sm font-medium text-gray-700 mb-2">
                            Título personalizado
                        </label>
                        <input 
                            type="text" 
                            id="customTitle" 
                            name="custom_title"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Deja vacío para usar el nombre original de la categoría"
                            maxlength="255"
                        >
                        <p class="text-xs text-gray-500 mt-1">Este título aparecerá en lugar del nombre original de la categoría.</p>
                    </div>

                    <!-- Custom URL Section -->
                    <div class="mb-6">
                        <label for="customUrl" class="block text-sm font-medium text-gray-700 mb-2">
                            URL personalizada
                        </label>
                        <input 
                            type="url" 
                            id="customUrl" 
                            name="custom_url"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Deja vacío para usar la URL original de la categoría"
                        >
                        <p class="text-xs text-gray-500 mt-1">Esta URL se usará cuando se haga clic en la categoría. Debe ser una URL completa (ej: https://ejemplo.com/pagina).</p>
                    </div>

                    <!-- Original Category Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Información original de la categoría:</h4>
                        <div id="originalCategoryInfo">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                <button
                    type="button"
                    onclick="saveCustomization()"
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Guardar personalización
                </button>
                <button
                    type="button"
                    onclick="closeCustomizationModal()"
                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let selectedCategoryId = null;
let searchTimeout = null;

// Toggle most popular categories
function toggleMostPopular(checked) {
    console.log('Toggle clicked, checked:', checked);
    
    fetch('{{ route('featured-categories.toggle-most-popular') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            use_most_popular: checked
        })
    })
    .then(response => {
        console.log('Response received:', response);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('Setting saved successfully, reloading page');
            window.location.reload();
        } else {
            console.error('Error response:', data);
            alert('Error al actualizar la configuración');
            // Reset toggle to previous state
            document.getElementById('useMostPopularToggle').checked = !checked;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error al actualizar la configuración');
        // Reset toggle to previous state
        document.getElementById('useMostPopularToggle').checked = !checked;
    });
}

// Open modal
document.getElementById('addCategoryBtn').addEventListener('click', function() {
    document.getElementById('categoryModal').classList.remove('hidden');
    document.getElementById('searchInput').focus();
});

// Close modal
function closeModal() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('selectedCategory').innerHTML = '';
    document.getElementById('selectedCategory').classList.add('hidden');
    document.getElementById('saveBtn').disabled = true;
    selectedCategoryId = null;
}

// Search categories
document.getElementById('searchInput').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value;
    
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`{{ route('featured-categories.search') }}?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(categories => {
                let html = '';
                if (categories.length === 0) {
                    html = '<p class="text-gray-500 text-center py-4">No se encontraron categorías</p>';
                } else {
                    categories.forEach(category => {
                        const imageUrl = category.image ? `/storage/${category.image}` : '';
                        html += `
                            <div class="p-2 hover:bg-gray-100 cursor-pointer rounded" onclick="selectCategory(${category.id}, '${category.name}', '${category.slug}', '${imageUrl}')">
                                <div class="flex items-center space-x-3">
                                    ${imageUrl ? 
                                        `<img src="${imageUrl}" class="w-10 h-10 rounded object-cover">` :
                                        `<div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                            </svg>
                                        </div>`
                                    }
                                    <div>
                                        <p class="font-medium">${category.name}</p>
                                        <p class="text-sm text-gray-500">${category.slug}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                document.getElementById('searchResults').innerHTML = html;
            });
    }, 300);
});

// Select category
function selectCategory(id, name, slug, imageUrl) {
    selectedCategoryId = id;
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('searchInput').value = '';
    
    let html = `
        <div class="flex items-center space-x-3">
            ${imageUrl ? 
                `<img src="${imageUrl}" class="w-12 h-12 rounded object-cover">` :
                `<div class="w-12 h-12 rounded bg-gray-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                </div>`
            }
            <div>
                <p class="font-medium">${name}</p>
                <p class="text-sm text-gray-500">${slug}</p>
            </div>
        </div>
    `;
    
    document.getElementById('selectedCategory').innerHTML = html;
    document.getElementById('selectedCategory').classList.remove('hidden');
    document.getElementById('saveBtn').disabled = false;
}

// Save category
document.getElementById('saveBtn').addEventListener('click', function() {
    if (!selectedCategoryId) return;
    
    fetch('{{ route('featured-categories.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            category_id: selectedCategoryId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error al agregar la categoría');
        }
    })
    .catch(error => {
        alert('Error al agregar la categoría');
    });
});

// Update section title
function updateSectionTitle() {
    const titleInput = document.getElementById('sectionTitle');
    const title = titleInput.value.trim();
    
    if (title === '') {
        alert('El título no puede estar vacío');
        return;
    }
    
    console.log('Updating section title to:', title);
    
    fetch('{{ route('featured-categories.update-title') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            title: title
        })
    })
    .then(response => {
        console.log('Title update response:', response);
        return response.json();
    })
    .then(data => {
        console.log('Title update response data:', data);
        if (data.success) {
            // Show success message
            const button = document.querySelector('button[onclick="updateSectionTitle()"]');
            const originalText = button.textContent;
            button.textContent = '¡Guardado!';
            button.classList.remove('bg-green-600', 'hover:bg-green-700');
            button.classList.add('bg-green-500');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-500');
                button.classList.add('bg-green-600', 'hover:bg-green-700');
            }, 2000);
        } else {
            console.error('Error response:', data);
            alert('Error al actualizar el título');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error al actualizar el título');
    });
}

// Featured categories data for JavaScript
const featuredCategories = @json($featuredCategoriesData);

// Customization Modal Functions
function openCustomizationModal(featuredCategoryId) {
    const featured = featuredCategories.find(f => f.id === featuredCategoryId);
    if (!featured) return;

    // Set the featured category ID
    document.getElementById('featuredCategoryId').value = featuredCategoryId;
    
    // Populate form with current values
    document.getElementById('customTitle').value = featured.custom_title || '';
    document.getElementById('customUrl').value = featured.custom_url || '';
    
    // Show current custom image if exists
    const currentImagePreview = document.getElementById('currentImagePreview');
    const currentImage = document.getElementById('currentImage');
    
    if (featured.custom_image) {
        currentImage.src = `/storage/${featured.custom_image}`;
        currentImagePreview.classList.remove('hidden');
    } else {
        currentImagePreview.classList.add('hidden');
    }
    
    // Populate original category info
    const originalInfo = document.getElementById('originalCategoryInfo');
    const originalImageHtml = featured.category.image ? 
        `<img src="/storage/${featured.category.image}" alt="${featured.category.name}" class="w-12 h-12 object-cover rounded inline-block mr-2">` : 
        '<span class="text-gray-400 mr-2">Sin imagen</span>';
    
    originalInfo.innerHTML = `
        <div class="flex items-center mb-2">
            ${originalImageHtml}
            <div>
                <div class="font-medium">${featured.category.name}</div>
                <div class="text-sm text-gray-500">${featured.category.slug}</div>
            </div>
        </div>
        <div class="text-sm text-gray-600">${featured.category.description || 'Sin descripción'}</div>
    `;
    
    // Show modal
    document.getElementById('customizationModal').classList.remove('hidden');
}

function closeCustomizationModal() {
    document.getElementById('customizationModal').classList.add('hidden');
    document.getElementById('customizationForm').reset();
    document.getElementById('currentImagePreview').classList.add('hidden');
}

function saveCustomization() {
    const featuredCategoryId = document.getElementById('featuredCategoryId').value;
    const form = document.getElementById('customizationForm');
    const formData = new FormData(form);
    
    console.log('Saving customization for featured category ID:', featuredCategoryId);
    console.log('Form data entries:');
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Show loading state
    const saveBtn = document.querySelector('#customizationModal button[onclick="saveCustomization()"]');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Guardando...';
    saveBtn.disabled = true;
    
    const url = `/featured-categories/${featuredCategoryId}/update-customization`;
    console.log('Request URL:', url);
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Show success message
            alert(data.message);
            // Reload page to see changes
            window.location.reload();
        } else {
            alert('Error al guardar la personalización: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error al guardar la personalización: ' + error.message);
    })
    .finally(() => {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

function removeCustomImage() {
    const featuredCategoryId = document.getElementById('featuredCategoryId').value;
    
    if (!confirm('¿Está seguro de eliminar la imagen personalizada?')) {
        return;
    }
    
    fetch(`/featured-categories/${featuredCategoryId}/remove-custom-image`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide image preview
            document.getElementById('currentImagePreview').classList.add('hidden');
            // Clear file input
            document.getElementById('customImageInput').value = '';
            alert(data.message);
        } else {
            alert('Error al eliminar la imagen');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar la imagen');
    });
}

// Allow updating title with Enter key
document.getElementById('sectionTitle').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        updateSectionTitle();
    }
});
</script>
@endsection 