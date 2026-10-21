@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full mb-1">
        <div class="mb-4">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Obsequios por monto</h1>
            <p class="mt-1 text-sm text-gray-500">En pedidos Express, cada regla entrega su propio obsequio una sola vez cuando la compra de los productos asociados alcanza el monto.</p>
        </div>
        <div class="items-center justify-between block sm:flex md:divide-x md:divide-gray-100">
            <div class="flex items-center mb-4 sm:mb-0">
                <x-search :home="route('spending-gifts.index')" />
            </div>
            <a href="{{ route('spending-gifts.create') }}"
                class="text-white bg-blue-700 hover:bg-primary-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-2.5">
                Nuevo obsequio
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
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Nombre</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Obsequio</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Monto mínimo</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Productos</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase">Estado</th>
                            <th class="p-4 text-xs font-medium text-left text-gray-500 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($spendingGifts as $gift)
                            <tr class="hover:bg-gray-100">
                                <td class="p-4 text-sm font-medium text-gray-900">{{ $gift->name }}</td>
                                <td class="p-4 text-sm text-gray-500">{{ $gift->giftProduct->name ?? '—' }} × {{ $gift->gift_quantity }}</td>
                                <td class="p-4 text-sm text-gray-500">${{ number_format($gift->minimum_amount, 0, ',', '.') }}</td>
                                <td class="p-4 text-sm text-gray-500">{{ $gift->products_count }}</td>
                                <td class="p-4 text-sm text-gray-500">{{ $gift->active ? 'Activo' : 'Inactivo' }}</td>
                                <td class="p-4 text-sm text-right space-x-3">
                                    <a href="{{ route('spending-gifts.edit', $gift) }}" class="text-blue-600 hover:underline">Editar</a>
                                    <form action="{{ route('spending-gifts.destroy', $gift) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este obsequio?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-sm text-gray-500">Todavía no hay obsequios por monto.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="p-4">{{ $spendingGifts->links() }}</div>
@endsection
