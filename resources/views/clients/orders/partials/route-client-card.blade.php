@php
    $client = $clientZone->user;
    $selectedRoute = $selectedRoute ?? '';
    $sellerZone = $sellerZone ?? '';
    $coversAllZones = $coversAllZones ?? false;
    $searchTerm = $searchTerm ?? '';
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

    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row sm:flex-wrap sm:justify-end gap-2">
        <a href="{{ route('clients.orders.index', array_filter(['tab' => 'orders', 'all' => 1, 'client_id' => $client->id, 'return_ruta' => $selectedRoute, 'return_zona' => $coversAllZones ? $sellerZone : null, 'ruta_q' => $searchTerm !== '' ? $searchTerm : null])) }}"
           data-client-orders
           class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
            Pedidos anteriores
        </a>
        @if($client->document)
            <a href="{{ route('client-data-updates.edit', array_filter(['zone' => $clientZone->id, 'return_tab' => 'mi-ruta', 'ruta' => $selectedRoute, 'ruta_q' => $searchTerm])) }}"
               class="px-3 py-2 border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition-colors text-center">
                Actualizar datos
            </a>
            <a href="{{ route('new-client.create', ['mode' => 'sucursal', 'document' => $client->document, 'return' => 'mi-ruta']) }}"
               class="px-3 py-2 border border-orange-300 text-orange-700 text-xs font-medium rounded-lg hover:bg-orange-50 transition-colors text-center">
                Agregar sucursal
            </a>
            <form method="POST" action="{{ route('seller.setclient') }}">
                @csrf
                <input type="hidden" name="document" value="{{ $client->document }}">
                <button type="submit"
                        class="w-full sm:w-auto px-3 py-2 bg-orange-600 text-white text-xs font-medium rounded-lg hover:bg-orange-700 transition-colors">
                    Crear pedido
                </button>
            </form>
        @endif
    </div>
</div>
