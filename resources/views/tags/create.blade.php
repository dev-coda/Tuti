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

            <div class="space-y-4">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Productos específicos</label>
                    <select name="product_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($products as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Mantén presionado Ctrl/Cmd para seleccionar múltiples</p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Categorías</label>
                    <select name="category_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Marcas</label>
                    <select name="brand_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($brands as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Bonificaciones</label>
                    <select name="bonification_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($bonifications as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection

