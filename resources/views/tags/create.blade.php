@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('tags.store') }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear Etiqueta</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('content', 'Contenido de la etiqueta')->groupClass('col-span-6')->helpText('Texto que aparecerá en la etiqueta') }}
                
                {{ Aire::input('priority', 'Prioridad')->type('number')->value(999)->groupClass('col-span-3')->helpText('Número más bajo = mayor prioridad. Si varias etiquetas aplican, se mostrará la de menor número.') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('enabled', 'Habilitada')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está habilitada, la etiqueta aparecerá en los productos que cumplan los criterios.
                        </span>
                    </div>
                </div>

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                    </p>
                    <a href="{{ route('tags.index') }}">Cancelar</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-1">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Criterios</h3>
            <p class="mb-4 text-sm text-gray-600">
                Selecciona los productos, categorías, marcas o bonificaciones a los que se aplicará esta etiqueta.
            </p>

            <div class="space-y-6">
                <!-- Products -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Productos específicos</label>
                    <input 
                        type="text" 
                        id="product-filter"
                        placeholder="Buscar por SKU o nombre..."
                        class="w-full mb-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                        @foreach($products as $product)
                            <label class="flex items-center py-1.5 px-2 hover:bg-white rounded cursor-pointer product-item">
                                <input 
                                    type="checkbox" 
                                    name="product_ids[]" 
                                    value="{{ $product['id'] }}"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($product['display']) }}">
                                    {{ $product['display'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Selecciona los productos que desees</p>
                </div>

                <!-- Categories -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Categorías</label>
                    <input 
                        type="text" 
                        id="category-filter"
                        placeholder="Buscar categorías..."
                        class="w-full mb-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                        @foreach($categories as $category)
                            <label class="flex items-center py-1.5 px-2 hover:bg-white rounded cursor-pointer category-item">
                                <input 
                                    type="checkbox" 
                                    name="category_ids[]" 
                                    value="{{ $category['id'] }}"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($category['name']) }}">
                                    {{ $category['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Brands -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Marcas</label>
                    <input 
                        type="text" 
                        id="brand-filter"
                        placeholder="Buscar marcas..."
                        class="w-full mb-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                        @foreach($brands as $brand)
                            <label class="flex items-center py-1.5 px-2 hover:bg-white rounded cursor-pointer brand-item">
                                <input 
                                    type="checkbox" 
                                    name="brand_ids[]" 
                                    value="{{ $brand['id'] }}"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($brand['name']) }}">
                                    {{ $brand['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Bonifications -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Bonificaciones</label>
                    <input 
                        type="text" 
                        id="bonification-filter"
                        placeholder="Buscar bonificaciones..."
                        class="w-full mb-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                    >
                    <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                        @foreach($bonifications as $bonification)
                            <label class="flex items-center py-1.5 px-2 hover:bg-white rounded cursor-pointer bonification-item">
                                <input 
                                    type="checkbox" 
                                    name="bonification_ids[]" 
                                    value="{{ $bonification['id'] }}"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($bonification['name']) }}">
                                    {{ $bonification['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generic filter function
    function setupFilter(inputId, itemClass) {
        const input = document.getElementById(inputId);
        if (!input) return;

        input.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('.' + itemClass);
            
            items.forEach(function(item) {
                const searchText = item.querySelector('[data-search]').getAttribute('data-search');
                if (searchText.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Setup filters for each criteria
    setupFilter('product-filter', 'product-item');
    setupFilter('category-filter', 'category-item');
    setupFilter('brand-filter', 'brand-item');
    setupFilter('bonification-filter', 'bonification-item');
});
</script>
@endsection

