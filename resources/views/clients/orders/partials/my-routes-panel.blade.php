@php
    $myRoutes = $myRoutes ?? [];
    $assignments = $myRoutes['assignments'] ?? collect();
    $selected = $myRoutes['selected'] ?? null;
    $routeFilters = $myRoutes['filters'] ?? [];
    $routeOrders = $myRoutes['orders'] ?? null;
@endphp

<div class="space-y-6">
    @if($assignments->isEmpty())
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
            No tienes rutas asignadas. Contacta al administrador para configurar tus rutas.
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6">
            <form method="GET" action="{{ route('clients.orders.index') }}"
                  data-orders-form data-tab="mis-rutas" data-page-param="sr_page">
                <input type="hidden" name="tab" value="mis-rutas">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label for="mis-rutas-select" class="block text-xs font-medium text-gray-500 mb-1">Ruta</label>
                        <select id="mis-rutas-select" name="sr" data-orders-autosubmit
                                class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Selecciona una ruta</option>
                            @foreach($assignments as $assignment)
                                <option value="{{ $assignment->id }}" @selected($selected && $selected->id === $assignment->id)>
                                    {{ $assignment->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                        <input type="date" name="sr_from_date" data-orders-autosubmit
                               value="{{ $routeFilters['from_date'] ?? '' }}"
                               max="{{ $sellerDashToday }}" autocomplete="off"
                               class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm touch-manipulation">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                        <input type="date" name="sr_to_date" data-orders-autosubmit
                               value="{{ $routeFilters['to_date'] ?? '' }}"
                               max="{{ $sellerDashToday }}" autocomplete="off"
                               class="w-full min-h-[44px] border-gray-300 rounded-lg text-base sm:text-sm touch-manipulation">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                        <select name="sr_status_id" data-orders-autosubmit class="w-full border-gray-300 rounded-lg text-sm">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected((string)($routeFilters['status_id'] ?? '') === (string)$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Buscar cliente</label>
                        <input type="text" name="sr_q" data-orders-filter="q"
                               value="{{ $routeFilters['q'] ?? '' }}"
                               placeholder="Nombre del cliente..."
                               class="w-full border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
            </form>
        </div>

        @if($selected && $routeOrders)
            <div class="flex items-center justify-between px-1">
                <h2 class="text-sm font-semibold text-gray-900">Pedidos de la {{ $selected->label() }}</h2>
                <span class="text-xs font-medium text-gray-500">
                    {{ $routeOrders->total() }} {{ $routeOrders->total() === 1 ? 'pedido' : 'pedidos' }}
                </span>
            </div>

            @include('clients.orders.partials.orders-tab-panel', [
                'tabKey' => 'mis-rutas',
                'pageParam' => 'sr_page',
                'showFilters' => false,
                'showOrigin' => true,
                'orders' => $routeOrders,
                'statuses' => $statuses,
                'sellerDashToday' => $sellerDashToday,
                'filters' => $routeFilters,
                'emptyMessage' => 'No hay pedidos en esta ruta para el rango seleccionado.',
            ])
        @endif
    @endif
</div>
