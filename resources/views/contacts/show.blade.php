@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('contacts.index') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 mb-2">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                Volver a interesados
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Contacto #{{ $contact->id }}</h1>
            <p class="text-sm text-gray-500 mt-1">Registrado el {{ $contact->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $contact->status_color }}">
                {{ $contact->status_label }}
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $contact->workflow_status_color }}">
                Estado: {{ $contact->workflow_status_label }}
            </span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $contact->transmit_status_color }}">
                Transmitir: {{ $contact->transmit_status_label }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Datos del contacto -->
        <div class="bg-gray-50 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Datos del contacto</h2>
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Tipo de persona</dt>
                    <dd class="mt-1">
                        @if($contact->person_type === 'juridica')
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-100 text-indigo-800 text-sm">Persona jurídica</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-100 text-gray-800 text-sm">Persona natural</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Nombre / Razón social</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">NIT / Cédula</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->nit ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Correo electrónico</dt>
                    <dd class="mt-1">
                        <a href="mailto:{{ $contact->email }}" class="text-blue-600 hover:underline">{{ $contact->email }}</a>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Celular</dt>
                    <dd class="mt-1">
                        <a href="tel:{{ $contact->phone }}" class="text-blue-600 hover:underline">{{ $contact->phone }}</a>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Departamento</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->department ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Ciudad</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->city?->name ?? $contact->city ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Dirección</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->address ?? '—' }}</dd>
                </div>
                @if($contact->business_name)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Tienda / Nombre negocio</dt>
                    <dd class="mt-1 text-gray-900">{{ $contact->business_name }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Términos aceptados</dt>
                    <dd class="mt-1">
                        @if($contact->terms_accepted)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">Sí</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded-full">No</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <!-- Verificación supervisor -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Verificación y actualización</h3>
                <form action="{{ route('contacts.update', $contact) }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado del proceso</label>
                        <select name="status" id="status"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 {{ $contact->status_color }}">
                            @foreach(\App\Models\Contact::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $contact->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="client_status" class="block text-sm font-medium text-gray-700 mb-1">Estado cliente</label>
                        <select name="client_status" id="client_status" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="{{ \App\Models\User::CLIENT_STATUS_PROSPECTO }}" @selected(old('client_status', $contact->resolveClientStatus()) === \App\Models\User::CLIENT_STATUS_PROSPECTO)>Prospecto</option>
                            <option value="{{ \App\Models\User::CLIENT_STATUS_PENDIENTE }}" @selected(old('client_status', $contact->resolveClientStatus()) === \App\Models\User::CLIENT_STATUS_PENDIENTE)>Pendiente</option>
                            <option value="{{ \App\Models\User::CLIENT_STATUS_CLIENTE }}" @selected(old('client_status', $contact->resolveClientStatus()) === \App\Models\User::CLIENT_STATUS_CLIENTE)>Cliente</option>
                            <option value="{{ \App\Models\User::CLIENT_STATUS_RECHAZADO }}" @selected(old('client_status', $contact->resolveClientStatus()) === \App\Models\User::CLIENT_STATUS_RECHAZADO)>Rechazado</option>
                        </select>
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del cliente</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $contact->name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700 mb-1">Establecimiento</label>
                        <input type="text" name="business_name" id="business_name" value="{{ old('business_name', $contact->business_name) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label for="nit" class="block text-sm font-medium text-gray-700 mb-1">Identificación</label>
                        <input type="text" name="nit" id="nit" value="{{ old('nit', $contact->nit) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Información de contacto (teléfono)</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $contact->phone) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Correo</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $contact->email) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Departamento</label>
                        <input type="text" name="department" id="department" value="{{ old('department', $contact->department) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-1">Ciudad</label>
                        <input type="text" name="city" id="city" value="{{ old('city', $contact->city?->name ?? $contact->city) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                        <input type="text" name="address" id="address" value="{{ old('address', $contact->address) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    </div>

                    <div>
                        <label for="verification_zona" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                        <select name="Zona" id="verification_zona" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">por asignar</option>
                            @php $currentZone = old('Zona', data_get($contact->new_client_payload, 'Zona')); @endphp
                            @foreach($zoneOptions as $zone)
                                <option value="{{ $zone }}" @selected($currentZone === $zone)>{{ $zone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="verification_ruta" class="block text-sm font-medium text-gray-700 mb-1">Ruta</label>
                        <select name="RutaZonaVentas" id="verification_ruta" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">por asignar</option>
                        </select>
                    </div>
                    <div>
                        <label for="DiaRecorrido" class="block text-sm font-medium text-gray-700 mb-1">Día</label>
                        @php $currentDay = old('DiaRecorrido', data_get($contact->new_client_payload, 'DiaRecorrido')); @endphp
                        <select name="DiaRecorrido" id="DiaRecorrido" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">por asignar</option>
                            @foreach(['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES'] as $day)
                                <option value="{{ $day }}" @selected($currentDay === $day)>{{ ucfirst(strtolower($day)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="Posicion" class="block text-sm font-medium text-gray-700 mb-1">Posición de rutero</label>
                        <input type="number" name="Posicion" id="Posicion" min="1" value="{{ old('Posicion', data_get($contact->new_client_payload, 'Posicion')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label for="verification_documents" class="block text-sm font-medium text-gray-700 mb-1">Documentos a subir</label>
                        <input type="file" name="verification_documents[]" id="verification_documents" multiple accept=".pdf,.jpg,.jpeg,.png" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                            Guardar verificación
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Documentos adjuntos -->
        <div class="bg-gray-50 rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Documentos adjuntos</h2>
            @if(!empty($contact->documents) && is_array($contact->documents))
                <div class="space-y-3">
                    @foreach($contact->documents as $index => $doc)
                        @php $filename = basename($doc); @endphp
                        <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg hover:border-blue-300 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                                    @if(str_ends_with(strtolower($filename), '.pdf'))
                                        @svg('heroicon-o-document-text', 'w-6 h-6 text-blue-600')
                                    @else
                                        @svg('heroicon-o-photo', 'w-6 h-6 text-blue-600')
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate" title="{{ $filename }}">{{ $filename }}</p>
                                    <p class="text-xs text-gray-500">Documento {{ $index + 1 }} de {{ count($contact->documents) }}</p>
                                </div>
                            </div>
                            <a href="{{ asset('storage/' . $doc) }}" target="_blank" rel="noopener noreferrer"
                                class="flex-shrink-0 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center gap-2">
                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                                Descargar
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 py-4">No hay documentos adjuntos.</p>
            @endif
        </div>
    </div>

    @if($contact->new_client_mode === 'self_service')
    <div class="mt-6 bg-gray-50 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Completar y enviar a API de Cliente Nuevo</h2>
        <p class="text-sm text-gray-600 mb-4">Este interesado fue creado por autogestion del cliente. Completa los datos faltantes de ruta y envia manualmente al webservice.</p>

        @if($contact->external_client_code)
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
                Ya fue enviado. Codigo externo: <strong>{{ $contact->external_client_code }}</strong>
                @if($contact->external_submitted_at)
                    ({{ $contact->external_submitted_at->format('d/m/Y H:i') }})
                @endif
            </div>
        @endif

        <form action="{{ route('contacts.submit-new-client', $contact) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4" id="manual-submit-form">
            @csrf
            <div>
                <label for="manual_zona" class="block text-sm font-medium text-gray-700 mb-1">Zona *</label>
                <select name="Zona" id="manual_zona" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Seleccione zona</option>
                    @php $currentZone = old('Zona', data_get($contact->new_client_payload, 'Zona')); @endphp
                    @foreach($zoneOptions as $zone)
                        <option value="{{ $zone }}" @selected($currentZone === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="manual_ruta" class="block text-sm font-medium text-gray-700 mb-1">Ruta zona de ventas *</label>
                <select name="RutaZonaVentas" id="manual_ruta" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Seleccione ruta</option>
                </select>
            </div>
            <div>
                <label for="DiaRecorrido" class="block text-sm font-medium text-gray-700 mb-1">Dia de recorrido *</label>
                @php $currentDay = old('DiaRecorrido', data_get($contact->new_client_payload, 'DiaRecorrido')); @endphp
                <select name="DiaRecorrido" id="DiaRecorrido" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Seleccione</option>
                    @foreach(['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES'] as $day)
                        <option value="{{ $day }}" @selected($currentDay === $day)>{{ ucfirst(strtolower($day)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="Posicion" class="block text-sm font-medium text-gray-700 mb-1">Posicion *</label>
                <input type="number" name="Posicion" id="Posicion" min="1" value="{{ old('Posicion', data_get($contact->new_client_payload, 'Posicion')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    Enviar manualmente al webservice
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

@if($contact->new_client_mode === 'self_service')
@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const routesByZone = @json($routesByZone);
    const manualZoneSelect = document.getElementById('manual_zona');
    const manualRouteSelect = document.getElementById('manual_ruta');
    const verificationZoneSelect = document.getElementById('verification_zona');
    const verificationRouteSelect = document.getElementById('verification_ruta');
    const currentRoute = @json(old('RutaZonaVentas', data_get($contact->new_client_payload, 'RutaZonaVentas')));

    function syncRoutes(zoneSelect, routeSelect, placeholder = 'Seleccione ruta') {
        if (!zoneSelect || !routeSelect) {
            return;
        }
        const zone = zoneSelect.value;
        const routes = routesByZone[zone] || [];
        routeSelect.innerHTML = `<option value="">${placeholder}</option>`;
        routes.forEach((route) => {
            const option = document.createElement('option');
            option.value = route;
            option.textContent = route;
            if (route === currentRoute) option.selected = true;
            routeSelect.appendChild(option);
        });
    }

    if (manualZoneSelect) {
        manualZoneSelect.addEventListener('change', () => syncRoutes(manualZoneSelect, manualRouteSelect, 'Seleccione ruta'));
        syncRoutes(manualZoneSelect, manualRouteSelect, 'Seleccione ruta');
    }

    if (verificationZoneSelect) {
        verificationZoneSelect.addEventListener('change', () => syncRoutes(verificationZoneSelect, verificationRouteSelect, 'por asignar'));
        syncRoutes(verificationZoneSelect, verificationRouteSelect, 'por asignar');
    }
});
</script>
@endsection
@endif
@endsection
