@extends('layouts.admin')

@section('content')
{{--
  Criteria checkboxes must stay inside the update form. A nested <form> for delete
  closes the outer form in the browser and drops product/category/brand ids on save.
--}}
{{ Aire::open()->route('tags.update', $tag)->bind($tag)->put()->id('tag-update-form') }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Etiqueta</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('content', 'Contenido de la etiqueta')->groupClass('col-span-6')->helpText('Texto que aparecerá en la etiqueta') }}

                {{ Aire::input('priority', 'Prioridad')->type('number')->groupClass('col-span-3')->helpText('Número más bajo = mayor prioridad. La etiqueta manual se muestra junto con hasta una automática.') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('enabled', 'Habilitada')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está habilitada, la etiqueta aparecerá en los productos que cumplan los criterios.
                        </span>
                    </div>
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
                                    {{ $tag->products->contains($product['id']) ? 'checked' : '' }}
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($product['display']) }}">
                                    {{ $product['display'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Selecciona los productos que desees</p>
                </div>

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
                                    {{ $tag->categories->contains($category['id']) ? 'checked' : '' }}
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($category['name']) }}">
                                    {{ $category['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

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
                                    {{ $tag->brands->contains($brand['id']) ? 'checked' : '' }}
                                >
                                <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($brand['name']) }}">
                                    {{ $brand['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

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
                                    {{ $tag->bonifications->contains($bonification['id']) ? 'checked' : '' }}
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

    <div class="col-span-full flex flex-wrap items-center justify-between gap-3 mb-4">
        <p class="flex space-x-2 items-center">
            {{ Aire::submit('Actualizar')->variant()->submit() }}
            <a href="{{ route('tags.index') }}">Cancelar</a>
        </p>
    </div>
</div>
{{ Aire::close() }}

<form action="{{ route('tags.destroy', $tag) }}" method="POST" class="px-4 pb-4"
      onsubmit="return confirm('¿Estás seguro de eliminar esta etiqueta?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
        Eliminar
    </button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    setupFilter('product-filter', 'product-item');
    setupFilter('category-filter', 'category-item');
    setupFilter('brand-filter', 'brand-item');
    setupFilter('bonification-filter', 'bonification-item');
});
</script>
@endsection
