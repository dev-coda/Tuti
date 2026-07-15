<html>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="margin-bottom: 12px;">Actualización de datos de cliente</h2>

    <p><strong>ID solicitud:</strong> #{{ $updateRequest->id }}</p>
    <p><strong>Fecha:</strong> {{ $updateRequest->created_at?->format('d/m/Y H:i') }}</p>
    <p><strong>Cliente:</strong> {{ $updateRequest->name ?: '-' }} ({{ $updateRequest->document ?: '-' }})</p>
    @if($updateRequest->seller)
        <p><strong>Vendedor:</strong> {{ $updateRequest->seller->name }} ({{ $updateRequest->seller->email }})</p>
    @endif

    @php $changes = $updateRequest->changedFields(); @endphp

    @if(!empty($changes))
        <h3 style="margin: 20px 0 8px;">Datos actualizados</h3>
        <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
            <thead>
                <tr>
                    <th align="left" style="border-bottom: 2px solid #E5E7EB;">Campo</th>
                    <th align="left" style="border-bottom: 2px solid #E5E7EB;">Dato anterior</th>
                    <th align="left" style="border-bottom: 2px solid #E5E7EB;">Dato nuevo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($changes as $change)
                    <tr>
                        <td style="border-bottom: 1px solid #E5E7EB;"><strong>{{ $change['label'] }}</strong></td>
                        <td style="border-bottom: 1px solid #E5E7EB; color: #6B7280;">{{ $change['old'] ?: '-' }}</td>
                        <td style="border-bottom: 1px solid #E5E7EB; color: #047857;"><strong>{{ $change['new'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="margin: 20px 0 8px; color: #6B7280;">
            La solicitud no contiene cambios respecto a los datos registrados.
        </p>
    @endif

    @if($updateRequest->seller_notes)
        <p style="margin-top: 16px;"><strong>Notas del vendedor:</strong></p>
        <p style="white-space: pre-line;">{{ $updateRequest->seller_notes }}</p>
    @endif

    <hr style="margin: 16px 0;">
    <p style="font-size: 12px; color: #6B7280;">
        Enviado desde TUTI — solicitud de actualización de datos de cliente.
    </p>
</body>
</html>
