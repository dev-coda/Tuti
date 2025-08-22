@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('categories.index') }}" class="text-gray-700 hover:text-blue-600">
                            Categorías
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('categories.edit', $category) }}" class="ml-1 text-gray-700 hover:text-blue-600 md:ml-2">{{ $category->name }}</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">Productos Destacados</span>
                        </div>
                    </li>
                </ol>
            </nav>
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl mt-2">
                Productos Destacados - {{ $category->name }}
            </h1>
            <p class="text-gray-600 text-sm mt-1">
                Configura hasta 4 productos específicos para mostrar en las primeras posiciones de la categoría
            </p>
        </div>
    </div>
</div>

<div class="p-4">
    <!-- Current Highlights -->
    @if($highlights->count() > 0)
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Productos Destacados Actuales</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="highlights-grid">
                @foreach($highlights as $highlight)
                <div class="border rounded-lg p-4 bg-gray-50" data-highlight-id="{{ $highlight->id }}">
                    <div class="flex justify-between items-start mb-2">
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                            Posición {{ $highlight->position }}
                        </span>
                        <form action="{{ route('categories.highlights.destroy', [$category, $highlight]) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('¿Eliminar este producto destacado?')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                    <h4 class="font-medium text-gray-900 mb-1">{{ $highlight->product->name }}</h4>
                    <p class="text-sm text-gray-600 mb-2">SKU: {{ $highlight->product->sku }}</p>
                    <p class="text-sm font-medium text-gray-900">${{ number_format($highlight->product->price, 2) }}</p>
                    
                    <div class="mt-3">
                        <form action="{{ route('categories.highlights.update', [$category, $highlight]) }}" method="POST" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <select name="position" class="w-full text-xs p-1 border border-gray-300 rounded">
                                @for($i = 1; $i <= 4; $i++)
                                    <option value="{{ $i }}" {{ $highlight->position == $i ? 'selected' : '' }}>
                                        Posición {{ $i }}
                                    </option>
                                @endfor
                            </select>
                            <button type="submit" class="w-full bg-blue-600 text-white text-xs py-1 px-2 rounded hover:bg-blue-700">
                                Actualizar
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Add New Highlight -->
    @if(count($availablePositions) > 0)
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Agregar Producto Destacado</h3>
        </div>
        <div class="p-6">
            <form action="{{ route('categories.highlights.store', $category) }}" method="POST" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Producto</label>
                        <select name="product_id" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleccionar producto</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->sku }}</option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Posición</label>
                        <select name="position" required class="w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Seleccionar posición</option>
                            @foreach($availablePositions as $position)
                                <option value="{{ $position }}">Posición {{ $position }}</option>
                            @endforeach
                        </select>
                        @error('position')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label class="ml-2 block text-sm text-gray-900">Activo</label>
                </div>

                <div class="flex justify-end space-x-3">
                    <a href="{{ route('categories.edit', $category) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Volver
                    </a>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Agregar Producto Destacado
                    </button>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">Límite alcanzado</h3>
                <p class="text-sm text-yellow-700 mt-1">
                    Ya tienes 4 productos destacados configurados. Elimina alguno para agregar otro.
                </p>
            </div>
        </div>
        <div class="mt-4">
            <a href="{{ route('categories.edit', $category) }}" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 font-bold py-2 px-4 rounded">
                Volver a la Categoría
            </a>
        </div>
    </div>
    @endif
</div>

@section('scripts')
<script>
// Add any JavaScript functionality for drag-and-drop reordering if needed
document.addEventListener('DOMContentLoaded', function() {
    // Product search functionality could be added here
    console.log('Product highlights management loaded');
});
</script>
@endsection

@endsection
