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
            @if($contact->state === 'Existente')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">Cliente existente</span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">Nuevo</span>
            @endif
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

            <!-- Estado -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <form action="{{ route('contacts.update', $contact) }}" method="POST" class="flex items-center gap-3">
                    @csrf
                    @method('PUT')
                    <label for="status" class="text-sm font-medium text-gray-700">Estado del proceso:</label>
                    <select name="status" id="status" onchange="this.form.submit()"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500 {{ $contact->status_color }}">
                        @foreach(\App\Models\Contact::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(($contact->status ?? 'interesado') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
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
                <label for="Zona" class="block text-sm font-medium text-gray-700 mb-1">Zona *</label>
                <select name="Zona" id="Zona" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                    <option value="">Seleccione zona</option>
                    @php $currentZone = old('Zona', data_get($contact->new_client_payload, 'Zona')); @endphp
                    @foreach($zoneOptions as $zone)
                        <option value="{{ $zone }}" @selected($currentZone === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="RutaZonaVentas" class="block text-sm font-medium text-gray-700 mb-1">Ruta zona de ventas *</label>
                <select name="RutaZonaVentas" id="RutaZonaVentas" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
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
    const zoneSelect = document.getElementById('Zona');
    const routeSelect = document.getElementById('RutaZonaVentas');
    const currentRoute = @json(old('RutaZonaVentas', data_get($contact->new_client_payload, 'RutaZonaVentas')));

    function syncRoutes() {
        const zone = zoneSelect.value;
        const routes = routesByZone[zone] || [];
        routeSelect.innerHTML = '<option value="">Seleccione ruta</option>';
        routes.forEach((route) => {
            const option = document.createElement('option');
            option.value = route;
            option.textContent = route;
            if (route === currentRoute) option.selected = true;
            routeSelect.appendChild(option);
        });
    }

    zoneSelect.addEventListener('change', syncRoutes);
    syncRoutes();
});
</script>
@endsection
@endif
@endsection
