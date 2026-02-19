@extends('layouts.admin')

@section('content')

<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Zonas de Upsell/Cross-sell</h1>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex items-center mb-4 sm:mb-0">
                <x-search :home="route('admin.upsell-zones.index')" />
            </div>
            <a href="{{ route('admin.upsell-zones.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                Nueva Zona
            </a>
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
                                Nombre
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Contexto
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Max Productos
                            </th>
                            <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase">
                                Reglas
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
                        @foreach ($zones as $zone)
                        <tr class="hover:bg-gray-100">
                            <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                <a class="flex flex-col text-gray-900 hover:text-blue-500" href="{{ route('admin.upsell-zones.edit', $zone) }}">
                                    <span class="text-base font-semibold">{{ $zone->name }}</span>
                                    <span class="text-sm font-normal text-gray-500">{{ $zone->slug }}</span>
                                </a>
                            </td>
                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                @if($zone->context === 'product_detail')
                                    Detalle de Producto
                                @elseif($zone->context === 'cart')
                                    Carrito
                                @else
                                    Checkout
                                @endif
                            </td>
                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                {{ $zone->max_products }}
                            </td>
                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                {{ $zone->rules->count() }} regla(s)
                            </td>
                            <td class="p-4 text-base text-gray-900 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div @class([
                                        'inline-block w-4 h-4 mr-2 rounded-full',
                                        'bg-green-700' => $zone->active,
                                        'bg-red-700' => !$zone->active
                                    ])></div>
                                    {{ $zone->active ? 'Activo' : 'Inactivo' }}
                                </div>
                            </td>
                            <td class="p-4 space-x-2 whitespace-nowrap text-end">
                                <a href="{{ route('admin.upsell-zones.edit', $zone) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                    </svg>
                                    Editar
                                </a>
                                <a href="{{ route('admin.upsell-zones.manage-products', $zone) }}"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white rounded-lg bg-green-700 hover:bg-green-800 focus:ring-4 focus:ring-green-300">
                                    Productos
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{ $zones->links() }}

@endsection
