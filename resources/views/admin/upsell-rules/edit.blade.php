@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('admin.upsell-rules.update', $upsellRule)->put() }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Regla de Upsell</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información</h3>
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('name', 'Nombre')->value($upsellRule->name)->required()->groupClass('col-span-6') }}
                {{ Aire::select($ruleTypes, 'type', 'Tipo de Regla')->value($upsellRule->type)->required()->groupClass('col-span-6')->id('rule-type') }}
                {{ Aire::textarea('description', 'Descripción')->value($upsellRule->description)->rows(3)->groupClass('col-span-6') }}
                {{ Aire::number('priority', 'Prioridad')->value($upsellRule->priority)->min(0)->groupClass('col-span-6') }}
                
                <div class="col-span-6">
                    {{ Aire::hidden('active')->value(0) }}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" {{ $upsellRule->active ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Activo</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Manual Products Section (shown only when type is 'manual') -->
        <div id="manual-products-section" class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm" style="display: {{ $upsellRule->type === 'manual' ? 'block' : 'none' }};">
            <h3 class="mb-4 text-xl font-semibold">Productos Manuales</h3>
            <p class="mb-4 text-sm text-gray-600">
                Selecciona los productos que se mostrarán con esta regla de upsell.
            </p>
            
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-900">Productos específicos</label>
                <input 
                    type="text" 
                    id="product-filter"
                    placeholder="Buscar por SKU o nombre..."
                    class="w-full mb-2 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                >
                <div class="border border-gray-300 rounded-lg p-3 max-h-60 overflow-y-auto bg-gray-50">
                    @php
                        $selectedProductIds = $upsellRule->type === 'manual' && isset($upsellRule->config['product_ids']) 
                            ? $upsellRule->config['product_ids'] 
                            : [];
                    @endphp
                    @foreach($products as $product)
                        <label class="flex items-center py-1.5 px-2 hover:bg-white rounded cursor-pointer product-item">
                            <input 
                                type="checkbox" 
                                name="product_ids[]" 
                                value="{{ $product['id'] }}"
                                {{ in_array($product['id'], $selectedProductIds) ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                            >
                            <span class="ml-2 text-sm text-gray-700" data-search="{{ strtolower($product['display']) }}">
                                {{ $product['display'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-500">Selecciona los productos que desees</p>
            </div>
        </div>

        <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
            <p class="flex space-x-2 items-center">
                {{ Aire::submit('Actualizar')->variant()->submit() }}
                <a href="{{ route('admin.upsell-rules.index') }}" class="text-gray-600 hover:text-gray-800">Cancelar</a>
            </p>
        </div>
    </div>
</div>
{{ Aire::close() }}

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ruleTypeSelect = document.getElementById('rule-type');
    const manualProductsSection = document.getElementById('manual-products-section');
    
    // Show/hide manual products section based on selected type
    function toggleManualProductsSection() {
        if (ruleTypeSelect.value === 'manual') {
            manualProductsSection.style.display = 'block';
        } else {
            manualProductsSection.style.display = 'none';
        }
    }
    
    // Initial check
    toggleManualProductsSection();
    
    // Listen for changes
    ruleTypeSelect.addEventListener('change', toggleManualProductsSection);
    
    // Product filter functionality
    const productFilter = document.getElementById('product-filter');
    if (productFilter) {
        productFilter.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('.product-item');
            
            items.forEach(function(item) {
                const searchText = item.querySelector('[data-search]').getAttribute('data-search');
                if (searchText.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>
@endsection
