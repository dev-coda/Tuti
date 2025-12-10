@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('tags.update', $tag)->bind($tag)->put() }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Etiqueta</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('content', 'Contenido de la etiqueta')->groupClass('col-span-6')->helpText('Texto que aparecerá en la etiqueta') }}
                
                {{ Aire::input('priority', 'Prioridad')->type('number')->groupClass('col-span-3')->helpText('Número más bajo = mayor prioridad. Si varias etiquetas aplican, se mostrará la de menor número.') }}

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
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('tags.index') }}">Cancelar</a>
                    </p>
                    <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="inline"
                        onsubmit="return confirm('¿Estás seguro de eliminar esta etiqueta?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                            Eliminar
                        </button>
                    </form>
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
                            <option value="{{ $id }}" {{ $tag->products->contains($id) ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Mantén presionado Ctrl/Cmd para seleccionar múltiples</p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Categorías</label>
                    <select name="category_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" {{ $tag->categories->contains($id) ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Marcas</label>
                    <select name="brand_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($brands as $id => $name)
                            <option value="{{ $id }}" {{ $tag->brands->contains($id) ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">Bonificaciones</label>
                    <select name="bonification_ids[]" multiple class="w-full border border-gray-300 rounded-lg p-2 text-sm" size="5">
                        @foreach($bonifications as $id => $name)
                            <option value="{{ $id }}" {{ $tag->bonifications->contains($id) ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
@endsection

