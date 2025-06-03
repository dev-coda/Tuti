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

                {{ Aire::input('name', "Nombre")->groupClass('col-span-6') }}
                {{ Aire::input('slug', "Slug")->groupClass('col-span-6') }}

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

                {{ Aire::select($categories, 'parent_id', 'Padre')->groupClass('col-span-6') }}
                {{ Aire::textarea('description', "Descripción")->rows(5)->groupClass('col-span-6') }}

                <div>
                    {{ Aire::hidden('active')->value(0)}}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input @checked($category->active) type="checkbox" name='active' value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300  rounded-full peer  peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all0 peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900 ">Activo</span>
                    </label>

                </div>
                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('categories.index') }}">Cancelar</a>
                    </p>


                    
                    <x-remove-button />  
                   
                </div>
            </div>

        </div>
    </div>

    <div class="col-span-full xl:col-auto">
        <div
            class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Imagenes</h3>
      
            <div class="mb-3 text-center">
                <img src="{{ asset('storage/'.$category->image) }}" alt="" class="w-48 inline-block">
            </div>
            {{ Aire::file('image_file', 'Imagen') }}

          
        </div>
    </div>

   
</div>
{{ Aire::close() }}


<x-remove-drawer title="Categoría" route='categories.destroy' :item='$category' />


@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const parentRadio = document.getElementById("category_type_parent");
        const childRadio = document.getElementById("category_type_child");
        const parentSelect = document.querySelector("select[name='parent_id']");

        if (parentRadio && childRadio && parentSelect) {
            
            const parentSelectContainer = parentSelect.closest('.col-span-6');

            function toggleParentSelect() {
                if (parentRadio.checked) {
                    parentSelectContainer.classList.add('hidden');  
                    parentSelect.value = ""; 
                } else {
                    parentSelectContainer.classList.remove('hidden'); 
                }
            }

            
            parentRadio.addEventListener("change", toggleParentSelect);
            childRadio.addEventListener("change", toggleParentSelect);

          
            toggleParentSelect();
        }
    });
</script>
@endsection
