@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Productos Destacados</h1>
        </div>
        <div class="items-center flex space-x-5">
            <!-- Toggle Switch -->
            <div class="flex items-center space-x-3">
                <label class="inline-flex items-center cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="useMostSoldToggle"
                        @if($useMostSold) checked @endif
                        class="sr-only peer"
                        onchange="toggleMostSold(this.checked)"
                    >
                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-900">Usar más vendidos</span>
                </label>
            </div>

            <button 
                id="addProductBtn"
                @if($featuredProducts->count() >= 12 || $useMostSold) disabled @endif
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed">
                Agregar producto
            </button>
            <span class="text-sm text-gray-600">{{ $featuredProducts->count() }} / 12 productos</span>
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
                    placeholder="Ej: Productos Destacados, Productos Recomendados, etc."
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
            Este título aparecerá en la sección de productos destacados en la página principal del sitio web.
        </p>
    </div>
</div>

@if($useMostSold)
<div class="p-4 bg-yellow-50 border-l-4 border-yellow-400">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                Actualmente se están mostrando los productos más vendidos automáticamente. Los productos destacados seleccionados manualmente no se están utilizando.
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
                                Producto
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                SKU
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Marca
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Precio
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($featuredProducts as $featured)
                        <tr class="hover:bg-gray-100 @if($useMostSold) opacity-50 @endif">
                            <td class="flex items-center p-4 mr-12 space-x-4 max-w-sm lg:mr-0">
                                @if($featured->product->images->first())
                                    <img class="w-10 h-10 rounded-lg object-cover" src="{{ asset('storage/'.$featured->product->images->first()->path) }}" alt="{{ $featured->product->name }}">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="font-medium text-gray-900 truncate">
                                    {{ $featured->product->name }}
                                </div>
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900">
                                {{ $featured->product->sku }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900">
                                {{ $featured->product->brand ? $featured->product->brand->name : '-' }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900">
                                ${{ number_format($featured->product->price, 2) }}
                            </td>
                            <td class="p-4 space-x-2">
                                {{ Aire::open()->route('featured-products.destroy', $featured) }}
                                    <button 
                                        type="submit" 
                                        @if($useMostSold) disabled @endif
                                        onclick="return confirm('¿Está seguro de eliminar este producto de destacados?')" 
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-red-600 rounded-lg hover:bg-red-800 focus:ring-4 focus:ring-red-300 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Eliminar
                                    </button>
                                {{ Aire::close() }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-gray-500">
                                @if($useMostSold)
                                    Los productos más vendidos se están mostrando automáticamente
                                @else
                                    No hay productos destacados
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
<div id="productModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeModal()"></div>

        <!-- Modal panel -->
        <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                    Buscar producto
                </h3>
                
                <input 
                    type="text" 
                    id="searchInput"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por nombre o SKU..."
                >
                
                <div id="searchResults" class="mt-4 max-h-60 overflow-y-auto">
                    <!-- Search results will appear here -->
                </div>
                
                <div id="selectedProduct" class="mt-4 p-3 bg-gray-100 rounded-md hidden">
                    <!-- Selected product will appear here -->
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

@endsection

@section('scripts')
<script>
let selectedProductId = null;
let searchTimeout = null;

// Toggle most sold products
function toggleMostSold(checked) {
    console.log('Toggle clicked, checked:', checked);
    
    fetch('{{ route('featured-products.toggle-most-sold') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            use_most_sold: checked
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
            document.getElementById('useMostSoldToggle').checked = !checked;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error al actualizar la configuración');
        // Reset toggle to previous state
        document.getElementById('useMostSoldToggle').checked = !checked;
    });
}

// Open modal
document.getElementById('addProductBtn').addEventListener('click', function() {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('searchInput').focus();
});

// Close modal
function closeModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('selectedProduct').innerHTML = '';
    document.getElementById('selectedProduct').classList.add('hidden');
    document.getElementById('saveBtn').disabled = true;
    selectedProductId = null;
}

// Search products
document.getElementById('searchInput').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value;
    
    if (query.length < 2) {
        document.getElementById('searchResults').innerHTML = '';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`{{ route('featured-products.search') }}?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(products => {
                let html = '';
                if (products.length === 0) {
                    html = '<p class="text-gray-500 text-center py-4">No se encontraron productos</p>';
                } else {
                    products.forEach(product => {
                        const imageUrl = product.images && product.images.length > 0 
                            ? `/storage/${product.images[0].path}` 
                            : '';
                        html += `
                            <div class="p-2 hover:bg-gray-100 cursor-pointer rounded" onclick="selectProduct(${product.id}, '${product.name}', '${product.sku || ''}', '${imageUrl}')">
                                <div class="flex items-center space-x-3">
                                    ${imageUrl ? 
                                        `<img src="${imageUrl}" class="w-10 h-10 rounded object-cover">` :
                                        `<div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                            </svg>
                                        </div>`
                                    }
                                    <div>
                                        <p class="font-medium">${product.name}</p>
                                        <p class="text-sm text-gray-500">${product.sku || 'Sin SKU'}</p>
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

// Select product
function selectProduct(id, name, sku, imageUrl) {
    selectedProductId = id;
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('searchInput').value = '';
    
    let html = `
        <div class="flex items-center space-x-3">
            ${imageUrl ? 
                `<img src="${imageUrl}" class="w-12 h-12 rounded object-cover">` :
                `<div class="w-12 h-12 rounded bg-gray-200 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </div>`
            }
            <div>
                <p class="font-medium">${name}</p>
                <p class="text-sm text-gray-500">${sku || 'Sin SKU'}</p>
            </div>
        </div>
    `;
    
    document.getElementById('selectedProduct').innerHTML = html;
    document.getElementById('selectedProduct').classList.remove('hidden');
    document.getElementById('saveBtn').disabled = false;
}

// Save product
document.getElementById('saveBtn').addEventListener('click', function() {
    if (!selectedProductId) return;
    
    fetch('{{ route('featured-products.store') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            product_id: selectedProductId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error al agregar el producto');
        }
    })
    .catch(error => {
        alert('Error al agregar el producto');
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
    
    fetch('{{ route('featured-products.update-title') }}', {
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

// Allow updating title with Enter key
document.getElementById('sectionTitle').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        updateSectionTitle();
    }
});
</script>
@endsection 