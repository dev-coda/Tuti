<html>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2 style="margin-bottom: 12px;">Nuevo PQRS de Servicio al Cliente</h2>

    <p><strong>Nombre y apellido:</strong> {{ $request->full_name }}</p>
    <p><strong>Email:</strong> {{ $request->email }}</p>
    <p><strong>Ciudad:</strong> {{ $request->city }}</p>
    <p><strong>Teléfono / Celular:</strong> {{ $request->phone }}</p>
    <p><strong>Tipo de solicitud:</strong> {{ $request->request_type_label }}</p>
    <p><strong>Asunto:</strong> {{ $request->subject }}</p>
    <p><strong>Mensaje:</strong></p>
    <p style="white-space: pre-line;">{{ $request->message }}</p>

    <hr style="margin: 16px 0;">
    <p style="font-size: 12px; color: #6B7280;">
        Enviado desde el formulario de Servicio al Cliente TUTI.
    </p>
</body>
</html>
