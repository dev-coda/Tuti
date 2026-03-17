@extends('layouts.admin')

@section('content')

<form action="{{ route('admin.retentions.update', $retention) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
        <div class="mb-4 col-span-full xl:mb-2">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Regla de Retención</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $retention->tax_group }} — {{ \App\Models\RetentionRule::PRODUCT_TYPES[$retention->product_type] ?? $retention->product_type }}</p>
        </div>

        <div class="col-span-2">
            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-4 text-xl font-semibold">Información General</h3>

                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-3">
                        <label for="tax_group" class="block mb-2 text-sm font-medium text-gray-900">Grupo de Impuestos</label>
                        <select name="tax_group" id="tax_group" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            @foreach($taxGroups as $value => $label)
                                <option value="{{ $value }}" @selected(old('tax_group', $retention->tax_group) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tax_group')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-3">
                        <label for="product_type" class="block mb-2 text-sm font-medium text-gray-900">Tipo de Producto</label>
                        <select name="product_type" id="product_type" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            @foreach($productTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('product_type', $retention->product_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('product_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-4 text-xl font-semibold">Retención en la Fuente</h3>
                <p class="text-sm text-gray-500 mb-4">Se calcula sobre el subtotal. Solo aplica cuando el subtotal supera la base.</p>

                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-3">
                        <label for="base_rte_fuente" class="block mb-2 text-sm font-medium text-gray-900">Base Rte Fuente ($)</label>
                        <input type="number" name="base_rte_fuente" id="base_rte_fuente" step="0.01" min="0"
                            value="{{ old('base_rte_fuente', $retention->base_rte_fuente) }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        <p class="mt-1 text-xs text-gray-400">Monto mínimo de subtotal para aplicar retención</p>
                        @error('base_rte_fuente')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-3">
                        <label for="pct_rte_fuente" class="block mb-2 text-sm font-medium text-gray-900">% Rte Fuente</label>
                        <input type="number" name="pct_rte_fuente" id="pct_rte_fuente" step="0.01" min="0" max="100"
                            value="{{ old('pct_rte_fuente', $retention->pct_rte_fuente) }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        @error('pct_rte_fuente')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-4 text-xl font-semibold">Retención de IVA</h3>
                <p class="text-sm text-gray-500 mb-4">Se calcula sobre el IVA. Solo aplica cuando el IVA supera la base.</p>

                <div class="grid grid-cols-6 gap-6">
                    <div class="col-span-3">
                        <label for="base_rte_iva" class="block mb-2 text-sm font-medium text-gray-900">Base Rte IVA ($)</label>
                        <input type="number" name="base_rte_iva" id="base_rte_iva" step="0.01" min="0"
                            value="{{ old('base_rte_iva', $retention->base_rte_iva) }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        <p class="mt-1 text-xs text-gray-400">Monto mínimo de IVA para aplicar retención</p>
                        @error('base_rte_iva')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="col-span-3">
                        <label for="pct_rte_iva" class="block mb-2 text-sm font-medium text-gray-900">% Rte IVA</label>
                        <input type="number" name="pct_rte_iva" id="pct_rte_iva" step="0.01" min="0" max="100"
                            value="{{ old('pct_rte_iva', $retention->pct_rte_iva) }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        @error('pct_rte_iva')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" id="active" value="1" @checked(old('active', $retention->active))
                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                    <label for="active" class="ml-2 text-sm font-medium text-gray-900">Regla activa</label>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <button type="submit"
                    class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                    Guardar Cambios
                </button>
                <a href="{{ route('admin.retentions.index') }}" class="text-gray-600 hover:text-gray-900">Cancelar</a>
            </div>
        </div>

        <div class="col-span-1">
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">Referencia de Grupos</h4>
                <dl class="text-xs text-blue-700 space-y-2">
                    <div>
                        <dt class="font-semibold">C_NORETIE</dt>
                        <dd>Sin retenciones. Base y porcentajes en 0.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold">C_NAL</dt>
                        <dd>Artículo: ReteFuente 2.5% (base $524.000). Flete: ReteFuente 1% (base $105.000).</dd>
                    </div>
                    <div>
                        <dt class="font-semibold">C_NAL_GRC</dt>
                        <dd>Igual a C_NAL más ReteIVA 15% (base $99.560).</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</form>

@endsection
