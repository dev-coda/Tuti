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

                {{ Aire::input('safety_stock', 'Stock de seguridad por categoría')->type('number')->min(0)->helpText('Nivel mínimo de inventario permitido para productos de esta categoría')->groupClass('col-span-6') }}
            </div>
        </div>
    </div>

    <div class="col-span-1">
        {{-- Additional fields or sidebar content can go here --}}
    </div>
</div>
{{ Aire::close() }}
@endsection
