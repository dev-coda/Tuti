@props(['order'])

@if($order->origin === \App\Models\Order::ORIGIN_RUTA)
    <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-indigo-100 text-indigo-800' title="Pedido creado por vendedor{{ $order->seller ? ': ' . $order->seller->name : '' }}">
        RUTA
    </span>
@else
    <span class='inline-flex items-center gap-1'>
        <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-teal-100 text-teal-800' title="Pedido creado por el cliente">
            Autónomo
        </span>
        @if($order->autonomousClientSegment() === 'nuevo')
            <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-amber-100 text-amber-800' title="Cliente prospecto o pendiente de validación">
                Cliente nuevo
            </span>
        @elseif($order->autonomousClientSegment() === 'actual')
            <span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium leading-4 bg-emerald-100 text-emerald-800' title="Cliente activo en Dynamics">
                Cliente actual
            </span>
        @endif
    </span>
@endif
