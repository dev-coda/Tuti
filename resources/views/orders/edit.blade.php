@extends('layouts.admin')


@section('content')



<div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
    <div class="w-full">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Compra #{{ $order->id }}</h1>
            <div class="flex items-center space-x-4">
                <!-- XML Transmission Retry Button (Primary) -->
                <div class="flex space-x-2">
                    <form method="POST" action="{{ route('orders.retry-xml-transmission', $order) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 mr-2')
                            Reintentar XML
                        </button>
                    </form>
                    
                    <!-- Email Retry Buttons (Secondary) -->
                    <form method="POST" action="{{ route('orders.retry-confirmation-email', $order) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            @svg('heroicon-o-envelope', 'w-4 h-4 mr-1')
                            Reenviar Confirmación
                        </button>
                    </form>
                    <form method="POST" action="{{ route('orders.retry-status-email', $order) }}" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            @svg('heroicon-o-envelope', 'w-4 h-4 mr-1')
                            Reenviar Estado
                        </button>
                    </form>
                </div>
                
                @if(request()->filled('code'))
                <a href="{{route('orders.edit',[$order])}}">
                     @svg('heroicon-o-x-circle', 'w-6 h-6 text-blue-500')
                </a>
                @else
                <a href="{{route('orders.edit',[$order, 'code'=>1])}}">
                     @svg('heroicon-o-code-bracket', 'w-6 h-6 text-blue-500')
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="p-4 bg-white border-b border-gray-200">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
        <div class="rounded-lg border border-gray-200 p-4">
            <p class="font-semibold text-gray-800 mb-2">Vendedor</p>
            @if($order->seller)
                <p>
                    <a class="text-blue-600 hover:underline" href="{{ route('users.edit', $order->seller) }}">
                        {{ $order->seller->name }}
                    </a>
                </p>
                @if($order->seller->email)
                    <p><span class="text-gray-500">Email:</span> {{ $order->seller->email }}</p>
                @endif
            @else
                <p class="text-gray-500">—</p>
            @endif
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <p class="font-semibold text-gray-800 mb-2">Envío / Proveedor</p>
            <p><span class="text-gray-500">Proveedor:</span> {{ $order->shipping_provider ?? 'tronex' }}</p>
            <p><span class="text-gray-500">Costo cotizado:</span> ${{ number_format((float)($order->shipping_quote_amount ?? 0), 2) }}</p>
            <p><span class="text-gray-500">Método:</span> {{ $order->delivery_method }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 p-4">
            <p class="font-semibold text-gray-800 mb-2">Integración Coordinadora / FV</p>
            <p><span class="text-gray-500">FV:</span> {{ $order->fv_number ?? '-' }}</p>
            <p><span class="text-gray-500">Guía:</span> {{ $order->coordinadora_guide_number ?? '-' }}</p>
            <p><span class="text-gray-500">Estado:</span> {{ $order->coordinadora_status_text ?? '-' }}</p>
            <p><span class="text-gray-500">Actualizado:</span> {{ optional($order->coordinadora_status_at)->format('d/m/Y H:i') ?? '-' }}</p>
        </div>
    </div>

    @if($order->fv_request_payload || $order->fv_response_payload || $order->coordinadora_request_payload || $order->coordinadora_response_payload)
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            @if($order->fv_request_payload)
                <details class="rounded border border-gray-200 p-3">
                    <summary class="cursor-pointer font-semibold">FV Request</summary>
                    <pre class="mt-2 overflow-auto">{{ $order->fv_request_payload }}</pre>
                </details>
            @endif
            @if($order->fv_response_payload)
                <details class="rounded border border-gray-200 p-3">
                    <summary class="cursor-pointer font-semibold">FV Response</summary>
                    <pre class="mt-2 overflow-auto">{{ $order->fv_response_payload }}</pre>
                </details>
            @endif
            @if($order->coordinadora_request_payload)
                <details class="rounded border border-gray-200 p-3">
                    <summary class="cursor-pointer font-semibold">Coordinadora Request</summary>
                    <pre class="mt-2 overflow-auto">{{ $order->coordinadora_request_payload }}</pre>
                </details>
            @endif
            @if($order->coordinadora_response_payload)
                <details class="rounded border border-gray-200 p-3">
                    <summary class="cursor-pointer font-semibold">Coordinadora Response</summary>
                    <pre class="mt-2 overflow-auto">{{ $order->coordinadora_response_payload }}</pre>
                </details>
            @endif
        </div>
    @endif
</div>

@if(!request()->filled('code'))
    <div class="flex flex-col">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <div class="overflow-hidden shadow">
                    <table class="min-w-full divide-y divide-gray-200 table-fixed ">
                        <thead class="bg-gray-100">
                            <tr>
                            
                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Producto
                                </th>
                            
                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Combinaciones
                                </th>
                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Precio
                                </th>
                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Cantidad
                                </th>
                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Descuento
                                </th>

                                <th scope="col"
                                    class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                    Total
                                </th>

                        


                            
                            </tr>
                        </thead>
                        <tbody class="bg-white ">
                            @foreach ($order->products as $product)
                                <tr class="hover:bg-gray-100 ">
                                
                                    <td class="p-4 text-sm font-normal text-gray-500 whitespace-nowrap">
                                        <div class='flex flex-col'>
                                            <span>{{ $product->product->name }}</span>
                                            {{-- @if ( $product->product->variation)
                                                <small>
                                                    {{ $product->product->variation->name }}:
                                                    {{ App\Models\VariationItem::find($product->pivot->variation_id)->name }}
                                                </small>
                                            @endif
        --}}
                                        </div>
                                    </td>

                                


                                    <td class="px-4 py-2 text-xs font-normal text-gray-500 whitespace-nowrap">
                                        {{-- @if ($product->combinations->count())
                                            <ul>
                                                @foreach ($product->combinations as $p)
                                                    <li>{{ $p->name }}</li>
                                                @endforeach
                                            </ul>
                                        @endif --}}
                                    </td>

                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                        ${{ number_format($product->price+$product->discount, 2) }}
                                    </td>

                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                    {{$product->quantity}}
                                    </td>


                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                        ${{ number_format($product->discount, 2) }}
                                    </td>


                                    <td class="p-4 text-base font-medium text-gray-900 whitespace-nowra">
                                        ${{ number_format($product->price * $product->quantity, 2) }}
                                    </td>

                                </tr>  
                            @endforeach
                

                            <tr>
                                <td colspan="4">

                                </td>
                                <td class='p-4 text-base font-medium text-gray-900 whitespace-nowrap text-right'>Descuento</td>
                                <td class='p-4 text-base font-bold text-gray-900 whitespace-nowrap text-left'>${{ number_format($order->discount, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4">

                                </td>
                                <td class='p-4 text-base font-medium text-gray-900 whitespace-nowrap text-right'>Total</td>
                                <td class='p-4 text-base font-bold text-gray-900 whitespace-nowrap text-left'>${{ number_format($order->total, 2) }}</td>
                            </tr>


                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    @if($order->bonifications->count())
        <div class="p-4 bg-white block sm:flex items-center justify-between border-b border-gray-200">
            <div class="w-full">
                <div class="">
                    <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl ">Bonificaciones</h1>
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
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                        Producto
                                    </th>
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                        Bonificación
                                    </th>
                                
                                    <th scope="col"
                                        class="p-4 text-xs font-medium text-left text-gray-500 uppercase ">
                                        Cantidad
                                    </th>                        
                                </tr>
                            </thead>
                            <tbody class="bg-white ">
                                @foreach ($order->bonifications as $bonification)
                                    <tr class="hover:bg-gray-100 ">
                                        <td class="px-4 py-2  font-normal text-gray-500 whitespace-nowrap">
                                            {{ $bonification->product->name }}
                                        </td>
                                        <td class="px-4 py-2  font-normal text-gray-500 whitespace-nowrap">
                                            {{ $bonification->bonification->name }}
                                        </td>
                                        <td class="px-4 py-2  font-normal text-gray-500 whitespace-nowrap">
                                            {{ $bonification->quantity }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif


@else


<div class="text-xs">
        <pre>
            <code>
                {{$order->request}}
            </code>
        </pre>

        Response
        <pre>
            <code>
                {{$order->response}}
            </code>
        </pre>
   
   
   
</div>



@endif









@endsection
