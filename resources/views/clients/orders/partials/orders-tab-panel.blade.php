@php
    $tabKey = $tabKey ?? 'orders';
    $pageParam = $pageParam ?? 'page';
    $queryKeys = $queryKeys ?? [
        'q' => 'q',
        'order_id' => 'order_id',
        'from_date' => 'from_date',
        'to_date' => 'to_date',
        'status_id' => 'status_id',
    ];
    $filters = $filters ?? [];
    $emptyMessage = $emptyMessage ?? 'No tienes pedidos.';
    $showFilters = $showFilters ?? true;
    $showOrigin = $showOrigin ?? false;
    $exportParams = request()->query();
    unset($exportParams['page'], $exportParams['today_page'], $exportParams[$pageParam]);
    $exportParams['tab'] = $tabKey;
    $exportUrl = route('clients.orders.export', $exportParams);
    $suggestedOrderEnabled = false;
@endphp

@if(!empty($clientFilter))
    <div class="mb-4 bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" data-client-orders-banner>
        <div>
            <p class="text-sm font-semibold text-gray-900">Pedidos anteriores</p>
            <p class="text-sm text-gray-600 mt-0.5">{{ $clientFilter->business_name ?: $clientFilter->name }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="{{ route('clients.orders.index', array_filter(['tab' => 'mi-ruta', 'ruta' => request('return_ruta'), 'zona' => request('return_zona'), 'ruta_q' => request('ruta_q')])) }}"
               class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                Volver a Mi Ruta
            </a>
            <a href="{{ route('clients.orders.index', ['tab' => 'orders']) }}"
               class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                Ver todos los pedidos
            </a>
        </div>
    </div>
@endif

@php
    $showOrderRangeToggle = auth()->user()?->isStaffSeller() && empty($clientFilter) && ($tabKey ?? '') === 'orders';
    $isAllOrders = request()->boolean('all');
    $isTodayOrders = $showOrderRangeToggle
        && ! $isAllOrders
        && ($filters['from_date'] ?? '') === ($sellerDashToday ?? '')
        && ($filters['to_date'] ?? '') === ($sellerDashToday ?? '');
@endphp
@if($showOrderRangeToggle)
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('clients.orders.index', ['tab' => 'orders']) }}"
           class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $isTodayOrders ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Hoy
        </a>
        <a href="{{ route('clients.orders.index', ['tab' => 'orders', 'all' => 1]) }}"
           class="px-3 py-1.5 text-xs font-medium rounded-lg border {{ $isAllOrders ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            Todos
        </a>
    </div>
@endif

<div class="flex justify-end mb-4">
    <a href="{{ $exportUrl }}" data-orders-export class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
        Descargar Excel
    </a>
</div>

