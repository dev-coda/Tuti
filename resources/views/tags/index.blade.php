@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Etiquetas</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex items-center mb-4 sm:mb-0">
                <x-search :home="route('tags.index')" />
            </div>
            <a href="{{ route('tags.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                Nueva Etiqueta
            </a>
        </div>
    </div>
</div>

<!-- Auto Tags Toggles -->
<div class="p-4 bg-white border-b border-gray-200">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Etiquetas Automáticas</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- NUEVO Auto Tag Toggle -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-900 mb-1">Etiqueta NUEVO</h3>
                <p class="text-xs text-gray-500">Muestra "NUEVO" en productos creados en los últimos 30 días</p>
            </div>
            <form action="{{ route('tags.auto-tag-nuevo.toggle') }}" method="POST" class="ml-4">
                @csrf
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" 
                           name="enabled" 
                           value="1" 
                           {{ $autoTagNuevoEnabled ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </form>
        </div>

        <!-- DESCUENTO Auto Tag Toggle -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-900 mb-1">Etiqueta DESCUENTO</h3>
                <p class="text-xs text-gray-500">Muestra el descuento en productos con descuentos estáticos (producto, marca, vendedor)</p>
            </div>
            <form action="{{ route('tags.auto-tag-descuento.toggle') }}" method="POST" class="ml-4">
                @csrf
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" 
                           name="enabled" 
                           value="1" 
                           {{ $autoTagDescuentoEnabled ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </form>
        </div>
    </div>
</div>

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Contenido
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Prioridad
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Estado
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Criterios
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-center text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tags as $tag)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900 hover:text-blue-500" href="{{ route('tags.edit', $tag) }}">
                                    <span class="text-base font-semibold">
                                        {{ $tag->content }}
                                    </span>
                                </a>
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                {{ $tag->priority }}
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                @if($tag->enabled)
                                    <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">
                                        Habilitada
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 rounded-full">
                                        Deshabilitada
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-sm font-normal text-gray-500">
                                <div class="flex flex-wrap gap-2">
                                    @if($tag->products->count() > 0)
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">Productos ({{ $tag->products->count() }})</span>
                                    @endif
                                    @if($tag->categories->count() > 0)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Categorías ({{ $tag->categories->count() }})</span>
                                    @endif
                                    @if($tag->brands->count() > 0)
                                        <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Marcas ({{ $tag->brands->count() }})</span>
                                    @endif
                                    @if($tag->bonifications->count() > 0)
                                        <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded">Bonificaciones ({{ $tag->bonifications->count() }})</span>
                                    @endif
                                    @if($tag->products->count() == 0 && $tag->categories->count() == 0 && $tag->brands->count() == 0 && $tag->bonifications->count() == 0)
                                        <span class="text-xs text-gray-400">Sin criterios</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 text-sm font-medium text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-2">
                                    <form action="{{ route('tags.toggle', $tag) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                            class="px-3 py-1 text-xs font-medium text-center text-white rounded-lg {{ $tag->enabled ? 'bg-gray-600 hover:bg-gray-700' : 'bg-green-600 hover:bg-green-700' }} focus:ring-4 focus:ring-gray-300">
                                            {{ $tag->enabled ? 'Deshabilitar' : 'Habilitar' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('tags.edit', $tag) }}" 
                                        class="px-3 py-1 text-xs font-medium text-center text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                                        Editar
                                    </a>
                                    <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="inline" 
                                        onsubmit="return confirm('¿Estás seguro de eliminar esta etiqueta?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                            class="px-3 py-1 text-xs font-medium text-center text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-4 text-sm text-center text-gray-500">
                                No hay etiquetas creadas. <a href="{{ route('tags.create') }}" class="text-blue-600 hover:underline">Crear una nueva</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="p-4">
        {{ $tags->links() }}
    </div>
</div>

@endsection

