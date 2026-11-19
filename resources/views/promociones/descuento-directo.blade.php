@extends('layouts.admin')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">Descuento Directo</h1>
                        <p class="text-gray-600">Configura descuentos por marca, proveedor o referencias (SKU)</p>
                    </div>

                    @if (session('success'))
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-8 border border-gray-200 rounded-lg p-4">
                        <h2 class="text-lg font-semibold text-gray-900 mb-1">Aplicar a referencias</h2>
                        <p class="text-sm text-gray-600 mb-4">Busca por SKU o nombre y aplica el descuento solo a esas referencias. La etiqueta del descuento aparece en la ficha cuando la etiqueta automática DESCUENTO está activa.</p>

                        <form action="{{ route('promociones.descuento-directo.store') }}" method="POST" class="space-y-4" id="direct-discount-form">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="discount_type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                    <select name="discount_type" id="discount_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <option value="percentage" @selected(old('discount_type', 'percentage') === 'percentage')>Porcentaje (%)</option>
                                        <option value="fixed_amount" @selected(old('discount_type') === 'fixed_amount')>Valor fijo ($)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="discount" class="block text-sm font-medium text-gray-700">Valor</label>
                                    <input type="number" name="discount" id="discount" value="{{ old('discount') }}" min="0" step="0.01" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <div class="flex items-end">
                                    <label class="inline-flex items-center mb-2">
                                        <input type="checkbox" name="first_purchase_only" value="1" @checked(old('first_purchase_only')) class="rounded border-gray-300 text-blue-600">
                                        <span class="ml-2 text-sm text-gray-700">Solo primera compra</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="reference-search" class="block text-sm font-medium text-gray-700">Referencias</label>
                                <input type="text" id="reference-search" placeholder="Buscar por referencia (SKU) o nombre..."
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <div id="selected-references" class="flex flex-wrap gap-2 mt-3"></div>
                                <div id="reference-results" class="mt-2 max-h-60 overflow-y-auto border border-gray-200 rounded-md">
                                    <div class="p-3 text-sm text-gray-500">Escribe para buscar referencias</div>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Aplicar descuento
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Brands Section -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Descuentos por Marca</h2>
                            <a href="{{ route('brands.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                                Nueva Marca
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marca</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solo Primera Compra</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($brands as $brand)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @if($brand->image)
                                                        <img class="h-10 w-10 rounded-full object-cover" src="{{ asset('storage/' . $brand->image) }}" alt="{{ $brand->name }}">
                                                    @else
                                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                            @svg('heroicon-o-building-storefront', 'w-5 h-5 text-gray-400')
                                                        </div>
                                                    @endif
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">{{ $brand->name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $brand->vendor->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($brand->discount > 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        {{ $brand->discount }}{{ $brand->discount_type === 'percentage' ? '%' : '$' }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">Sin descuento</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="text-gray-500">{{ $brand->discount_type === 'percentage' ? 'Porcentaje' : 'Valor Fijo' }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($brand->first_purchase_only)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Sí
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('brands.edit', $brand) }}" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Editar
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                No hay marcas configuradas
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Vendors Section -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Descuentos por Proveedor</h2>
                            <a href="{{ route('vendors.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                                Nuevo Proveedor
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solo Primera Compra</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($vendors as $vendor)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @if($vendor->image)
                                                        <img class="h-10 w-10 rounded-full object-cover" src="{{ asset('storage/' . $vendor->image) }}" alt="{{ $vendor->name }}">
                                                    @else
                                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                            @svg('heroicon-o-building-office', 'w-5 h-5 text-gray-400')
                                                        </div>
                                                    @endif
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">{{ $vendor->name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($vendor->discount > 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        {{ $vendor->discount }}{{ $vendor->discount_type === 'percentage' ? '%' : '$' }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">Sin descuento</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="text-gray-500">{{ $vendor->discount_type === 'percentage' ? 'Porcentaje' : 'Valor Fijo' }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($vendor->first_purchase_only)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Sí
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">No</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('vendors.edit', $vendor) }}" 
                                                   class="text-blue-600 hover:text-blue-900">
                                                    Editar
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                                No hay proveedores configurados
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Discount Types Information -->
                    <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check-circle', 'w-5 h-5 text-green-400')
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Tipos de Descuento Soportados</h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <p>Los descuentos directos soportan tanto <strong>porcentajes (%)</strong> como <strong>valores fijos ($)</strong>. Los descuentos en valor fijo se distribuyen proporcionalmente entre los productos del carrito.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('reference-search');
    const results = document.getElementById('reference-results');
    const selectedBox = document.getElementById('selected-references');
    const form = document.getElementById('direct-discount-form');
    const searchUrl = @json(route('promociones.elements'));
    const selected = new Map();
    let timer = null;

    function renderSelected() {
        selectedBox.innerHTML = '';
        form.querySelectorAll('input[name="product_ids[]"]').forEach(function (input) {
            input.remove();
        });

        if (selected.size === 0) {
            return;
        }

        selected.forEach(function (item, id) {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
            chip.textContent = item.name;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'ml-1 text-blue-600';
            remove.textContent = '×';
            remove.addEventListener('click', function () {
                selected.delete(id);
                renderSelected();
                load(searchInput.value.trim());
            });
            chip.appendChild(remove);
            selectedBox.appendChild(chip);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = id;
            form.appendChild(input);
        });
    }

    function renderResults(items) {
        results.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            results.innerHTML = '<div class="p-3 text-sm text-gray-500">No se encontraron referencias</div>';
            return;
        }

        items.forEach(function (item) {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'w-full text-left p-3 border-b border-gray-200 hover:bg-gray-50';
            const title = document.createElement('div');
            title.className = 'text-sm font-medium text-gray-900';
            title.textContent = item.name || '';
            const subtitle = document.createElement('div');
            subtitle.className = 'text-xs text-gray-500';
            subtitle.textContent = item.description || '';
            row.appendChild(title);
            row.appendChild(subtitle);
            if (selected.has(String(item.id))) {
                row.classList.add('bg-blue-50');
            }
            row.addEventListener('click', function () {
                const id = String(item.id);
                if (selected.has(id)) {
                    selected.delete(id);
                } else {
                    selected.set(id, { name: item.name || item.description || id });
                }
                renderSelected();
                renderResults(items);
            });
            results.appendChild(row);
        });
    }

    function load(search) {
        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('type', 'products');
        if (search) {
            url.searchParams.set('search', search);
        }

        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Error al cargar referencias');
                }
                return response.json();
            })
            .then(renderResults)
            .catch(function () {
                results.innerHTML = '<div class="p-3 text-sm text-red-600">Error al cargar referencias</div>';
            });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        const value = this.value.trim();
        timer = setTimeout(function () {
            load(value);
        }, 300);
    });

    load('');
});
</script>
@endsection