@if($showFilters)
<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6 mb-6">
    <form method="GET" action="{{ route('clients.orders.index') }}" data-orders-form data-tab="{{ $tabKey }}" data-page-param="{{ $pageParam }}">
        <input type="hidden" name="tab" value="{{ $tabKey }}">
        @if(request()->boolean('all'))
            <input type="hidden" name="all" value="1">
        @endif
        @if(!empty($clientFilter))
            <input type="hidden" name="client_id" value="{{ $clientFilter->id }}">
            @if(request('return_ruta'))
                <input type="hidden" name="return_ruta" value="{{ request('return_ruta') }}">
            @endif
            @if(request('return_zona'))
                <input type="hidden" name="return_zona" value="{{ request('return_zona') }}">
            @endif
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar cliente</label>
                <input
                    type="text"
                    name="{{ $queryKeys['q'] }}"
                    data-orders-filter="q"
                    value="{{ $filters['q'] ?? '' }}"
                    class="w-full border-gray-300 rounded-lg text-sm"
                    placeholder="Nombre del cliente..."
                >
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">ID de orden</label>
                <input
                    type="text"
                    name="{{ $queryKeys['order_id'] }}"
                    data-orders-filter="order_id"
                    value="{{ $filters['order_id'] ?? '' }}"
                    class="w-full border-gray-300 rounded-lg text-sm"
                    placeholder="Ej: 1024"
                >
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                <input
                    type="date"
                    name="{{ $queryKeys['from_date'] }}"
                    data-orders-autosubmit
                    value="{{ $filters['from_date'] ?? '' }}"
                    max="{{ $sellerDashToday }}"
                    autocomplete="off"
                    class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm touch-manipulation"
                >
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                <input
                    type="date"
                    name="{{ $queryKeys['to_date'] }}"
                    data-orders-autosubmit
                    value="{{ $filters['to_date'] ?? '' }}"
                    max="{{ $sellerDashToday }}"
                    autocomplete="off"
                    class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm touch-manipulation"
                >
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Filtrar</label>
                <select name="{{ $queryKeys['status_id'] }}" data-orders-autosubmit class="w-full border-gray-300 rounded-lg text-sm">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected((string)($filters['status_id'] ?? '') === (string)$value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>
</div>
@endif

<div class="space-y-4">
    @forelse ($orders as $order)
        @php
            $client = $order->user;
            $clientZone = $order->zone;
            $zoneSnapshot = is_array($order->zone_snapshot) ? $order->zone_snapshot : [];
            $showClientTools = auth()->user()?->isStaffSeller() && $client;
            $clientSubtitle = $client
                ? (($client->business_name ?: $client->name).' · '.$order->created_at->subHour(5)->format('d M Y'))
                : $order->created_at->subHour(5)->format('d M Y');
        @endphp
        <x-account-record-card :title="'Pedido #'.$order->id" :subtitle="$clientSubtitle">
            <x-slot:badge>
                <div class="flex flex-wrap items-center gap-2">
                    <x-order-status :status="$order->status_id" />
                    @if($showOrigin)
                        <x-order-origin :order="$order" />
                    @endif
                </div>
            </x-slot:badge>
            <x-slot:body>
                <x-account-record-field label="Total">
                    <span class="text-orange-600">${{ number_format($order->totalWithTax()) }}</span>
                </x-account-record-field>
                <x-account-record-field label="Artículos">{{ $order->products_sum_quantity ?? 0 }}</x-account-record-field>
                @if($showClientTools)
                    @include('clients.orders.partials.client-card-fields', [
                        'client' => $client,
                        'clientZone' => $clientZone,
                        'addressFallback' => $zoneSnapshot['address'] ?? null,
                    ])
                @endif
            </x-slot:body>
            @if($order->shipping_provider === \App\Models\Order::SHIPPING_PROVIDER_COORDINADORA)
                <x-slot:extra>
                    <div class="mt-4 text-xs text-gray-600">
                        <span class="font-semibold">Coordinadora:</span>
                        {{ $order->coordinadora_status_text ?? 'Pendiente de guía' }}
                        @if($order->coordinadora_guide_number)
                            · Guía {{ $order->coordinadora_guide_number }}
                        @endif
                    </div>
                </x-slot:extra>
            @endif
            <x-slot:actions>
                @if($showClientTools)
                    @include('clients.orders.partials.client-card-actions', [
                        'client' => $client,
                        'clientZone' => $clientZone,
                        'returnTab' => $tabKey,
                        'returnRoute' => request('return_ruta') ?: request('ruta') ?: ($zoneSnapshot['route'] ?? null),
                        'returnZona' => request('return_zona') ?: request('zona') ?: ($zoneSnapshot['zone'] ?? null),
                        'returnSearch' => request('ruta_q') ?: null,
                        'showPreviousOrders' => empty($clientFilter) || (int) $clientFilter->id !== (int) $client->id,
                        'suggestedOrderEnabled' => $suggestedOrderEnabled,
                    ])
                @endif
                <x-account-record-action :href="route('clients.orders.show', $order)" :variant="$showClientTools ? 'secondary' : 'primary'">
                    Ver detalles
                </x-account-record-action>
                <form action="{{ route('clients.orders.reorder', $order) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <x-account-record-action>Volver a pedir</x-account-record-action>
                </form>
            </x-slot:actions>
        </x-account-record-card>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
            {{ $emptyMessage }}
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $orders->appends(['tab' => $tabKey])->links() }}
</div>
