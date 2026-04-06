@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupon-tests.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a pruebas</a>
        <h1 class="text-2xl font-bold text-gray-900">
            @if($isMockTest ?? false)
                XML de orden simulada
            @else
                XML de orden #{{ $order->id }}
            @endif
        </h1>
        @if($order->user)
            <p class="text-gray-600 mt-1">Cliente: {{ $order->user->name }} ({{ $order->user->document }})</p>
        @endif
    </div>

    @if($couponResult ?? null)
        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 class="font-semibold text-blue-900">Resultado de cupones aplicados</h3>
            <p class="text-sm text-blue-800">
                @if($couponResult['success'])
                    ✓ Total descuento cupón: ${{ number_format($couponResult['total_coupon_discount'] ?? 0, 2) }}
                @else
                    {{ $couponResult['message'] ?? 'No se aplicaron cupones' }}
                @endif
            </p>
        </div>
    @endif

    @if(!empty($assertions))
        <div class="mb-4 p-4 border rounded-lg {{ collect($assertions)->where('passed', false)->count() === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }}">
            <h3 class="font-semibold {{ collect($assertions)->where('passed', false)->count() === 0 ? 'text-emerald-900' : 'text-amber-900' }}">Validaciones XML</h3>
            <ul class="text-sm mt-2 space-y-1 {{ collect($assertions)->where('passed', false)->count() === 0 ? 'text-emerald-800' : 'text-amber-800' }}">
                @foreach($assertions as $assertion)
                    <li>{{ ($assertion['passed'] ?? false) ? '✓' : '✗' }} {{ $assertion['message'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Productos en la orden</h2>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left">Producto</th>
                    <th class="px-4 py-2 text-right">Cant.</th>
                    <th class="px-4 py-2 text-right">Precio</th>
                    <th class="px-4 py-2 text-center">Tipo desc.</th>
                    <th class="px-4 py-2 text-right">% / Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productSummary as $p)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ $p['name'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $p['quantity'] }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($p['price'], 2) }}</td>
                        <td class="px-4 py-2 text-center">{{ $p['discount_type'] ?? 'percentage' }}</td>
                        <td class="px-4 py-2 text-right">
                            @if(($p['discount_type'] ?? '') === 'fixed_amount')
                                ${{ number_format($p['flat_discount_amount'] ?? 0, 2) }}/unidad
                            @else
                                {{ $p['percentage'] ?? 0 }}%
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">XML generado (SOAP Envelope)</h2>
        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs whitespace-pre-wrap max-h-[600px] overflow-y-auto"><code>{{ $xml }}</code></pre>
    </div>
</div>
@endsection
