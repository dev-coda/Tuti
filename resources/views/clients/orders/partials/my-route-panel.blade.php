@php
    $myRoute = $myRoute ?? [];
    $sellerZone = $myRoute['sellerZone'] ?? '';
    $routeOptions = $myRoute['routeOptions'] ?? collect();
    $selectedRoute = $myRoute['selectedRoute'] ?? '';
    $searchTerm = $myRoute['searchTerm'] ?? '';
    $routeClients = $myRoute['clients'] ?? null;
    $todayLabel = $myRoute['todayLabel'] ?? '';
    $deliveryLabel = $myRoute['deliveryLabel'] ?? '';
    $coversAllZones = $myRoute['coversAllZones'] ?? false;
    $zoneOptions = $myRoute['zoneOptions'] ?? collect();
@endphp

<div class="space-y-6">
    @if($coversAllZones)
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6">
            <form method="GET" action="{{ route('clients.orders.index') }}">
                <input type="hidden" name="tab" value="mi-ruta">
                <label class="block text-xs font-medium text-gray-500 mb-1">Zona</label>
                <select name="zona" data-orders-autosubmit class="w-full border-gray-300 rounded-lg text-sm px-3 py-2">
                    <option value="">Selecciona una zona</option>
                    @foreach($zoneOptions as $zoneOption)
                        <option value="{{ $zoneOption }}" @selected((string) $zoneOption === $sellerZone)>{{ $zoneOption }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif
    @if($sellerZone === '')
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
            No tienes una zona asignada. Contacta al administrador para configurar tu zona.
        </div>
    @elseif($routeOptions->isEmpty())
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
            No encontramos rutas para tu zona {{ $sellerZone }}.
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6">
            <form method="GET" action="{{ route('clients.orders.index') }}">
                <input type="hidden" name="tab" value="mi-ruta">
                @if($coversAllZones)
                    <input type="hidden" name="zona" value="{{ $sellerZone }}">
                @endif
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div class="flex-1 min-w-0">
                        <label for="mi-ruta-select" class="block text-xs font-medium text-gray-500 mb-1">Ruta (zona {{ $sellerZone }})</label>
                        <select id="mi-ruta-select" name="ruta" data-orders-autosubmit
                                class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Selecciona una ruta</option>
                            @foreach($routeOptions as $routeOption)
                                <option value="{{ $routeOption }}" @selected((string) $routeOption === $selectedRoute)>{{ $routeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-sm text-gray-500 sm:pb-2 whitespace-nowrap">
                        Hoy: <span class="font-medium text-gray-700 capitalize">{{ $todayLabel }}</span>
                        @if($deliveryLabel !== '')
                            <span class="mx-1">·</span>
                            Entrega: <span class="font-medium text-gray-700">{{ $deliveryLabel }}</span>
                        @endif
                    </p>
                </div>
                @if($selectedRoute !== '')
                    <div class="flex flex-col sm:flex-row sm:items-end gap-3 mt-3">
                        <div class="flex-1 min-w-0">
                            <label for="mi-ruta-search" class="block text-xs font-medium text-gray-500 mb-1">Buscar cliente</label>
                            <input type="text" id="mi-ruta-search" name="ruta_q" value="{{ $searchTerm }}"
                                   placeholder="Nombre o NIT/documento"
                                   class="w-full border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                    class="px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors">
                                Buscar
                            </button>
                            @if($searchTerm !== '')
                                <a href="{{ route('clients.orders.index', ['tab' => 'mi-ruta', 'ruta' => $selectedRoute]) }}"
                                   class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </form>
        </div>

        @if($selectedRoute === '')
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
                Selecciona una ruta para ver los clientes con visita programada para hoy.
            </div>
        @elseif($routeClients === null || $routeClients->isEmpty())
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
                @if($searchTerm !== '')
                    No encontramos clientes en la ruta {{ $selectedRoute }} que coincidan con "{{ $searchTerm }}".
                @else
                    No hay clientes en la ruta {{ $selectedRoute }} con visita programada para hoy.
                @endif
            </div>
        @else
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-semibold text-gray-900">Clientes de la ruta {{ $selectedRoute }}</h2>
                    <span class="text-xs font-medium text-gray-500">{{ $routeClients->count() }} {{ $routeClients->count() === 1 ? 'cliente' : 'clientes' }}</span>
                </div>

                @foreach($routeClients as $clientZone)
                    @include('clients.orders.partials.route-client-card', [
                        'clientZone' => $clientZone,
                        'selectedRoute' => $selectedRoute,
                        'sellerZone' => $sellerZone,
                        'coversAllZones' => $coversAllZones,
                        'searchTerm' => $searchTerm,
                    ])
                @endforeach
            </div>
        @endif
    @endif
</div>
