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
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-semibold text-gray-900">Clientes de la ruta {{ $selectedRoute }}</h2>
                    <span class="text-xs font-medium text-gray-500">{{ $routeClients->count() }} {{ $routeClients->count() === 1 ? 'cliente' : 'clientes' }}</span>
                </div>

                @foreach($routeClients as $clientZone)
                    @php
                        $client = $clientZone->user;
                        $displayEmail = $client->email;
                        if (is_string($displayEmail) && str_ends_with(strtolower($displayEmail), '@tuti.com')) {
                            $displayEmail = null;
                        }
                        $fullAddress = collect([$clientZone->address, $client->city?->name])
                            ->filter(fn ($part) => filled($part))
                            ->implode(', ');
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $client->business_name ?: $client->name }}</p>
                                @if($client->business_name && $client->business_name !== $client->name)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $client->name }}</p>
                                @endif
                            </div>
                            <span class="self-start text-xs font-medium text-orange-600 bg-orange-50 rounded-full px-2.5 py-1 whitespace-nowrap">
                                {{ \App\Models\Zone::weekdayLabelFromDay($clientZone->day) ?? $clientZone->day }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 text-sm">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Cédula o NIT</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $client->document ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Razón social</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $client->business_name ?: $client->name ?: '-' }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-gray-500 uppercase">Dirección</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $fullAddress ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Teléfono</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $client->phone ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Celular</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $client->mobile_phone ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">WhatsApp</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $client->whatsapp ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Correo</p>
                                <p class="font-semibold text-gray-800 break-words">{{ $displayEmail ?: '-' }}</p>
                            </div>
                        </div>

                        @if($client->document)
                            <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:justify-end gap-2">
                                <a href="{{ route('client-data-updates.edit', ['zone' => $clientZone->id, 'return_tab' => 'mi-ruta', 'ruta' => $selectedRoute]) }}"
                                   class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                                    Actualizar datos
                                </a>
                                <form method="POST" action="{{ route('seller.setclient') }}">
                                    @csrf
                                    <input type="hidden" name="document" value="{{ $client->document }}">
                                    <button type="submit"
                                            class="w-full sm:w-auto px-3 py-2 bg-orange-600 text-white text-xs font-medium rounded-lg hover:bg-orange-700 transition-colors">
                                        Crear pedido
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
