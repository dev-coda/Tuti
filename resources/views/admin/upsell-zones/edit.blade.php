@extends('layouts.admin')

@section('content')
{{ Aire::open()->route('admin.upsell-zones.update', $upsellZone)->put() }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Zona de Upsell</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información Básica</h3>
            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('name', 'Nombre')->value($upsellZone->name)->required()->groupClass('col-span-6') }}
                {{ Aire::input('slug', 'Slug')->value($upsellZone->slug)->groupClass('col-span-6') }}
                {{ Aire::textarea('description', 'Descripción')->value($upsellZone->description)->rows(3)->groupClass('col-span-6') }}
                {{ Aire::input('display_title', 'Título a mostrar')->value($upsellZone->display_title)->groupClass('col-span-6')->helpText('Si está vacío, se usará el nombre de la zona') }}
                {{ Aire::select(['product_detail' => 'Detalle de Producto', 'cart' => 'Carrito', 'checkout' => 'Checkout'], 'context', 'Contexto')->value($upsellZone->context)->required()->groupClass('col-span-6') }}
                {{ Aire::number('max_products', 'Máximo de productos')->value($upsellZone->max_products)->min(1)->max(20)->required()->groupClass('col-span-6') }}
                {{ Aire::number('position', 'Posición')->value($upsellZone->position)->min(0)->groupClass('col-span-6') }}
                
                <div class="col-span-6">
                    {{ Aire::hidden('active')->value(0) }}
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" {{ $upsellZone->active ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ml-3 text-sm font-medium text-gray-900">Activo</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Reglas de Selección</h3>
            <div id="rules-container" class="space-y-4">
                @php
                    $zoneRuleIds = $upsellZone->rules->pluck('id')->toArray();
                    $zoneRulePriorities = $upsellZone->rules->pluck('pivot.priority', 'id')->toArray();
                @endphp
                @foreach($allRules as $rule)
                    @php
                        $isAttached = in_array($rule->id, $zoneRuleIds);
                        $priority = $zoneRulePriorities[$rule->id] ?? 0;
                    @endphp
                    <div class="rule-item p-4 border border-gray-200 rounded-lg">
                        <div class="grid grid-cols-6 gap-4">
                            <div class="col-span-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Regla</label>
                                <select name="rule_ids[]" class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">Seleccione una regla</option>
                                    @foreach($allRules as $r)
                                        <option value="{{ $r->id }}" {{ $r->id == $rule->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                                <input type="number" name="rule_priorities[]" value="{{ $isAttached ? $priority : 0 }}" min="0" class="w-full p-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <button type="button" class="mt-2 text-red-600 hover:text-red-800 remove-rule">Eliminar</button>
                    </div>
                @endforeach
            </div>
            <button type="button" id="add-rule" class="mt-4 text-blue-600 hover:text-blue-800">+ Agregar Regla</button>
        </div>

        <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
            <p class="flex space-x-2 items-center">
                {{ Aire::submit('Actualizar')->variant()->submit() }}
                <a href="{{ route('admin.upsell-zones.index') }}" class="text-gray-600 hover:text-gray-800">Cancelar</a>
            </p>
        </div>
    </div>
</div>
{{ Aire::close() }}

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('rules-container');
    const addButton = document.getElementById('add-rule');
    const rules = @json($allRules);
    
    if (addButton) {
        addButton.addEventListener('click', function() {
            const ruleItem = document.createElement('div');
            ruleItem.className = 'rule-item p-4 border border-gray-200 rounded-lg';
            ruleItem.innerHTML = `
                <div class="grid grid-cols-6 gap-4">
                    <div class="col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Regla</label>
                        <select name="rule_ids[]" class="w-full p-2 border border-gray-300 rounded-md">
                            <option value="">Seleccione una regla</option>
                            ${rules.map(r => `<option value="${r.id}">${r.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                        <input type="number" name="rule_priorities[]" value="0" min="0" class="w-full p-2 border border-gray-300 rounded-md">
                    </div>
                </div>
                <button type="button" class="mt-2 text-red-600 hover:text-red-800 remove-rule">Eliminar</button>
            `;
            container.appendChild(ruleItem);
            
            ruleItem.querySelector('.remove-rule').addEventListener('click', function() {
                ruleItem.remove();
            });
        });
    }
    
    document.querySelectorAll('.remove-rule').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.rule-item').remove();
        });
    });
});
</script>
@endsection
