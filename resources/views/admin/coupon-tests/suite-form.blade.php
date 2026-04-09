@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupon-tests.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a pruebas</a>
        <h1 class="text-2xl font-bold text-gray-900">Suite de escenarios de cupones/XML</h1>
        <p class="text-gray-600 mt-1">Ejecuta varios escenarios en una sola corrida para validar descuentos y XML. No transmite órdenes.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('coupon-tests.suite.run') }}" class="space-y-6">
        @csrf
        <div class="max-w-lg">
            <label class="block text-sm font-medium text-gray-700">Cliente base *</label>
            <select name="user_id" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                <option value="">Selecciona un cliente (con zona)</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->document ?? 'N/A' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Escenarios (JSON) *</label>
            <textarea id="scenarios-json" name="scenarios_json" rows="22" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm font-mono text-xs">{{ old('scenarios_json', $seededScenariosJson ?? '[]') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">
                Cada escenario requiere: <code>name</code>, <code>coupon_codes</code> (array o texto con comas) y <code>products</code> (array con <code>product_id</code>, <code>quantity</code>, opcional <code>variation_id</code>).
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Incluye semilla automática con baseline, todos los cupones activos individuales, combinaciones por pares (máx. 50) y ternas (máx. 20), y casos edge de XML.
                La suite se ejecuta en segundo plano.
            </p>
            <button type="button" id="reset-seed" class="mt-2 px-3 py-1.5 bg-gray-100 border border-gray-300 rounded text-xs text-gray-800 hover:bg-gray-200">
                Restablecer JSON semilla
            </button>
        </div>

        <div class="flex gap-2">
            <button type="submit" id="submit-suite" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Ejecutar suite
            </button>
            <a href="{{ route('coupon-tests.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 font-medium">
                Cancelar
            </a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('scenarios-json');
    const resetBtn = document.getElementById('reset-seed');
    const seed = @json($seededScenariosJson ?? '[]');
    if (!textarea || !resetBtn) return;
    resetBtn.addEventListener('click', function () {
        textarea.value = seed;
    });

    const form = textarea.closest('form');
    const submitBtn = document.getElementById('submit-suite');
    if (form && submitBtn) {
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando…';
        });
    }
});
</script>
@endsection
