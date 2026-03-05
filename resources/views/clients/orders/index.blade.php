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

        {{-- ── Seller Mini Dashboard ─────────────────────────── --}}
        @if(!empty($isSeller))
        <div id="seller-dashboard" class="mb-8">
            {{-- Date Range Picker --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
                <span class="text-sm font-medium text-gray-600">Filtrar por fecha:</span>
                <div class="flex items-center gap-2">
                    <input type="date" id="dash-from" class="border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500" />
                    <span class="text-gray-400">—</span>
                    <input type="date" id="dash-to" class="border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500" />
                </div>
            </div>

            {{-- Row 1: KPI Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Pedidos</p>
                    <p id="kpi-pedidos" class="mt-1 text-2xl font-bold text-gray-900">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ventas Totales</p>
                    <p id="kpi-ventas" class="mt-1 text-2xl font-bold text-orange-600">—</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ticket Promedio</p>
                    <p id="kpi-ticket" class="mt-1 text-2xl font-bold text-gray-900">—</p>
                </div>
            </div>

            {{-- Row 2: Category Sales Cards --}}
            <div id="category-cards" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                {{-- Filled dynamically via JS --}}
            </div>
        </div>
        @endif
        {{-- ── / Seller Mini Dashboard ───────────────────────── --}}

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
                    $fullName = trim((string) $accountUser->name);
                    $nameParts = preg_split('/\s+/', $fullName, 2);
                    $firstName = $nameParts[0] ?? $fullName;
                    $lastName = $nameParts[1] ?? '';
                @endphp
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Información Personal</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                            <input type="text" value="{{ $firstName }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Apellido</label>
                            <input type="text" value="{{ $lastName ?: '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Correo Electrónico</label>
                            <input type="text" value="{{ $accountUser->email }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Teléfono</label>
                            <input type="text" value="{{ $accountUser->mobile_phone ?? $accountUser->phone ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Documento de Identidad</label>
                            <input type="text" value="{{ $accountUser->document ?? '-' }}" readonly class="w-full border-gray-200 rounded-lg text-sm bg-gray-50">
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6 mt-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Cambiar Contraseña</h2>
                    <form method="POST" action="{{ route('password.update') }}?tab=account" class="space-y-4">
                        @csrf
                        @method('put')

                        <div>
                            <label for="current_password" class="block text-xs font-medium text-gray-500 mb-1">Contraseña Actual</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required 
                                   autocomplete="current-password"
                                   class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500 @error('current_password', 'updatePassword') border-red-300 @enderror">
                            @error('current_password', 'updatePassword')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-medium text-gray-500 mb-1">Nueva Contraseña</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   autocomplete="new-password"
                                   class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500 @error('password', 'updatePassword') border-red-300 @enderror">
                            @error('password', 'updatePassword')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-gray-500 mb-1">Confirmar Nueva Contraseña</label>
                            <input type="password" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   required 
                                   autocomplete="new-password"
                                   class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500 @error('password_confirmation', 'updatePassword') border-red-300 @enderror">
                            @error('password_confirmation', 'updatePassword')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <button type="submit" 
                                    class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-colors">
                                Guardar Contraseña
                            </button>
                            @if (session('status') === 'password-updated')
                                <p class="text-sm text-green-600 font-medium">Contraseña actualizada correctamente.</p>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div data-tab-panel="addresses" class="hidden">
                <div class="space-y-6">
                    @if($accountUser->zones && $accountUser->zones->count())
                        @foreach($accountUser->zones as $zone)
                            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 sm:p-6">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8.25 8.25 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-sm font-semibold text-gray-900">
                                                {{ $zone->address ?? 'Dirección' }}
                                            </h3>
                                            @if($zone->id === $accountUser->zones->first()->id)
                                                <span class="text-xs font-medium text-orange-600 bg-orange-100 rounded-full px-2 py-0.5">Predeterminada</span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1">{{ $accountUser->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $zone->address ?? '-' }}</p>
                                        <p class="text-sm text-gray-600">{{ $accountUser->city?->name ?? '-' }}</p>
                                        <p class="text-sm text-gray-600">{{ $accountUser->phone ?? $accountUser->mobile_phone ?? '-' }}</p>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 mt-4 pt-4">
                                    <div class="flex items-center gap-2 text-sm text-gray-700 font-semibold mb-3">
                                        <span class="w-6 h-6 rounded-full bg-orange-50 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 0a8 8 0 11-16 0 8 8 0 0116 0z" />
                                            </svg>
                                        </span>
                                        Información de Rutero
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-gray-600">
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase">Zona</p>
                                            <p class="font-semibold text-gray-800">{{ $zone->zone ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase">Ruta</p>
                                            <p class="font-semibold text-gray-800">{{ $zone->route ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase">Rutero</p>
                                            <p class="font-semibold text-gray-800">{{ $zone->code ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
                            No hay direcciones registradas.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>


@endsection


@section('scripts')
<script>
    (function(){
        /* ── Tab switching ─────────────────────────────────── */
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

        // Check for tab query parameter, otherwise default to 'orders'
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        const initialTab = tabParam && ['orders', 'account', 'addresses'].includes(tabParam) ? tabParam : 'orders';
        activateTab(initialTab);

        /* ── Order filter debounce ─────────────────────────── */
        const input = document.getElementById('orders-filter-q');
        const idInput = document.getElementById('orders-filter-id');
        if(input) {
            let t;
            input.addEventListener('input', function(){
                clearTimeout(t);
                t = setTimeout(() => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('q', input.value || '');
                    window.location = `${window.location.pathname}?${params.toString()}`;
                }, 350);
            });
        }

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

        /* ── Seller Mini Dashboard ─────────────────────────── */
        @if(!empty($isSeller))
        (function(){
            const DASH_URL   = @json(route('api.seller.dashboard'));
            const fromInput  = document.getElementById('dash-from');
            const toInput    = document.getElementById('dash-to');
            const kpiPedidos = document.getElementById('kpi-pedidos');
            const kpiVentas  = document.getElementById('kpi-ventas');
            const kpiTicket  = document.getElementById('kpi-ticket');
            const catCards   = document.getElementById('category-cards');

            function todayStr() {
                const d = new Date();
                return d.getFullYear() + '-' +
                    String(d.getMonth()+1).padStart(2,'0') + '-' +
                    String(d.getDate()).padStart(2,'0');
            }

            // Default dates = today
            fromInput.value = todayStr();
            toInput.value   = todayStr();

            function currency(n) {
                return '$' + Number(n).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            }

            function renderKPIs(data) {
                kpiPedidos.textContent = data.total_pedidos;
                kpiVentas.textContent  = currency(data.ventas_totales);
                kpiTicket.textContent  = currency(data.ticket_promedio);
            }

            function renderCategories(categories) {
                catCards.innerHTML = '';
                (categories || []).forEach(function(cat) {
                    const card = document.createElement('div');
                    card.className = 'bg-white border border-gray-200 rounded-2xl shadow-sm p-4 flex flex-col items-center justify-center text-center';
                    card.innerHTML =
                        '<p class="text-xs font-medium text-gray-500 uppercase tracking-wide leading-tight mb-1">Ventas ' + cat.name + '</p>' +
                        '<p class="text-lg font-bold text-gray-900">' + currency(cat.total) + '</p>';
                    catCards.appendChild(card);
                });
            }

            function fetchDashboard() {
                const params = new URLSearchParams();
                if (fromInput.value) params.set('from_date', fromInput.value);
                if (toInput.value)   params.set('to_date', toInput.value);

                fetch(DASH_URL + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    renderKPIs(data);
                    renderCategories(data.category_sales);
                })
                .catch(function(err) {
                    console.error('Dashboard fetch error', err);
                });
            }

            fromInput.addEventListener('change', fetchDashboard);
            toInput.addEventListener('change', fetchDashboard);

            // Initial load
            fetchDashboard();
        })();
        @endif
    })();
</script>
@endsection
