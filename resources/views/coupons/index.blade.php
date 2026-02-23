@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Cupones</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex items-center mb-4 sm:mb-0">
                <x-search :home="route('coupons.index')" />
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('coupons.export') }}" 
                    class="text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300 font-bold rounded-lg text-sm px-5 py-2.5">
                    Exportar Todos
                </a>
                <a href="{{ route('coupons.export', ['only_mass_created' => 1]) }}" 
                    class="text-white bg-purple-700 hover:bg-purple-800 focus:ring-4 focus:ring-purple-300 font-bold rounded-lg text-sm px-5 py-2.5">
                    Exportar Masivos
                </a>
                <a href="{{ route('coupons.create') }}"
                    class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                    Nuevo cupón
                </a>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-col">
    <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
            <div class="overflow-hidden shadow">
                <table class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-100">
                        <tr>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Código
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Nombre
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Tipo
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Descuento
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Vigencia
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Uso
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Estado
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($coupons as $coupon)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900 hover:text-blue-500" href="{{ route('coupons.show', $coupon) }}">
                                    <span class="text-base font-semibold">
                                        {{ $coupon->code }}
                                    </span>
                                </a>
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                {{ $coupon->name }}
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    {{ $coupon->type === 'fixed_amount' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $coupon->type === 'fixed_amount' ? 'Monto fijo' : 'Porcentaje' }}
                                </span>
                            </td>
                            <td class="p-4 text-base font-medium text-gray-900 whitespace-nowrap">
                                @if($coupon->type === 'fixed_amount')
                                    ${{ number_format($coupon->value, 2) }}
                                @else
                                    {{ $coupon->value }}%
                                @endif
                            </td>
                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                <div class="text-sm">
                                    <div>{{ $coupon->valid_from->format('d/m/Y H:i') }}</div>
                                    <div>{{ $coupon->valid_to->format('d/m/Y H:i') }}</div>
                                </div>
                            </td>
                            <td class="p-4 text-sm text-gray-900 whitespace-nowrap">
                                <div class="text-sm">
                                    <div>{{ $coupon->current_usage }}{{ $coupon->total_usage_limit ? '/' . $coupon->total_usage_limit : '' }}</div>
                                    @if($coupon->usage_limit_per_customer)
                                        <div class="text-xs text-gray-500">{{ $coupon->usage_limit_per_customer }} por cliente</div>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                <div class="flex items-center">
                                    @php
                                        $isValid = $coupon->isValid();
                                        $isExpired = now() > $coupon->valid_to;
                                        $isNotYetActive = now() < $coupon->valid_from;
                                    @endphp
                                    <div @class([
                                        'inline-block w-4 h-4 mr-2 rounded-full',
                                        'bg-green-700' => $isValid,
                                        'bg-red-700' => !$coupon->active || $isExpired,
                                        'bg-yellow-500' => $coupon->active && $isNotYetActive
                                    ])></div>
                                    @if(!$coupon->active)
                                        Inactivo
                                    @elseif($isExpired)
                                        Expirado
                                    @elseif($isNotYetActive)
                                        Pendiente
                                    @else
                                        Activo
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('coupons.show', $coupon) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-gray-700 hover:bg-gray-800 focus:ring-4 focus:ring-gray-300">
                                    Ver
                                </a>
                                <a href="{{ route('coupons.edit', $coupon) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                                <button type="button"
                                    onclick="openMassCreateModal({{ $coupon->id }}, '{{ $coupon->code }}', '{{ route('coupons.mass-create', $coupon->id) }}')"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300">
                                    Crear masivamente
                                </button>
                                <form action="{{ route('coupons.toggle', $coupon) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg 
                                        {{ $coupon->active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700' }} focus:ring-4 focus:ring-yellow-300">
                                        {{ $coupon->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                @if($coupon->usages()->count() === 0)
                                <form action="{{ route('coupons.destroy', $coupon) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        onclick="return confirm('¿Estás seguro de que quieres eliminar este cupón?')"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300">
                                        Eliminar
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{ $coupons->links() }}

<!-- Mass Create Modal -->
<x-modal name="mass-create-modal" maxWidth="md">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Crear cupones masivamente</h3>
        <p class="text-sm text-gray-600 mb-4">
            Se crearán cupones basados en el cupón base <strong id="base-coupon-code"></strong>.
            Los nuevos cupones tendrán el mismo código seguido de números consecutivos (ej: <span id="example-code"></span>).
        </p>
        <form id="mass-create-form" method="POST">
            @csrf
            <div class="mb-4">
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                    Cantidad de cupones a crear
                </label>
                <input type="number" 
                    id="quantity" 
                    name="quantity" 
                    min="1" 
                    max="1000" 
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <p class="mt-1 text-xs text-gray-500">Máximo 1000 cupones por operación</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" 
                    x-on:click="$dispatch('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancelar
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    Crear
                </button>
            </div>
        </form>
    </div>
</x-modal>

<script>
    function openMassCreateModal(couponId, couponCode, routeUrl) {
        document.getElementById('base-coupon-code').textContent = couponCode;
        document.getElementById('example-code').textContent = couponCode + '1, ' + couponCode + '2, ' + couponCode + '3...';
        document.getElementById('mass-create-form').action = routeUrl;
        document.getElementById('quantity').value = '';
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'mass-create-modal' }));
    }
</script>

@endsection
