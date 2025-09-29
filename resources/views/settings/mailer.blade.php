@extends('layouts.admin')

@section('title', 'Configuración de Correo')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Configuración de Correo</h1>
                <a href="{{ route('settings.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Configuración
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form action="{{ route('settings.mailer.update') }}" method="POST">
                @csrf

                <!-- Mail Driver Selection -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Configuración General</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mail_mailer" class="form-label">Driver de Correo *</label>
                                    <select class="form-select @error('mail_mailer') is-invalid @enderror"
                                            id="mail_mailer"
                                            name="mail_mailer"
                                            required>
                                        <option value="smtp" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'smtp' ? 'selected' : '' }}>SMTP</option>
                                        <option value="mailgun" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                                        <option value="sendmail" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                                        <option value="log" {{ ($mailerSettings['mail_mailer']->value ?? 'mailgun') == 'log' ? 'selected' : '' }}>Log (para pruebas)</option>
                                    </select>
                                    @error('mail_mailer')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mail_from_address" class="form-label">Dirección de Remitente *</label>
                                    <input type="email"
                                           class="form-control @error('mail_from_address') is-invalid @enderror"
                                           id="mail_from_address"
                                           name="mail_from_address"
                                           value="{{ $mailerSettings['mail_from_address']->value ?? 'noreply@tuti.com' }}"
                                           required>
                                    @error('mail_from_address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mail_from_name" class="form-label">Nombre del Remitente *</label>
                                    <input type="text"
                                           class="form-control @error('mail_from_name') is-invalid @enderror"
                                           id="mail_from_name"
                                           name="mail_from_name"
                                           value="{{ $mailerSettings['mail_from_name']->value ?? 'Tuti' }}"
                                           required>
                                    @error('mail_from_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mailgun Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Configuración de Mailgun</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mailgun_domain" class="form-label">Dominio de Mailgun</label>
                                    <input type="text"
                                           class="form-control @error('mailgun_domain') is-invalid @enderror"
                                           id="mailgun_domain"
                                           name="mailgun_domain"
                                           value="{{ $mailerSettings['mailgun_domain']->value ?? '' }}"
                                           placeholder="ej: mg.tuti.com">
                                    @error('mailgun_domain')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mailgun_secret" class="form-label">Clave Secreta de Mailgun</label>
                                    <input type="password"
                                           class="form-control @error('mailgun_secret') is-invalid @enderror"
                                           id="mailgun_secret"
                                           name="mailgun_secret"
                                           value="{{ $mailerSettings['mailgun_secret']->value ?? '' }}"
                                           placeholder="Clave API de Mailgun">
                                    @error('mailgun_secret')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="mailgun_endpoint" class="form-label">Endpoint de Mailgun</label>
                            <input type="text"
                                   class="form-control @error('mailgun_endpoint') is-invalid @enderror"
                                   id="mailgun_endpoint"
                                   name="mailgun_endpoint"
                                   value="{{ $mailerSettings['mailgun_endpoint']->value ?? 'api.mailgun.net' }}"
                                   placeholder="api.mailgun.net o api.eu.mailgun.net">
                            @error('mailgun_endpoint')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- SMTP Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Configuración SMTP</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_host" class="form-label">Servidor SMTP</label>
                                    <input type="text"
                                           class="form-control @error('smtp_host') is-invalid @enderror"
                                           id="smtp_host"
                                           name="smtp_host"
                                           value="{{ $mailerSettings['smtp_host']->value ?? 'smtp.mailgun.org' }}"
                                           placeholder="ej: smtp.gmail.com">
                                    @error('smtp_host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="smtp_port" class="form-label">Puerto SMTP</label>
                                    <input type="number"
                                           class="form-control @error('smtp_port') is-invalid @enderror"
                                           id="smtp_port"
                                           name="smtp_port"
                                           value="{{ $mailerSettings['smtp_port']->value ?? '587' }}"
                                           min="1"
                                           max="65535"
                                           placeholder="587">
                                    @error('smtp_port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label">Encriptación</label>
                                    <select class="form-select @error('smtp_encryption') is-invalid @enderror"
                                            id="smtp_encryption"
                                            name="smtp_encryption">
                                        <option value="tls" {{ ($mailerSettings['smtp_encryption']->value ?? 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ ($mailerSettings['smtp_encryption']->value ?? 'tls') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                        <option value="">Ninguna</option>
                                    </select>
                                    @error('smtp_encryption')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_username" class="form-label">Usuario SMTP</label>
                                    <input type="text"
                                           class="form-control @error('smtp_username') is-invalid @enderror"
                                           id="smtp_username"
                                           name="smtp_username"
                                           value="{{ $mailerSettings['smtp_username']->value ?? '' }}"
                                           placeholder="usuario@ejemplo.com">
                                    @error('smtp_username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_password" class="form-label">Contraseña SMTP</label>
                                    <input type="password"
                                           class="form-control @error('smtp_password') is-invalid @enderror"
                                           id="smtp_password"
                                           name="smtp_password"
                                           value="{{ $mailerSettings['smtp_password']->value ?? '' }}"
                                           placeholder="Contraseña de la cuenta">
                                    @error('smtp_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Email Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Probar Configuración</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="test_email" class="form-label">Enviar correo de prueba a:</label>
                                    <input type="email"
                                           class="form-control"
                                           id="test_email"
                                           placeholder="correo@ejemplo.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-info w-100" id="test-email-btn">
                                        <i class="fas fa-paper-plane"></i> Enviar Prueba
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="test-result" class="mt-2" style="display: none;"></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testEmailBtn = document.getElementById('test-email-btn');
    const testEmailInput = document.getElementById('test_email');
    const testResult = document.getElementById('test-result');

    testEmailBtn.addEventListener('click', function() {
        const email = testEmailInput.value.trim();

        if (!email) {
            alert('Por favor ingresa una dirección de correo para la prueba');
            return;
        }

        // Disable button and show loading
        testEmailBtn.disabled = true;
        testEmailBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        // Send test email via AJAX
        fetch('/admin/test-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            testResult.style.display = 'block';
            if (data.success) {
                testResult.className = 'alert alert-success mt-2';
                testResult.textContent = 'Correo de prueba enviado exitosamente';
            } else {
                testResult.className = 'alert alert-danger mt-2';
                testResult.textContent = 'Error al enviar correo de prueba: ' + data.message;
            }
        })
        .catch(error => {
            testResult.style.display = 'block';
            testResult.className = 'alert alert-danger mt-2';
            testResult.textContent = 'Error de conexión: ' + error.message;
        })
        .finally(() => {
            // Re-enable button
            testEmailBtn.disabled = false;
            testEmailBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Prueba';
        });
    });
});
</script>
@endsection
