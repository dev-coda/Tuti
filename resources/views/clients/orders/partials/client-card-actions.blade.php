@php
    $clientZone = $clientZone ?? null;
    $returnTab = $returnTab ?? 'mi-ruta';
    $returnRoute = $returnRoute ?? null;
    $returnZona = $returnZona ?? null;
    $returnSearch = $returnSearch ?? null;
    $showPreviousOrders = $showPreviousOrders ?? true;
    $suggestedOrderEnabled = $suggestedOrderEnabled ?? false;
@endphp

@if($showPreviousOrders)
    <x-account-record-action
        :href="route('clients.orders.index', array_filter(['tab' => 'orders', 'all' => 1, 'client_id' => $client->id, 'return_ruta' => $returnRoute, 'return_zona' => $returnZona, 'ruta_q' => $returnSearch]))"
        data-client-orders
    >
        Pedidos anteriores
    </x-account-record-action>
@endif

@if($client->document)
    @if($clientZone)
        <x-account-record-action :href="route('client-data-updates.edit', array_filter(['zone' => $clientZone->id, 'return_tab' => $returnTab, 'zona' => $returnZona, 'ruta' => $returnRoute, 'ruta_q' => $returnSearch]))">
            Actualizar datos
        </x-account-record-action>
    @endif
    <x-account-record-action variant="accent" :href="route('new-client.create', ['mode' => 'sucursal', 'document' => $client->document, 'return' => $returnTab])">
        Agregar sucursal
    </x-account-record-action>
    @if($suggestedOrderEnabled)
        <form method="POST" action="{{ route('clients.suggested-order.store', $client) }}" class="w-full sm:w-auto">
            @csrf
            <x-account-record-action variant="accent" data-suggested-order>
                Pedido sugerido
            </x-account-record-action>
        </form>
    @endif
    <form method="POST" action="{{ route('seller.setclient') }}" class="w-full sm:w-auto">
        @csrf
        <input type="hidden" name="document" value="{{ $client->document }}">
        <x-account-record-action variant="primary">
            Crear pedido
        </x-account-record-action>
    </form>
@endif
