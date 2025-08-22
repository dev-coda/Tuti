@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('categories.update', $category)->bind($category)->enctype('multipart/form-data')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar categoría</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">

                {{ Aire::input('name', "Nombre")->groupClass('col-span-6 sm:col-span-3') }}
                
                {{ Aire::input('slug', "Slug")->groupClass('col-span-6 sm:col-span-3') }}

                <div class="col-span-6">
                    <h3 class="text-lg font-semibold">Tipo Categoría</h3>
                    <div class="flex items-center space-x-4 mt-2">
                        <label class="inline-flex items-center">
                            <input type="radio" name="category_type" value="parent" 
                                class="form-radio text-blue-600" 
                                @checked($category->category_type == 'parent')
                                id="category_type_parent">
                            <span class="ml-2">Padre</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="category_type" value="child" 
                                class="form-radio text-blue-600" 
                                @checked($category->category_type == 'child')
                                id="category_type_child">
                            <span class="ml-2">Hijo</span>
                        </label>
                    </div>
                </div>

                {{ Aire::select($categories, 'parent_id', 'Padre')->groupClass('col-span-6 sm:col-span-3') }}

                {{ Aire::input('safety_stock', 'Stock de seguridad por categoría')->type('number')->min(0)->helpText('Nivel mínimo de inventario permitido para productos de esta categoría')->groupClass('col-span-6 sm:col-span-3') }}

                {{ Aire::checkbox('inventory_opt_out', 'Excluir de gestión de inventario')->checked((bool)($category->inventory_opt_out ?? (mb_strtoupper($category->name) === 'OFERTAS')))->groupClass('col-span-6') }}

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('categories.index') }}">Cancelar</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-full xl:col-auto">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold">Configuración de Ordenamiento</h3>
            
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::select($sortOrders, 'default_sort_order', 'Orden por defecto')->groupClass('col-span-6') }}
            </div>
        </div>

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold">Configuración de Productos Destacados</h3>
            
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::checkbox('enable_highlighting', 'Habilitar productos destacados')->groupClass('col-span-6') }}
                
                <div class="col-span-6" id="highlighting-options" style="{{ $category->enable_highlighting ? '' : 'display: none;' }}">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Marcas destacadas</label>
                    <select name="highlighted_brand_ids[]" multiple class="w-full p-2 border border-gray-300 rounded-md" size="5">
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" 
                                {{ in_array($brand->id, $category->highlighted_brand_ids ?? []) ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Selecciona las marcas cuyos productos aparecerán destacados</p>
                </div>

                <div class="col-span-6" id="highlight-products-link" style="{{ $category->enable_highlighting ? '' : 'display: none;' }}">
                    <a href="{{ route('categories.highlights.index', $category) }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Gestionar Productos Destacados Específicos
                    </a>
                    <p class="text-sm text-gray-500 mt-1">Configura hasta 4 productos específicos para mostrar en las primeras posiciones</p>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}


<x-remove-drawer title="Categoría" route='categories.destroy' :item='$category' />


@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableHighlighting = document.querySelector('input[name="enable_highlighting"]');
    const highlightingOptions = document.getElementById('highlighting-options');
    const highlightProductsLink = document.getElementById('highlight-products-link');
    
    if (enableHighlighting) {
        enableHighlighting.addEventListener('change', function() {
            if (this.checked) {
                highlightingOptions.style.display = '';
                highlightProductsLink.style.display = '';
            } else {
                highlightingOptions.style.display = 'none';
                highlightProductsLink.style.display = 'none';
            }
        });
    }
});
</script>
@endsection