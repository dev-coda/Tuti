<html>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="margin-bottom: 12px;">Actualización de datos de cliente</h2>

    <p><strong>ID solicitud:</strong> #{{ $updateRequest->id }}</p>
    <p><strong>Fecha:</strong> {{ $updateRequest->created_at?->format('d/m/Y H:i') }}</p>
    @if($updateRequest->seller)
        <p><strong>Vendedor:</strong> {{ $updateRequest->seller->name }} ({{ $updateRequest->seller->email }})</p>
    @endif

    <h3 style="margin: 20px 0 8px;">Datos solicitados</h3>
    <p><strong>Cédula o NIT:</strong> {{ $updateRequest->document ?: '-' }}</p>
    <p><strong>Razón social / nombre:</strong> {{ $updateRequest->name ?: '-' }}</p>
    <p><strong>Nombre del negocio:</strong> {{ $updateRequest->business_name ?: '-' }}</p>
    <p><strong>Correo:</strong> {{ $updateRequest->email ?: '-' }}</p>
    <p><strong>Teléfono:</strong> {{ $updateRequest->phone ?: '-' }}</p>
    <p><strong>Celular:</strong> {{ $updateRequest->mobile_phone ?: '-' }}</p>
    <p><strong>WhatsApp:</strong> {{ $updateRequest->whatsapp ?: '-' }}</p>
    <p><strong>Dirección:</strong> {{ $updateRequest->address ?: '-' }}</p>
    <p><strong>Ciudad:</strong> {{ $updateRequest->city_name ?: '-' }}</p>
    <p><strong>Zona:</strong> {{ $updateRequest->zone_code ?: '-' }}</p>
    <p><strong>Ruta:</strong> {{ $updateRequest->route ?: '-' }}</p>
    <p><strong>Día de visita:</strong> {{ $updateRequest->day ?: '-' }}</p>

    @if($updateRequest->seller_notes)
        <p><strong>Notas del vendedor:</strong></p>
        <p style="white-space: pre-line;">{{ $updateRequest->seller_notes }}</p>
    @endif

    @if(!empty($updateRequest->previous_data))
        <h3 style="margin: 20px 0 8px;">Datos anteriores registrados</h3>
        @foreach($updateRequest->previous_data as $field => $value)
            <p><strong>{{ ucfirst(str_replace('_', ' ', $field)) }}:</strong> {{ $value ?: '-' }}</p>
        @endforeach
    @endif

    <hr style="margin: 16px 0;">
    <p style="font-size: 12px; color: #6B7280;">
        Enviado desde TUTI — solicitud de actualización de datos de cliente.
    </p>
</body>
</html>
