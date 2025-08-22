@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('categories.store')->enctype('multipart/form-data')}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear categoría</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">

                {{ Aire::input('name', "Nombre")->groupClass('col-span-6') }}
                {{ Aire::textarea('description', "Descripción")->rows(5)->groupClass('col-span-6') }}

                {{ Aire::select($categories, 'parent_id', 'Padre')->groupClass('col-span-6') }}

                
                <div>
                    {{ Aire::hidden('active')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked name='active' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900 ">Activo</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Sorting Configuration -->
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Configuración de Ordenamiento</h3>
            
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::select($sortOrders, 'default_sort_order', 'Orden por defecto')->value('most_recent')->groupClass('col-span-6') }}
            </div>
        </div>

        <!-- Product Highlighting Configuration -->
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Configuración de Productos Destacados</h3>
            
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::checkbox('enable_highlighting', 'Habilitar productos destacados')->groupClass('col-span-6') }}
                
                <div class="col-span-6" id="highlighting-options" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Marcas destacadas</label>
                    <select name="highlighted_brand_ids[]" multiple class="w-full p-2 border border-gray-300 rounded-md" size="5">
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Selecciona las marcas cuyos productos aparecerán destacados</p>
                </div>

                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Crear')->variant()->submit() }}
                        <a href="{{ route('categories.index') }}">Cancelar</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-full xl:col-auto">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold ">Imagenes</h3>
            {{ Aire::file('image_file', 'Imagen') }}
        </div>
    </div>

   
</div>
{{ Aire::close() }}

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const enableHighlighting = document.querySelector('input[name="enable_highlighting"]');
    const highlightingOptions = document.getElementById('highlighting-options');
    
    if (enableHighlighting) {
        enableHighlighting.addEventListener('change', function() {
            if (this.checked) {
                highlightingOptions.style.display = '';
            } else {
                highlightingOptions.style.display = 'none';
            }
        });
    }
});
</script>
@endsection
