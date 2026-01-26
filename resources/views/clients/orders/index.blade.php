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
                @php
                    $accountUser = $accountUser ?? auth()->user();
                    $isSeller = $accountUser?->hasRole('seller');
                    $roleLabel = $isSeller ? 'Vendedor' : 'Cliente';
                @endphp
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">Información Personal</h2>
                    <p class="text-sm text-gray-500 mb-4">
                        {{ $isSeller ? 'Información del vendedor' : 'Información del cliente' }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Rol</label>
                            <input type="text" value="{{ $roleLabel }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                            <input type="text" value="{{ $accountUser->name }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Razón Social</label>
                            <input type="text" value="{{ $accountUser->business_name ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Correo Electrónico</label>
                            <input type="text" value="{{ $accountUser->email }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Documento</label>
                            <input type="text" value="{{ $accountUser->document ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                            <input type="text" value="{{ $accountUser->phone ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Celular</label>
                            <input type="text" value="{{ $accountUser->mobile_phone ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">WhatsApp</label>
                            <input type="text" value="{{ $accountUser->whatsapp ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ciudad</label>
                            <input type="text" value="{{ $accountUser->city?->name ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Código Ciudad</label>
                            <input type="text" value="{{ $accountUser->city_code ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Zona</label>
                            <input type="text" value="{{ $accountUser->zone ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 mt-8 mb-4">Información Comercial</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de Cliente</label>
                            <input type="text" value="{{ $accountUser->customer_type ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Grupo de Precios</label>
                            <input type="text" value="{{ $accountUser->price_group ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Grupo de Impuestos</label>
                            <input type="text" value="{{ $accountUser->tax_group ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Descuento</label>
                            <input type="text" value="{{ $accountUser->line_discount ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Saldo</label>
                            <input type="text" value="{{ $accountUser->balance ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cupo</label>
                            <input type="text" value="{{ $accountUser->quota_value ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Estado del Cliente</label>
                            <input type="text" value="{{ $accountUser->customer_status ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cuenta</label>
                            <input type="text" value="{{ $accountUser->account_num ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Bloqueado</label>
                            <input type="text" value="{{ $accountUser->is_locked ? 'Sí' : 'No' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Secuencia de Orden</label>
                            <input type="text" value="{{ $accountUser->order_sequence ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 mt-8 mb-4">Metadatos</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">ID Usuario</label>
                            <input type="text" value="{{ $accountUser->id }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                            <input type="text" value="{{ $accountUser->status_id ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Términos Aceptados</label>
                            <input type="text" value="{{ $accountUser->terms_accepted ? 'Sí' : 'No' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Email Verificado</label>
                            <input type="text" value="{{ $accountUser->email_verified_at ? $accountUser->email_verified_at->format('d/m/Y H:i') : '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Creado</label>
                            <input type="text" value="{{ $accountUser->created_at?->format('d/m/Y H:i') ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Actualizado</label>
                            <input type="text" value="{{ $accountUser->updated_at?->format('d/m/Y H:i') ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 mt-8 mb-4">Zonas Asociadas</h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-600">
                        @if($accountUser->zones && $accountUser->zones->count())
                            <ul class="space-y-2">
                                @foreach($accountUser->zones as $zone)
                                    <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                        <span class="font-medium text-gray-800">{{ $zone->address ?? 'Dirección no disponible' }}</span>
                                        <span class="text-xs text-gray-500">Zona {{ $zone->zone ?? '-' }} · Ruta {{ $zone->route ?? '-' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <span>No hay zonas registradas.</span>
                        @endif
                    </div>
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
