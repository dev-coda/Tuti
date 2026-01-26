@extends('layouts.page')


@section('head')

    @include('elements.seo', ['title'=>'Ordenes' ])

@endsection


@section('content')
    
<section class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="py-6 sm:py-8">
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">Mi Cuenta</h1>
            <p class="text-sm text-gray-500 mt-1">Gestiona tu información personal y pedidos</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row">
                <button type="button" data-tab-trigger="orders"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-orange-600 bg-orange-50 border-b-2 border-orange-500">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        Pedidos Recientes
                    </div>
                </button>
                <button type="button" data-tab-trigger="account"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Información de Cuenta
                    </div>
                </button>
                <button type="button" data-tab-trigger="addresses"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8.25 8.25 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Direcciones
                    </div>
                </button>
            </div>
        </div>

        <div class="mt-6">
            <div data-tab-panel="orders">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6 mb-6">
                    <form method="GET" action="{{ route('clients.orders.index') }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Buscar cliente</label>
                                <input type="text" name="q" id="orders-filter-q" value="{{ request('q') }}" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Nombre del cliente...">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">ID de orden</label>
                                <input type="text" name="order_id" id="orders-filter-id" value="{{ request('order_id') }}" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Ej: 1024">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                                <input type="date" name="from_date" value="{{ request('from_date') }}" class="w-full border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                                <input type="date" name="to_date" value="{{ request('to_date') }}" class="w-full border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Filtrar</label>
                                <select name="status_id" class="w-full border-gray-300 rounded-lg text-sm">
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected((string)request('status_id','') === (string)$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="space-y-4">
                    @forelse ($orders as $order)
                        @php
                            $firstProduct = $order->products->first();
                            $firstImage = $firstProduct?->product?->images?->first();
                        @endphp
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                            <div class="w-16 h-16 rounded-xl bg-gray-100 flex items-center justify-center overflow-hidden">
                                @if($firstImage)
                                    <img src="{{ asset('storage/'.$firstImage->path) }}" alt="Producto" class="w-full h-full object-contain">
                                @else
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2" />
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">Pedido #{{ $order->id }}</p>
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
                            No tienes pedidos recientes.
                        </div>
                    @endforelse
                </div>

                <div class="mt-6">
                    {{ $orders->links() }}
                </div>
            </div>

            <div data-tab-panel="account" class="hidden">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-sm text-gray-500">
                    Sección en construcción.
                </div>
            </div>

            <div data-tab-panel="addresses" class="hidden">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-sm text-gray-500">
                    Sección en construcción.
                </div>
            </div>
        </div>
    </div>
</section>


@endsection


@section('scripts')
<script>
    (function(){
        const tabTriggers = document.querySelectorAll('[data-tab-trigger]');
        const tabPanels = document.querySelectorAll('[data-tab-panel]');

        function activateTab(tabKey) {
            tabPanels.forEach(panel => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== tabKey);
            });
            tabTriggers.forEach(trigger => {
                const isActive = trigger.dataset.tabTrigger === tabKey;
                trigger.classList.toggle('text-orange-600', isActive);
                trigger.classList.toggle('bg-orange-50', isActive);
                trigger.classList.toggle('border-orange-500', isActive);
                trigger.classList.toggle('border-b-2', isActive);
                trigger.classList.toggle('text-gray-700', !isActive);
            });
        }

        tabTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => activateTab(trigger.dataset.tabTrigger));
        });

        activateTab('orders');

        const input = document.getElementById('orders-filter-q');
        const idInput = document.getElementById('orders-filter-id');
        if(!input) return;
        let t;
        input.addEventListener('input', function(){
            clearTimeout(t);
            t = setTimeout(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('q', input.value || '');
                window.location = `${window.location.pathname}?${params.toString()}`;
            }, 350);
        });

        if (idInput) {
            let ti;
            idInput.addEventListener('input', function(){
                clearTimeout(ti);
                ti = setTimeout(() => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('order_id', idInput.value || '');
                    window.location = `${window.location.pathname}?${params.toString()}`;
                }, 350);
            });
        }

        // submit form on date/status change
        document.querySelectorAll("input[name='from_date'], input[name='to_date'], select[name='status_id']").forEach(el => {
            el.addEventListener('change', function(){
                this.form.submit();
            });
        });
    })();
</script>
@endsection
