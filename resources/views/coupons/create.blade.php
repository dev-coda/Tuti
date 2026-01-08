@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Crear Cupón</h1>
        </div>
    </div>
</div>

<div class="p-4">
    <form action="{{ route('coupons.store') }}" method="POST" class="space-y-6">
        @csrf
        
        <!-- Basic Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Código del cupón *</label>
                <input type="text" name="code" value="{{ old('code') }}" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Nombre *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción</label>
            <textarea name="description" rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
            @error('description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Discount Configuration -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo de descuento *</label>
                <select name="type" required onchange="updateValueLabel(this.value)"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Seleccionar tipo</option>
                    <option value="fixed_amount" {{ old('type') === 'fixed_amount' ? 'selected' : '' }}>Monto fijo</option>
                    <option value="percentage" {{ old('type') === 'percentage' ? 'selected' : '' }}>Porcentaje</option>
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" id="value-label">Valor *</label>
                <input type="number" name="value" value="{{ old('value') }}" step="0.01" min="0" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('value')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Validity Period -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Válido desde *</label>
                <input type="datetime-local" name="valid_from" value="{{ old('valid_from') }}" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('valid_from')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Válido hasta *</label>
                <input type="datetime-local" name="valid_to" value="{{ old('valid_to') }}" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('valid_to')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Application Rules -->
        <div>
            <label class="block text-sm font-medium text-gray-700">Se aplica a *</label>
            <select name="applies_to" required onchange="updateAppliesTo(this.value)"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar aplicación</option>
                <option value="cart" {{ old('applies_to') === 'cart' ? 'selected' : '' }}>Todo el carrito</option>
                <option value="product" {{ old('applies_to') === 'product' ? 'selected' : '' }}>Productos específicos</option>
                <option value="category" {{ old('applies_to') === 'category' ? 'selected' : '' }}>Categorías específicas</option>
                <option value="brand" {{ old('applies_to') === 'brand' ? 'selected' : '' }}>Marcas específicas</option>
                <option value="vendor" {{ old('applies_to') === 'vendor' ? 'selected' : '' }}>Proveedores específicos</option>
                <option value="customer" {{ old('applies_to') === 'customer' ? 'selected' : '' }}>Clientes específicos</option>
                <option value="customer_type" {{ old('applies_to') === 'customer_type' ? 'selected' : '' }}>Tipos de cliente</option>
            </select>
            @error('applies_to')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Dynamic selection based on applies_to -->
        <div id="applies_to_selection" style="display: none;">
            <label class="block text-sm font-medium text-gray-700" id="selection-label">Seleccionar elementos</label>
            <select multiple name="applies_to_ids[]" id="applies_to_ids"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </select>
            @error('applies_to_ids')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Usage Limits -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Límite por cliente</label>
                <input type="number" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer') }}" min="1"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('usage_limit_per_customer')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Límite total</label>
                <input type="number" name="total_usage_limit" value="{{ old('total_usage_limit') }}" min="1"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('total_usage_limit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Monto mínimo</label>
                <input type="number" name="minimum_amount" value="{{ old('minimum_amount') }}" step="0.01" min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('minimum_amount')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Active Status -->
        <div>
            <label class="flex items-center">
                <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                <span class="ml-2 text-sm text-gray-700">Cupón activo</span>
            </label>
        </div>

        <!-- Submit Buttons -->
        <div class="flex justify-end space-x-3">
            <a href="{{ route('coupons.index') }}"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Cancelar
            </a>
            <button type="submit"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Crear Cupón
            </button>
        </div>
    </form>
</div>

<script>
    // Data for dynamic selections
    const selectionData = {
        product: @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name])),
        category: @json($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])),
        brand: @json($brands->map(fn($b) => ['id' => $b->id, 'name' => $b->name])),
        vendor: @json($vendors->map(fn($v) => ['id' => $v->id, 'name' => $v->name])),
        customer: @json($customers->map(fn($u) => ['id' => $u->id, 'name' => ($u->name ?? 'Sin nombre') . ' - ' . ($u->document ?? 'Sin doc') . ' (' . ($u->email ?? 'Sin email') . ')'])),
        customer_type: @json($roles->map(fn($r) => ['id' => $r->name, 'name' => $r->name]))
    };

    function updateValueLabel(type) {
        const label = document.getElementById('value-label');
        if (type === 'fixed_amount') {
            label.textContent = 'Valor (monto en $) *';
        } else if (type === 'percentage') {
            label.textContent = 'Valor (porcentaje %) *';
        } else {
            label.textContent = 'Valor *';
        }
    }

    function updateAppliesTo(appliesTo) {
        const selectionDiv = document.getElementById('applies_to_selection');
        const select = document.getElementById('applies_to_ids');
        const label = document.getElementById('selection-label');

        if (appliesTo === 'cart') {
            selectionDiv.style.display = 'none';
            return;
        }

        if (selectionData[appliesTo]) {
            selectionDiv.style.display = 'block';
            select.innerHTML = '';
            
            selectionData[appliesTo].forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                select.appendChild(option);
            });

            // Update label
            const labels = {
                product: 'Seleccionar productos',
                category: 'Seleccionar categorías',
                brand: 'Seleccionar marcas',
                vendor: 'Seleccionar proveedores',
                customer: 'Seleccionar clientes',
                customer_type: 'Seleccionar tipos de cliente'
            };
            label.textContent = labels[appliesTo] || 'Seleccionar elementos';
        } else {
            selectionDiv.style.display = 'none';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.querySelector('select[name="type"]');
        const appliesSelect = document.querySelector('select[name="applies_to"]');
        
        if (typeSelect.value) {
            updateValueLabel(typeSelect.value);
        }
        
        if (appliesSelect.value) {
            updateAppliesTo(appliesSelect.value);
        }
    });
</script>

@endsection
