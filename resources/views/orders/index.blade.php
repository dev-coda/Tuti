@extends('layouts.admin')


@section('content')

    @if(session('success'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
        <div class="flex flex-col w-full mb-1">
            <div class="mb-4 flex justify-between">
                <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Pedidos</h1>
                <div>
                    <a href="{{ '/orderexport?from_date=' . (request()->from_date ? request()->from_date : '') . '&to_date=' . (request()->to_date ? request()->to_date : '') }}">
                        @svg('heroicon-o-arrow-down-on-square', 'w-8 h-8 text-blue-500')
                    </a>
                </div>


            </div>

            <div class="flex items-center mb-4 w-full">
                <form method="GET" action="{{ route('orders.index') }}"
                    class="xl:flex grid grid-cols-1 gap-y-5 w-full xl:space-x-2 space-x-0">

                    <div>

                        <div class="relative w-full sm:w-64 xl:w-96">
                            <input type="text" name='q' placeholder="Buscar" value="{{ request()->q }}"
                                class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5 ">
                        </div>
                    </div>

                    <div>
                        <select name="zone"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                            <option value="">Todas las zonas</option>
                            @foreach ($zones as $zone)
                                <option value="{{ $zone }}" {{ request()->zone == $zone ? 'selected' : '' }}>
                                    {{ $zone }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{ Aire::select($sellers, 'seller_id')->value(request()->seller_id)->groupClass('mb-0') }}

                    <div>
                        <input type="date" name="from_date" value="{{ request()->from_date }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                    </div>
                    <div>
                        <input type="date" name="to_date" value="{{ request()->to_date }}"
                            class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded focus:ring-primary-500 focus:border-primary-500 block w-full p-2.5">
                    </div>
                    {{ Aire::button('Buscar')->variant()->submit() }}
                    @if(request()->q || request()->seller_id)
                        <a href="{{route('orders.index')}}"
                            class="inline-flex justify-center items-center p-1 text-gray-500 rounded cursor-pointer hover:text-gray-900 hover:bg-gray-100 d">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </a>
                    @endif
                </form>

            </div>


        </div>
    </div>
    <div class="flex flex-col">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div class="overflow-hidden shadow">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                        <thead class="bg-gray-100">
                            <tr>

                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Id
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Fecha
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Cliente
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Estado
                                </th>
                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Total
                                </th>

                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Descuentos
                                </th>


                                <th scope="col" class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Productos
                                </th>

                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 ">

                            <tr class="hover:bg-gray-100 text-xs">
                                @foreach ($orders as $order)
                                        <td class="w-20 p-4 text-xs font-normal text-gray-500 whitespace-nowrap">
                                            <a class="flex flex-col text-gray-900  hover:text-blue-500"
                                                href="{{ route('orders.edit', $order) }}">
                                                #{{ $order->id }}
                                            </a>
                                        </td>
                                        <td class="p-4 text-xs font-sm text-gray-900 whitespace-nowra">
                                            {{ $order->created_at->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="p-4 text-xs font-medium text-gray-900 whitespace-nowra">

                                            <div class="flex flex-col">
                                                <a class="flex flex-col text-gray-900  hover:text-blue-500"
                                                    href="{{ route('users.edit', $order->user) }}">
                                                    {{ $order->user->name }}
                                                </a>
                                                @if($order->seller)
                                                    <small class="text-gray-500">Vendedor: {{ $order->seller->name }}</small>
                                                @endif

                                            </div>
                                        </td>

                                        <td class="p-4   text-gray-900 whitespace-nowra">
                                            <x-order-status :status="$order->status_id" />
                                            @if($order->status_id === 3)
                                                <form action="{{ route('orders.resend', $order) }}" method="POST" class="inline ml-2">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="inline-flex items-center p-1 border border-gray-300 rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500"
                                                            onclick="return confirm('¿Está seguro que desea reenviar esta orden?')"
                                                            title="Reenviar orden">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>

                                        <td class="p-4  text-gray-900 whitespace-nowra">
                                            {{ number_format($order->total, 2) }}
                                        </td>


                                        <td class="p-4   text-gray-900 whitespace-nowra">
                                            {{ number_format($order->discount, 2) }}
                                        </td>


                                        <td class="p-4  text-gray-900 whitespace-nowra">
                                            {{ $order->products->count() }}
                                        </td>

                                    </tr>

                                @endforeach



                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    {{ $orders->links() }}











@endsection