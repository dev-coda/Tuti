@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupon-tests.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a pruebas</a>
        <h1 class="text-2xl font-bold text-gray-900">Prueba simulada de cupones</h1>
        <p class="text-gray-600 mt-1">Crea una orden de prueba (no se guarda ni transmite). Inspecciona el XML generado.</p>
    </div>

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <ul class="text-sm text-red-800 list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('coupon-tests.mock.run') }}" class="max-w-2xl space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Cliente *</label>
            <select name="user_id" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">Selecciona un cliente (con zona)</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->document ?? 'N/A' }})
                    </option>
                @endforeach
            </select>
            @if($users->isEmpty())
                <p class="mt-1 text-sm text-amber-600">No hay usuarios con zonas asignadas.</p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Productos *</label>
            <div id="product-rows" class="space-y-3">
                @php $oldProducts = old('products', [['product_id' => '', 'quantity' => 1, 'variation_id' => '']]); @endphp
                @foreach($oldProducts as $idx => $row)
                    <div class="flex gap-2 items-end product-row">
                        <div class="flex-1">
                            <select name="products[{{ $idx }}][product_id]" required class="block w-full rounded-lg border-gray-300 shadow-sm">
                                <option value="">Producto</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" {{ ($row['product_id'] ?? '') == $p->id ? 'selected' : '' }}>
                                        {{ $p->name }} - ${{ number_format($p->price, 0) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-24">
                            <input type="number" name="products[{{ $idx }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" min="1" class="block w-full rounded-lg border-gray-300 shadow-sm" placeholder="Cant.">
                        </div>
                        <div class="w-32">
                            <input type="number" name="products[{{ $idx }}][variation_id]" value="{{ $row['variation_id'] ?? '' }}" class="block w-full rounded-lg border-gray-300 shadow-sm" placeholder="Variación ID (opc.)">
                        </div>
                        <button type="button" class="remove-row px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg" title="Quitar">×</button>
                    </div>
                @endforeach
            </div>
            <button type="button" id="add-row" class="mt-2 text-sm text-blue-600 hover:text-blue-800 font-medium">+ Agregar producto</button>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Códigos de cupón</label>
            <input type="text" name="coupon_codes_text" value="{{ old('coupon_codes_text') }}" placeholder="Ej: CUPON1, CUPON2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
            <p class="mt-1 text-xs text-gray-500">Separados por coma. Se aplicarán en orden.</p>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                Generar XML de prueba
            </button>
            <a href="{{ route('coupon-tests.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('product-rows');
    const addBtn = document.getElementById('add-row');
    let rowIndex = {{ count(old('products', [[]])) }};

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'flex gap-2 items-end product-row';
        row.innerHTML = `
            <div class="flex-1">
                <select name="products[${rowIndex}][product_id]" required class="block w-full rounded-lg border-gray-300 shadow-sm">
                    <option value="">Producto</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} - ${{ number_format($p->price, 0) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-24">
                <input type="number" name="products[${rowIndex}][quantity]" value="1" min="1" class="block w-full rounded-lg border-gray-300 shadow-sm">
            </div>
            <div class="w-32">
                <input type="number" name="products[${rowIndex}][variation_id]" class="block w-full rounded-lg border-gray-300 shadow-sm" placeholder="Variación ID">
            </div>
            <button type="button" class="remove-row px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg">×</button>
        `;
        container.appendChild(row);
        rowIndex++;
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    });

    container.querySelectorAll('.remove-row').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('.product-row');
            if (container.querySelectorAll('.product-row').length > 1) row.remove();
        });
    });
});
</script>
@endsection
