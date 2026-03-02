@extends('layouts.admin')

@section('title', 'Configuración de Ventas')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Configuración de Ventas</h1>
                <p class="text-sm text-gray-500">Configura las categorías que se muestran en el mini dashboard del vendedor</p>
            </div>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Volver a Configuraciones
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="col-span-full mb-4">
            <div class="flex items-center p-4 text-sm text-green-800 border border-green-300 rounded-lg bg-green-50">
                <svg class="flex-shrink-0 w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="col-span-full">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Categorías del Dashboard de Vendedores</h3>
            <p class="text-sm text-gray-500 mb-6">Selecciona hasta <strong>5 categorías</strong> que se mostrarán como tarjetas de ventas en el mini dashboard de la página "Mi Cuenta" para vendedores.</p>

            <form action="{{ route('settings.ventas.update') }}" method="POST">
                @csrf

                <div class="space-y-3 mb-6">
                    @foreach($categories as $category)
                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition
                            {{ in_array($category->id, $selectedIds) ? 'border-orange-300 bg-orange-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                            <input
                                type="checkbox"
                                name="category_ids[]"
                                value="{{ $category->id }}"
                                class="category-checkbox h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                @checked(in_array($category->id, $selectedIds))
                            />
                            <span class="text-sm font-medium text-gray-800">{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>

                <p id="limit-warning" class="hidden text-sm text-red-600 mb-4">Solo puedes seleccionar hasta 5 categorías.</p>

                @error('category_ids')
                    <p class="text-sm text-red-600 mb-4">{{ $message }}</p>
                @enderror

                <button type="submit" class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-lg shadow-sm hover:bg-orange-700 focus:ring-4 focus:ring-orange-300">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Guardar Categorías
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function(){
    const MAX = 5;
    const boxes   = document.querySelectorAll('.category-checkbox');
    const warning = document.getElementById('limit-warning');

    function enforce() {
        const checked = document.querySelectorAll('.category-checkbox:checked').length;
        boxes.forEach(function(cb) {
            if (!cb.checked) cb.disabled = checked >= MAX;
        });
        warning.classList.toggle('hidden', checked < MAX);

        // Update visual styling
        boxes.forEach(function(cb) {
            const label = cb.closest('label');
            if (cb.checked) {
                label.classList.add('border-orange-300', 'bg-orange-50');
                label.classList.remove('border-gray-200');
            } else {
                label.classList.remove('border-orange-300', 'bg-orange-50');
                label.classList.add('border-gray-200');
            }
        });
    }

    boxes.forEach(function(cb){ cb.addEventListener('change', enforce); });
    enforce();
})();
</script>
@endsection
