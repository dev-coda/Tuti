@extends('layouts.admin')

@section('title', 'Editar Método de Envío')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar Método de Envío</h1>
        <p class="text-sm text-gray-500 mt-1">Actualiza la información del método de envío</p>
    </div>

    @if(session('success'))
        <div class="col-span-full">
            <div class="p-4 mb-4 text-sm text-green-800 bg-green-100 border border-green-200 rounded-lg">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="col-span-2 space-y-4">
        {{ Aire::open()->route('shipping-methods.update', $shippingMethod)->bind($shippingMethod)->put() }}
        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Información del Método</h3>

            <div class="grid grid-cols-6 gap-6">
                {{ Aire::input('name', 'Nombre')->groupClass('col-span-6')->helpText('Nombre que verán los clientes') }}

                {{ Aire::textarea('description', 'Descripción')->rows(3)->groupClass('col-span-6')->helpText('Descripción breve del método de envío') }}

                {{ Aire::input('sort_order', 'Orden de visualización')->type('number')->groupClass('col-span-3')->helpText('Número más bajo aparece primero') }}

                <div class="col-span-6">
                    <div class="flex items-center">
                        {{ Aire::checkbox('enabled', 'Habilitado')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Si está deshabilitado, no aparece en ninguna ciudad.
                        </span>
                    </div>
                </div>

                <div class="col-span-6">
                    <div class="flex items-start">
                        {{ Aire::checkbox('restrict_cities', 'Limitar a ciudades seleccionadas (piloto)')->value(1) }}
                        <span class="ml-2 text-sm text-gray-600">
                            Recomendado para Entrega Especial: solo las ciudades que actives abajo lo verán. Cambiar este modo limpia la lista de ciudades para no mezclar reglas.
                        </span>
                    </div>
                </div>

                <div class="col-span-6 justify-between items-center mt-2 space-x-2 flex">
                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Guardar método')->variant()->submit() }}
                        <a href="{{ route('shipping-methods.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Volver
                        </a>
                    </p>
                </div>
            </div>
        </div>
        {{ Aire::close() }}

        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-1 text-lg font-semibold text-gray-900">Disponibilidad por ciudad</h3>
            <p class="text-sm text-gray-500 mb-3">
                Cada ciudad se activa o desactiva sola. Guardar una ciudad <strong>no reescribe</strong> las demás.
                @if($shippingMethod->restrict_cities)
                    Modo actual: <span class="font-medium text-orange-700">piloto / allowlist</span> — off por defecto; marca solo las ciudades del piloto.
                @else
                    Modo actual: <span class="font-medium text-gray-700">global / opt-out</span> — on por defecto; desactiva solo las que quieras excluir.
                @endif
            </p>

            <div class="mb-3">
                <input type="search" id="city-shipping-filter" placeholder="Buscar ciudad o departamento"
                       class="w-full rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
            </div>

            <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100">
                @forelse($cities as $city)
                    @php($isOn = $shippingMethod->restrict_cities ? ($cityEnabled[$city->id] ?? false) : ($cityEnabled[$city->id] ?? true))
                    <div data-city-row class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900">{{ $city->name }}</div>
                            <div class="text-xs text-gray-500">{{ $city->state?->name }} · id {{ $city->id }}</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($isOn)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">ON</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">OFF</span>
                            @endif

                            <form method="POST" action="{{ route('shipping-methods.toggle-city', [$shippingMethod, $city]) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="enabled" value="{{ $isOn ? '0' : '1' }}">
                                <button type="submit"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $isOn ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-green-200 text-green-700 hover:bg-green-50' }}">
                                    {{ $isOn ? 'Desactivar solo esta' : 'Activar solo esta' }}
                                </button>
                            </form>

                            @if($shippingMethod->code === 'express')
                                <a href="{{ route('shipping-methods.edit', ['shippingMethod' => $shippingMethod, 'diagnose_city' => $city->id]) }}"
                                   class="px-3 py-1.5 text-xs font-medium text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-50">
                                    Diagnosticar
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-gray-500">No hay ciudades registradas.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-span-1 space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-xl font-semibold">Detalles</h3>

            <dl class="space-y-3">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Código</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $shippingMethod->code }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Modo ciudades</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($shippingMethod->restrict_cities)
                            Piloto (solo seleccionadas)
                        @else
                            Global (con exclusiones)
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Estado actual</dt>
                    <dd class="mt-1">
                        @if($shippingMethod->enabled)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Habilitado</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Deshabilitado</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Última actualización</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $shippingMethod->updated_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </div>

        @if($shippingMethod->code === 'express')
            <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-2 text-lg font-semibold text-gray-900">Diagnóstico Entrega Especial</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Checklist explícito de por qué se muestra u oculta para una ciudad (env, setting, método, ciudad, zona).
                </p>

                <form method="GET" action="{{ route('shipping-methods.edit', $shippingMethod) }}" class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad a diagnosticar</label>
                    <div class="flex gap-2">
                        <select name="diagnose_city" class="flex-1 rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500">
                            <option value="">— elegir —</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" @selected((int) $diagnoseCityId === (int) $city->id)>
                                    {{ $city->name }}{{ $city->state ? ' / '.$city->state->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700">
                            Ver
                        </button>
                    </div>
                </form>

                @if($diagnosis)
                    <div class="mb-3 p-3 rounded-lg border {{ $diagnosis['visible'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <p class="text-sm font-semibold {{ $diagnosis['visible'] ? 'text-green-800' : 'text-red-800' }}">
                            {{ $diagnosis['visible'] ? 'VISIBLE en carrito' : 'OCULTO en carrito' }}
                            @if($diagnosis['city_name'])
                                · {{ $diagnosis['city_name'] }}
                            @endif
                        </p>
                        <p class="text-xs mt-1 text-gray-600">Modo: {{ $diagnosis['mode'] }}</p>
                    </div>
                    <ul class="space-y-2">
                        @foreach($diagnosis['checks'] as $check)
                            <li class="text-sm border border-gray-100 rounded-lg p-2">
                                <div class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold {{ $check['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ $check['ok'] ? '✓' : '✗' }}
                                    </span>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $check['label'] }}</div>
                                        <div class="text-xs text-gray-600 mt-0.5">{{ $check['detail'] }}</div>
                                        <div class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">{{ $check['key'] }}</div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-gray-500">
                        API: <code class="bg-gray-100 px-1 rounded">GET {{ route('shipping-methods.diagnose-express') }}?city_id={{ $diagnoseCityId }}</code>
                    </p>
                @endif
            </div>
        @endif

        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-sm font-medium text-amber-900 mb-1">Sin efectos colaterales</p>
            <p class="text-sm text-amber-800">
                Usa “Activar/Desactivar solo esta” por ciudad. No uses un guardado masivo de checkboxes: eso era lo que podía alterar otras ciudades sin querer.
            </p>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('city-shipping-filter');
    if (!input) return;
    input.addEventListener('input', () => {
        const needle = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-city-row]').forEach((row) => {
            row.classList.toggle('hidden', needle !== '' && !row.textContent.toLowerCase().includes(needle));
        });
    });
});
</script>
@endsection
