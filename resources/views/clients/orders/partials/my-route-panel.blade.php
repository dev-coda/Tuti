@php
    $myRoute = $myRoute ?? [];
    $sellerZone = $myRoute['sellerZone'] ?? '';
    $routeOptions = $myRoute['routeOptions'] ?? collect();
    $selectedRoute = $myRoute['selectedRoute'] ?? '';
    $routeClients = $myRoute['clients'] ?? null;
    $todayLabel = $myRoute['todayLabel'] ?? '';
@endphp

<div class="space-y-6">
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
                    </p>
                </div>
            </form>
        </div>

        @if($selectedRoute === '')
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
                Selecciona una ruta para ver los clientes con visita programada para hoy.
            </div>
        @elseif($routeClients === null || $routeClients->isEmpty())
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 text-center text-gray-500">
                No hay clientes en la ruta {{ $selectedRoute }} con visita programada para hoy.
            </div>
        @else
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Clientes de la ruta {{ $selectedRoute }}</h2>
                    <span class="text-xs font-medium text-gray-500">{{ $routeClients->count() }} {{ $routeClients->count() === 1 ? 'cliente' : 'clientes' }}</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($routeClients as $clientZone)
                        @php $client = $clientZone->user; @endphp
                        <li class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-center gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $client->name }}</p>
                                @if($client->business_name)
                                    <p class="text-xs text-gray-500">{{ $client->business_name }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-1">
                                    Documento: {{ $client->document ?? '-' }} · Tel: {{ $client->mobile_phone ?? $client->phone ?? '-' }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $clientZone->address ?? '-' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-medium text-orange-600 bg-orange-50 rounded-full px-2.5 py-1">
                                    {{ \App\Models\Zone::weekdayLabelFromDay($clientZone->day) ?? $clientZone->day }}
                                </span>
                                @if($client->document)
                                    <form method="POST" action="{{ route('seller.setclient') }}">
                                        @csrf
                                        <input type="hidden" name="document" value="{{ $client->document }}">
                                        <button type="submit"
                                                class="px-3 py-2 bg-orange-600 text-white text-xs font-medium rounded-lg hover:bg-orange-700 transition-colors">
                                            Crear pedido
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
