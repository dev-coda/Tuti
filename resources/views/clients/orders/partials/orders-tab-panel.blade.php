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
@endphp

<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6 mb-6">
    <form method="GET" action="{{ route('clients.orders.index') }}" data-orders-form data-tab="{{ $tabKey }}" data-page-param="{{ $pageParam }}">
        <input type="hidden" name="tab" value="{{ $tabKey }}">
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

<div class="space-y-4">
    @forelse ($orders as $order)
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Pedido #{{ $order->id }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ $order->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $order->created_at->subHour(5)->format('d M Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-orange-600">${{ number_format(($order->total + $order->discount) - $order->discount) }}</p>
                        <p class="text-xs text-gray-500">{{ $order->products_sum_quantity ?? 0 }} artículos</p>
                    </div>
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <x-order-status :status="$order->status_id" />
                </div>
                @if($order->shipping_provider === \App\Models\Order::SHIPPING_PROVIDER_COORDINADORA)
                    <div class="mt-2 text-xs text-gray-600">
                        <span class="font-semibold">Coordinadora:</span>
                        {{ $order->coordinadora_status_text ?? 'Pendiente de guía' }}
                        @if($order->coordinadora_guide_number)
                            · Guía {{ $order->coordinadora_guide_number }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex flex-col sm:items-end gap-2">
                <a href="{{ route('clients.orders.show', $order) }}" class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                    Ver detalles
                </a>
                <form action="{{ route('clients.orders.reorder', $order) }}" method="POST">
                    @csrf
                    <button class="text-sm text-gray-600 hover:text-gray-800 font-medium">Volver a pedir</button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
            {{ $emptyMessage }}
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $orders->appends(['tab' => $tabKey])->links() }}
</div>
