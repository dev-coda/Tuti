@extends('layouts.page')


@section('head')

    @include('elements.seo', ['title'=>'Ordenes' ])

@endsection


@section('content')
    @php
        $sellerDashToday = $sellerDashToday ?? \Carbon\Carbon::now(config('app.seller_dashboard_timezone', 'America/Bogota'))->format('Y-m-d');
    @endphp

<section class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="py-6 sm:py-8">
        <div class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">Mi Cuenta</h1>
            <p class="text-sm text-gray-500 mt-1">Gestiona tu información personal y pedidos</p>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- ── Seller Mini Dashboard ─────────────────────────── --}}
        @if(!empty($isSeller))
        <div id="seller-dashboard" class="mb-8">
            {{-- Date Range Picker --}}
            <div class="flex flex-col gap-3 mb-4">
                <span class="text-sm font-medium text-gray-600">Filtrar por fecha</span>
                <div class="flex flex-col sm:flex-row sm:items-end gap-3 w-full">
                    <div class="flex-1 min-w-0">
                        <label for="dash-from" class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                        <input type="date" id="dash-from" autocomplete="off"
                               max="{{ $sellerDashToday }}"
                               class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500 touch-manipulation" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <label for="dash-to" class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                        <input type="date" id="dash-to" autocomplete="off"
                               max="{{ $sellerDashToday }}"
                               class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500 touch-manipulation" />
                    </div>
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
            <div id="category-cards" class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                {{-- Filled dynamically via JS --}}
            </div>
        </div>
        @endif
        {{-- ── / Seller Mini Dashboard ───────────────────────── --}}

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-col sm:flex-row">
                @if(!empty($isSeller))
                <button type="button" data-tab-trigger="orders-today"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-orange-600 bg-orange-50 border-b-2 border-orange-500">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-12 9h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z" />
                        </svg>
                        Pedidos del dia
                    </div>
                </button>
                <button type="button" data-tab-trigger="mi-ruta"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Mi Ruta
                    </div>
                </button>
                @endif
                <button type="button" data-tab-trigger="orders"
                        class="flex-1 px-4 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 border-b border-gray-200">
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
            @if(!empty($isSeller))
            <div data-tab-panel="orders-today">
                @include('clients.orders.partials.orders-tab-panel', [
                    'tabKey' => 'orders-today',
                    'pageParam' => 'today_page',
                    'showFilters' => false,
                    'orders' => $dailyOrders,
                    'statuses' => $statuses,
                    'sellerDashToday' => $sellerDashToday,
                    'filters' => $todayFilters,
                    'queryKeys' => [
                        'q' => 'today_q',
                        'order_id' => 'today_order_id',
                        'from_date' => 'today_from_date',
                        'to_date' => 'today_to_date',
                        'status_id' => 'today_status_id',
                    ],
                    'emptyMessage' => 'No tienes pedidos para el rango seleccionado.',
                ])
            </div>

            <div data-tab-panel="mi-ruta" class="hidden">
                @include('clients.orders.partials.my-route-panel', ['myRoute' => $myRoute])
            </div>
            @endif

            <div data-tab-panel="orders" @if(!empty($isSeller)) class="hidden" @endif>
                @include('clients.orders.partials.orders-tab-panel', [
                    'tabKey' => 'orders',
                    'pageParam' => 'page',
                    'orders' => $orders,
                    'statuses' => $statuses,
                    'sellerDashToday' => $sellerDashToday,
                    'filters' => $recentFilters,
                    'queryKeys' => [
                        'q' => 'q',
                        'order_id' => 'order_id',
                        'from_date' => 'from_date',
                        'to_date' => 'to_date',
                        'status_id' => 'status_id',
                    ],
                    'emptyMessage' => 'No tienes pedidos recientes.',
                ])
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
                                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 text-sm text-gray-600">
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
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase">ZIP</p>
                                            <p class="font-semibold text-gray-800">{{ $zone->zip_code ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase">48H</p>
                                            <p class="font-semibold text-gray-800">{{ $zone->fulfillment_provider_48h ?? 'coordinadora' }}</p>
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

            const params = new URLSearchParams(window.location.search);
            params.set('tab', tabKey);
            window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);
        }

        tabTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => activateTab(trigger.dataset.tabTrigger));
        });

        // Check for tab query parameter, otherwise default to first seller/orders tab
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        const availableTabs = Array.from(tabTriggers).map(trigger => trigger.dataset.tabTrigger);
        const fallbackTab = availableTabs.includes('orders-today') ? 'orders-today' : 'orders';
        const initialTab = tabParam && availableTabs.includes(tabParam) ? tabParam : fallbackTab;
        activateTab(initialTab);

        /* ── Order filter debounce ─────────────────────────── */
        document.querySelectorAll('[data-orders-filter]').forEach(input => {
            let debounceTimer;
            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const form = input.closest('form[data-orders-form]');
                    const params = new URLSearchParams(window.location.search);
                    const fieldName = input.name;
                    const pageParam = form?.dataset.pageParam || 'page';

                    params.set(fieldName, input.value || '');
                    params.set('tab', form?.dataset.tab || 'orders');
                    params.delete(pageParam);

                    window.location = `${window.location.pathname}?${params.toString()}`;
                }, 350);
            });
        });

        // Submit each orders form on date/status change and keep active tab
        document.querySelectorAll('[data-orders-autosubmit]').forEach(el => {
            el.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Prevent repeated export clicks and show feedback.
        document.querySelectorAll('[data-orders-export]').forEach(link => {
            link.addEventListener('click', function (event) {
                if (link.dataset.downloading === '1') {
                    event.preventDefault();
                    return;
                }

                link.dataset.downloading = '1';
                link.dataset.originalText = link.textContent.trim();
                link.textContent = 'Descargando...';
                link.classList.add('opacity-70', 'cursor-not-allowed');

                // Re-enable after a grace period in case browser blocks/aborts download.
                setTimeout(() => {
                    link.dataset.downloading = '0';
                    link.textContent = link.dataset.originalText || 'Descargar Excel';
                    link.classList.remove('opacity-70', 'cursor-not-allowed');
                }, 15000);
            });
        });

        /* ── Seller Mini Dashboard ─────────────────────────── */
        @if(!empty($isSeller))
        (function(){
            const DASH_URL   = @json(route('api.seller.dashboard'));
            const SERVER_TODAY = @json($sellerDashToday);
            const fromInput  = document.getElementById('dash-from');
            const toInput    = document.getElementById('dash-to');
            const kpiPedidos = document.getElementById('kpi-pedidos');
            const kpiVentas  = document.getElementById('kpi-ventas');
            const kpiTicket  = document.getElementById('kpi-ticket');
            const catCards   = document.getElementById('category-cards');

            function normalizeDateOrder() {
                if (!fromInput.value || !toInput.value) return;
                if (fromInput.value > toInput.value) {
                    const t = fromInput.value;
                    fromInput.value = toInput.value;
                    toInput.value = t;
                }
            }

            // Default = server calendar day (Colombia), avoids device clock / timezone drift
            fromInput.value = SERVER_TODAY;
            toInput.value = SERVER_TODAY;

            function currency(n) {
                return '$' + Number(n).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            }

            function renderKPIs(data) {
                kpiPedidos.textContent = data.total_pedidos;
                kpiVentas.textContent  = currency(data.ventas_totales);
                kpiTicket.textContent  = currency(data.ticket_promedio);
            }

            function renderBuckets(buckets) {
                catCards.innerHTML = '';
                (buckets || []).forEach(function(b) {
                    const qty = b.quantity != null ? Number(b.quantity).toLocaleString('es-CO') : '0';
                    const card = document.createElement('div');
                    card.className = 'bg-white border border-gray-200 rounded-2xl shadow-sm p-4 flex flex-col items-center justify-center text-center';
                    card.innerHTML =
                        '<p class="text-xs font-medium text-gray-500 uppercase tracking-wide leading-tight mb-1">' + b.label + '</p>' +
                        '<p class="text-lg font-bold text-gray-900">' + currency(b.total) + '</p>' +
                        '<p class="text-xs text-gray-500 mt-0.5">' + qty + ' uds</p>';
                    catCards.appendChild(card);
                });
            }

            function fetchDashboard() {
                normalizeDateOrder();
                const params = new URLSearchParams();
                if (fromInput.value) params.set('from_date', fromInput.value);
                if (toInput.value) params.set('to_date', toInput.value);

                fetch(DASH_URL + '?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.from_date) fromInput.value = data.from_date;
                    if (data.to_date) toInput.value = data.to_date;
                    renderKPIs(data);
                    renderBuckets(data.sales_buckets);
                })
                .catch(function(err) {
                    console.error('Dashboard fetch error', err);
                });
            }

            function onDashDateAdjust() {
                normalizeDateOrder();
                fetchDashboard();
            }

            fromInput.addEventListener('change', onDashDateAdjust);
            toInput.addEventListener('change', onDashDateAdjust);
            fromInput.addEventListener('input', function() { normalizeDateOrder(); });
            toInput.addEventListener('input', function() { normalizeDateOrder(); });

            fetchDashboard();
        })();
        @endif
    })();
</script>
@endsection
