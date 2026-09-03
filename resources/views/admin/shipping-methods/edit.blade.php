@extends('layouts.admin')

@section('title', 'Editar Método de Envío')

@section('content')
{{ Aire::open()->route('shipping-methods.update', $shippingMethod)->bind($shippingMethod)->put() }}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Método de Envío</h1>
        <p class="text-sm text-gray-500 mt-1">Actualiza la información del método de envío</p>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información del Método</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('name', 'Nombre')->groupClass('col-span-6')->helpText('Nombre que verán los clientes') }}
                
                {{ Aire::textarea('description', 'Descripción')->rows(3)->groupClass('col-span-6')->helpText('Descripción breve del método de envío') }}
                
                {{ Aire::input('sort_order', 'Orden de visualización')->type('number')->groupClass('col-span-3')->helpText('Número más bajo aparece primero') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('enabled', 'Habilitado')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está habilitado, el método estará disponible para los clientes
                        </span>
                    </div>
                </div>

                <div class="col-span-6">
                    <div class="flex items-start">
                        {{ Aire::checkbox('restrict_cities', 'Limitar a ciudades seleccionadas')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Piloto: el método solo aparece en las ciudades marcadas abajo. Sin esta opción, está disponible en todas excepto las que desmarques.
                        </span>
                    </div>
                </div>

                <div class="col-span-6 mt-2">
                    <h4 class="text-sm font-semibold text-gray-900">Disponibilidad por ciudad</h4>
                    <p id="city-shipping-help-restrict" class="mt-1 text-sm text-gray-500 {{ $shippingMethod->restrict_cities ? '' : 'hidden' }}">
                        Solo las ciudades marcadas tendrán este método. Ideal para un piloto en una ciudad.
                    </p>
                    <p id="city-shipping-help-all" class="mt-1 text-sm text-gray-500 {{ $shippingMethod->restrict_cities ? 'hidden' : '' }}">
                        Aplica a la ciudad del cliente. Desmarca las ciudades donde no debe estar disponible.
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <input type="search" id="city-shipping-filter" placeholder="Buscar ciudad o departamento"
                               class="flex-1 min-w-[12rem] rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                        <button type="button" id="city-shipping-select-all" class="px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Marcar todas
                        </button>
                        <button type="button" id="city-shipping-select-none" class="px-3 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Ninguna
                        </button>
                    </div>
                    <div class="mt-3 max-h-80 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100">
                        @forelse($cities as $city)
                            @php($isOn = $shippingMethod->restrict_cities ? ($cityEnabled[$city->id] ?? false) : ($cityEnabled[$city->id] ?? true))
                            <label data-city-row class="flex items-center justify-between gap-3 px-4 py-2 hover:bg-gray-50">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900">{{ $city->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $city->state?->name }}</span>
                                </span>
                                <span class="flex items-center gap-2 shrink-0">
                                    <input type="hidden" name="city_enabled[{{ $city->id }}]" value="0">
                                    <input type="checkbox" name="city_enabled[{{ $city->id }}]" value="1"
                                           data-city-enabled
                                           class="rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                           @checked($isOn)>
                                </span>
                            </label>
                        @empty
                            <p class="px-4 py-3 text-sm text-gray-500">No hay ciudades registradas.</p>
                        @endforelse
                    </div>
                </div>

                <div class="col-span-6 justify-between items-center mt-5 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('shipping-methods.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-1">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Detalles</h3>
            
            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Código</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $shippingMethod->code }}</dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Ciudades</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($shippingMethod->restrict_cities)
                            Solo ciudades seleccionadas
                        @else
                            Todas (con exclusiones)
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Estado actual</dt>
                    <dd class="mt-1">
                        @if($shippingMethod->enabled)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Habilitado
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Deshabilitado
                            </span>
                        @endif
                    </dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Creado</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $shippingMethod->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                
                <div>
                    <dt class="text-sm font-medium text-gray-500">Última actualización</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $shippingMethod->updated_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        <!-- Warning Box -->
        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex">
                <svg class="w-5 h-5 text-yellow-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div class="text-sm text-yellow-800">
                    <p class="font-medium mb-1">Advertencia</p>
                    <p>Al deshabilitar este método, los clientes no podrán seleccionarlo al realizar pedidos.</p>
                </div>
            </div>
        </div>
    </div>
</div>
{{ Aire::close() }}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('city-shipping-filter');
    const restrict = document.querySelector('input[name="restrict_cities"]');
    const helpRestrict = document.getElementById('city-shipping-help-restrict');
    const helpAll = document.getElementById('city-shipping-help-all');

    if (input) {
        input.addEventListener('input', () => {
            const needle = input.value.trim().toLowerCase();
            document.querySelectorAll('[data-city-row]').forEach((row) => {
                row.classList.toggle('hidden', needle !== '' && !row.textContent.toLowerCase().includes(needle));
            });
        });
    }

    const setAll = (checked) => {
        document.querySelectorAll('[data-city-enabled]').forEach((box) => {
            box.checked = checked;
        });
    };
    document.getElementById('city-shipping-select-all')?.addEventListener('click', () => setAll(true));
    document.getElementById('city-shipping-select-none')?.addEventListener('click', () => setAll(false));

    const syncHelp = () => {
        const on = !!(restrict && restrict.checked);
        helpRestrict?.classList.toggle('hidden', !on);
        helpAll?.classList.toggle('hidden', on);
    };
    restrict?.addEventListener('change', syncHelp);
});
</script>
@endsection
