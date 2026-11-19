@php
    $clientZone = $clientZone ?? null;
    $addressFallback = $addressFallback ?? null;
    $displayEmail = $client->clientDisplayEmail();
    $fullAddress = collect([$clientZone?->address ?: $addressFallback, $client->city?->name])
        ->filter(fn ($part) => filled($part))
        ->implode(', ');
@endphp

<x-account-record-field label="Cédula o NIT">{{ $client->document ?: '-' }}</x-account-record-field>
<x-account-record-field label="Razón social">{{ $client->business_name ?: $client->name ?: '-' }}</x-account-record-field>
<x-account-record-field label="Dirección" span>{{ $fullAddress ?: '-' }}</x-account-record-field>
<x-account-record-field label="Teléfono">{{ $client->phone ?: '-' }}</x-account-record-field>
<x-account-record-field label="Celular">{{ $client->mobile_phone ?: '-' }}</x-account-record-field>
<x-account-record-field label="WhatsApp">{{ $client->whatsapp ?: '-' }}</x-account-record-field>
<x-account-record-field label="Correo">{{ $displayEmail ?: '-' }}</x-account-record-field>
