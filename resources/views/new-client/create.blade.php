@extends($layout)

@section('head')
@include('elements.seo', ['title' => 'Registrar Cliente Nuevo', 'description' => 'Formulario de registro de cliente nuevo'])
@endsection

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <h1 class="text-3xl font-bold text-gray-900 mb-2">Registrar Cliente Nuevo</h1>
    <p class="text-gray-600 mb-8">Complete todos los campos requeridos para registrar un cliente nuevo en el sistema.</p>

    @if(session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-green-800 font-medium">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <p class="text-red-800 font-medium">{{ session('error') }}</p>
    </div>
    @endif

    @if(session('warning'))
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <p class="text-yellow-800 font-medium">{{ session('warning') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('new-client.store') }}" enctype="multipart/form-data" id="new-client-form">
        @csrf

        {{-- Document Information --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"></path></svg>
                Identificación
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="TipoDocumento" class="block text-sm font-medium text-gray-700 mb-1">Tipo de documento *</label>
                    <select name="TipoDocumento" id="TipoDocumento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione...</option>
                        @foreach($tipoDocumentoOptions as $id => $label)
                        <option value="{{ $id }}" {{ old('TipoDocumento') == $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('TipoDocumento') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Documento" class="block text-sm font-medium text-gray-700 mb-1">Documento *</label>
                    <input type="text" name="Documento" id="Documento" value="{{ old('Documento') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="20" placeholder="NIT o Cédula" required>
                    @error('Documento') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Personal Information --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Datos Personales
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="PrimerNombre" class="block text-sm font-medium text-gray-700 mb-1">Primer nombre <span id="nombre-req" class="text-red-500 hidden">*</span></label>
                    <input type="text" name="PrimerNombre" id="PrimerNombre" value="{{ old('PrimerNombre') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="50">
                    @error('PrimerNombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="SegundoNombre" class="block text-sm font-medium text-gray-700 mb-1">Segundo nombre</label>
                    <input type="text" name="SegundoNombre" id="SegundoNombre" value="{{ old('SegundoNombre') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="50">
                    @error('SegundoNombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="PrimerApellido" class="block text-sm font-medium text-gray-700 mb-1">Primer apellido <span id="apellido-req" class="text-red-500 hidden">*</span></label>
                    <input type="text" name="PrimerApellido" id="PrimerApellido" value="{{ old('PrimerApellido') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="50">
                    @error('PrimerApellido') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="SegundoApellido" class="block text-sm font-medium text-gray-700 mb-1">Segundo apellido</label>
                    <input type="text" name="SegundoApellido" id="SegundoApellido" value="{{ old('SegundoApellido') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="50">
                    @error('SegundoApellido') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Business Information --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Negocio
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="NombreNegocio" class="block text-sm font-medium text-gray-700 mb-1">Nombre del negocio *</label>
                    <input type="text" name="NombreNegocio" id="NombreNegocio" value="{{ old('NombreNegocio') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="100" required>
                    @error('NombreNegocio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="IdClasificacionCliente" class="block text-sm font-medium text-gray-700 mb-1">Clasificación *</label>
                    <select name="IdClasificacionCliente" id="IdClasificacionCliente" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione...</option>
                        @foreach($clasificacionOptions as $id => $label)
                        <option value="{{ $id }}" {{ old('IdClasificacionCliente') == $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('IdClasificacionCliente') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Pep" class="block text-sm font-medium text-gray-700 mb-1">Persona Expuesta Políticamente (PEP) *</label>
                    <select name="Pep" id="Pep" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione...</option>
                        <option value="SI" {{ old('Pep') === 'SI' ? 'selected' : '' }}>Sí</option>
                        <option value="NO" {{ old('Pep') === 'NO' ? 'selected' : '' }}>No</option>
                    </select>
                    @error('Pep') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Location --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Ubicación
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="Departamento" class="block text-sm font-medium text-gray-700 mb-1">Departamento *</label>
                    <select name="Departamento" id="Departamento" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione...</option>
                        @foreach($states as $id => $name)
                        <option value="{{ $name }}" data-state-id="{{ $id }}" {{ old('Departamento') === $name ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('Departamento') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Ciudad" class="block text-sm font-medium text-gray-700 mb-1">Ciudad / Municipio *</label>
                    <select name="Ciudad" id="Ciudad" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Primero seleccione departamento</option>
                    </select>
                    @error('Ciudad') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="Direccion" class="block text-sm font-medium text-gray-700 mb-1">Dirección *</label>
                    <input type="text" name="Direccion" id="Direccion" value="{{ old('Direccion') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="100" required>
                    @error('Direccion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Barrio" class="block text-sm font-medium text-gray-700 mb-1">Barrio *</label>
                    <input type="text" name="Barrio" id="Barrio" value="{{ old('Barrio') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="100" required>
                    @error('Barrio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                Contacto
            </h2>
            <p class="text-sm text-gray-500 mb-3">Al menos uno de los tres números es obligatorio.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="Telefono" class="block text-sm font-medium text-gray-700 mb-1">Teléfono (7 dígitos)</label>
                    <input type="text" name="Telefono" id="Telefono" value="{{ old('Telefono') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="7" pattern="\d{7}" placeholder="8871234">
                    @error('Telefono') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Movil" class="block text-sm font-medium text-gray-700 mb-1">Móvil (10 dígitos)</label>
                    <input type="text" name="Movil" id="Movil" value="{{ old('Movil') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="10" pattern="\d{10}" placeholder="3101234567">
                    @error('Movil') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Whatsapp" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp (10 dígitos)</label>
                    <input type="text" name="Whatsapp" id="Whatsapp" value="{{ old('Whatsapp') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="10" pattern="\d{10}" placeholder="3101234567">
                    @error('Whatsapp') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4">
                <label for="Correo" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                <input type="email" name="Correo" id="Correo" value="{{ old('Correo') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" maxlength="100" placeholder="cliente@email.com">
                @error('Correo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        @if($isSellerFlow)
        {{-- Route Information (seller flow only) --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                Ruta de Ventas
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="ZonaDisplay" class="block text-sm font-medium text-gray-700 mb-1">Zona *</label>
                    <input type="text" id="ZonaDisplay" value="{{ old('Zona', $sellerZone) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700" readonly>
                    <input type="hidden" name="Zona" value="{{ old('Zona', $sellerZone) }}">
                    @error('Zona') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="RutaZonaVentas" class="block text-sm font-medium text-gray-700 mb-1">Ruta zona de ventas *</label>
                    <select name="RutaZonaVentas" id="RutaZonaVentas" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione ruta...</option>
                        @foreach($zoneRoutes as $route)
                            <option value="{{ $route }}" @selected(old('RutaZonaVentas') === $route)>{{ $route }}</option>
                        @endforeach
                    </select>
                    @error('RutaZonaVentas') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @if(empty($zoneRoutes))
                        <p class="text-xs text-amber-700 mt-1">No hay rutas configuradas para esta zona. Un administrador debe crearlas en "Rutas por Zona".</p>
                    @endif
                </div>
                <div>
                    <label for="DiaRecorrido" class="block text-sm font-medium text-gray-700 mb-1">Día de recorrido *</label>
                    <select name="DiaRecorrido" id="DiaRecorrido" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                        <option value="">Seleccione...</option>
                        @foreach($diaOptions as $dia)
                        <option value="{{ $dia }}" {{ old('DiaRecorrido') === $dia ? 'selected' : '' }}>{{ ucfirst(strtolower($dia)) }}</option>
                        @endforeach
                    </select>
                    @error('DiaRecorrido') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="Posicion" class="block text-sm font-medium text-gray-700 mb-1">Posición en rutero *</label>
                    <input type="number" name="Posicion" id="Posicion" value="{{ old('Posicion') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" min="1" required placeholder="1">
                    @error('Posicion') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        @endif

        {{-- Documents --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Documentos
            </h2>
            <p id="documents-help-text" class="text-sm text-gray-500 mb-3"></p>
            <p id="documents-limit-text" class="text-xs text-gray-500 mb-3"></p>

            <input type="file" name="documents[]" id="documents" multiple accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
            <div id="documents-preview" class="mt-3 space-y-2"></div>
            @error('documents') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            @error('documents.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Signature (final step) --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                Firma del Cliente *
            </h2>
            <p class="text-sm text-gray-500 mb-3">Use el mouse o el dedo (en dispositivos táctiles) para firmar en el recuadro.</p>

            <div class="border-2 border-dashed border-gray-300 rounded-lg p-1 bg-gray-50 relative" style="touch-action: none;">
                <canvas id="signature-canvas" class="w-full bg-white rounded" style="height: 200px; cursor: crosshair;"></canvas>
            </div>
            <div class="flex gap-3 mt-3">
                <button type="button" id="clear-signature" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                    Limpiar firma
                </button>
            </div>
            <input type="hidden" name="signature" id="signature-data">
            @error('signature') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

            <div class="mt-4 pt-4 border-t border-gray-200">
                <label class="inline-flex items-start gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="terms_accepted" value="1" class="mt-0.5 rounded border-gray-300 text-orange-600 focus:ring-orange-500" {{ old('terms_accepted') ? 'checked' : '' }} required>
                    <span>Aceptar términos y condiciones</span>
                </label>
                @error('terms_accepted') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end">
            <button type="submit" id="submit-btn" class="px-8 py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg shadow-sm transition-colors text-lg">
                {{ $isSellerFlow ? 'Registrar Cliente' : 'Enviar Solicitud' }}
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // -- Conditional name fields --
    const tipoDoc = document.getElementById('TipoDocumento');
    const nombreReq = document.getElementById('nombre-req');
    const apellidoReq = document.getElementById('apellido-req');

    function updateNameRequirements() {
        const v = parseInt(tipoDoc.value);
        const required = (v === 1 || v === 2);
        nombreReq.classList.toggle('hidden', !required);
        apellidoReq.classList.toggle('hidden', !required);
        document.getElementById('PrimerNombre').required = required;
        document.getElementById('PrimerApellido').required = required;
    }
    tipoDoc.addEventListener('change', updateNameRequirements);
    updateNameRequirements();

    // -- Cascading Departamento → Ciudad --
    const depSelect = document.getElementById('Departamento');
    const citySelect = document.getElementById('Ciudad');
    const oldCiudad = @json(old('Ciudad', ''));

    depSelect.addEventListener('change', function() {
        const opt = depSelect.options[depSelect.selectedIndex];
        const stateId = opt ? opt.dataset.stateId : '';
        citySelect.innerHTML = '<option value="">Cargando...</option>';
        if (!stateId) {
            citySelect.innerHTML = '<option value="">Primero seleccione departamento</option>';
            return;
        }
        fetch('{{ route("form.cities-by-state") }}?state_id=' + stateId)
            .then(r => r.json())
            .then(cities => {
                citySelect.innerHTML = '<option value="">Seleccione ciudad...</option>';
                Object.entries(cities).forEach(([id, name]) => {
                    const o = document.createElement('option');
                    o.value = name;
                    o.textContent = name;
                    if (name === oldCiudad) o.selected = true;
                    citySelect.appendChild(o);
                });
            })
            .catch(() => {
                citySelect.innerHTML = '<option value="">Error al cargar ciudades</option>';
            });
    });

    if (depSelect.value) depSelect.dispatchEvent(new Event('change'));

    // -- Documents upload rules by client type --
    const documentsInput = document.getElementById('documents');
    const documentsPreview = document.getElementById('documents-preview');
    const documentsHelpText = document.getElementById('documents-help-text');
    const documentsLimitText = document.getElementById('documents-limit-text');

    function getDocConfigByTipoDocumento() {
        const tipo = parseInt(tipoDoc.value || '0', 10);
        const isJuridica = tipo === 3;

        if (isJuridica) {
            return {
                maxFiles: 6,
                help: 'Persona jurídica: sube RUT, foto del documento del representante legal, certificado de existencia, cámara de comercio, certificado de accionistas y beneficiarios finales.',
            };
        }

        return {
            maxFiles: 2,
            help: 'Persona natural: sube Cédula o RUT.',
        };
    }

    function updateDocumentsHelp() {
        const config = getDocConfigByTipoDocumento();
        documentsHelpText.textContent = config.help;
        documentsLimitText.textContent = `Formatos permitidos: PDF, JPG, JPEG, PNG. Máximo ${config.maxFiles} archivo(s), 5 MB por archivo.`;
    }

    if (documentsInput) {
        documentsInput.addEventListener('change', function() {
            documentsPreview.innerHTML = '';
            const files = Array.from(this.files);
            const config = getDocConfigByTipoDocumento();

            if (files.length > config.maxFiles) {
                alert(`Máximo ${config.maxFiles} archivo(s) permitidos para este tipo de cliente.`);
                this.value = '';
                return;
            }

            const allowed = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            for (const file of files) {
                if (!allowed.includes(file.type)) {
                    alert('Solo se permiten archivos PDF, JPG y PNG. Archivo rechazado: ' + file.name);
                    this.value = '';
                    documentsPreview.innerHTML = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('El archivo ' + file.name + ' excede los 5 MB permitidos.');
                    this.value = '';
                    documentsPreview.innerHTML = '';
                    return;
                }

                const row = document.createElement('div');
                row.className = 'text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2 bg-gray-50';
                row.textContent = file.name;
                documentsPreview.appendChild(row);
            }
        });
    }

    tipoDoc.addEventListener('change', updateDocumentsHelp);
    updateDocumentsHelp();

    // -- Signature pad --
    const canvas = document.getElementById('signature-canvas');
    const ctx = canvas.getContext('2d');
    const signatureInput = document.getElementById('signature-data');
    let drawing = false;
    let hasDrawn = false;

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        ctx.strokeStyle = '#1a1a1a';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches ? e.touches[0] : e;
        return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        hasDrawn = true;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDraw(e) {
        if (drawing) {
            e.preventDefault();
            drawing = false;
            signatureInput.value = canvas.toDataURL('image/png');
        }
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDraw);
    canvas.addEventListener('mouseleave', stopDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDraw, { passive: false });

    document.getElementById('clear-signature').addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        signatureInput.value = '';
        hasDrawn = false;
    });

    // -- Form submission validation --
    document.getElementById('new-client-form').addEventListener('submit', function(e) {
        if (!hasDrawn || !signatureInput.value) {
            e.preventDefault();
            alert('Por favor firme en el recuadro antes de enviar.');
            return;
        }
        document.getElementById('submit-btn').disabled = true;
        document.getElementById('submit-btn').textContent = 'Enviando...';
    });
});
</script>
@endsection
