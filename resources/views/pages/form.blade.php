@extends('layouts.page')


@section('head')
@include('elements.seo', [
'title'=>'¿Quieres ser cliente de TUTI?',
'description'=> '¿Quieres ser cliente de TUTI?'
])
@endsection



@section('content')
<div class="max-w-6xl container mx-auto mt-5 mb-20">
    <h1 class="xl:text-4xl text-2xl font-bold text-center mb-8">Bienvenido Tendero</h1>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div id="login-section" class="border border-2 border-blue-900 p-5 rounded-lg flex flex-col items-center">
            <div class="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">Ingreso</h2>
            <p>Ingresa aquí solo si ya estás registrado</p>

            <div class="mt-5 p-5 w-full">
                <form method="POST" action="{{ route('login') }}" class="space-y-5" id="login-form">
                    @csrf
                    <div>
                        <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                        <input type="email" id="login-email" name="email" placeholder="Correo electrónico" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required value="{{ old('email') }}">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                        <input type="password" id="login-password" name="password" placeholder="Ingresa tu contraseña" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                    </div>

                    <div class="flex justify-between">
                        <a href="{{ route('password.request') }}" class="text-sm text-orange-600 hover:underline">¿Olvidó su contraseña?</a>
                    </div>

                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                        <span>Ingresar</span>
                    </button>
                </form>

                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 uppercase tracking-wider text-xs font-semibold">O bien</span>
                    </div>
                </div>

                <button type="button" id="magic-link-btn" class="w-full border-2 border-gray-300 hover:border-orange-400 text-gray-700 hover:text-orange-600 font-semibold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 magic-link-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" />
                    </svg>
                    <svg class="animate-spin h-5 w-5 magic-link-spinner hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="magic-link-text">Ingresar sin contraseña</span>
                </button>
                <p class="text-center text-xs text-gray-500 mt-2" id="magic-link-hint">Te enviaremos un código de verificación a tu correo.</p>
                <p class="text-center text-xs text-red-600 mt-2 hidden" id="magic-link-error"></p>

                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500 uppercase tracking-wider text-xs font-semibold">O bien</span>
                    </div>
                </div>
                <button type="button" id="tronex-btn" class="w-full border-2 border-blue-200 hover:border-blue-400 text-blue-700 hover:text-blue-900 font-semibold py-3 px-4 rounded-lg transition duration-300 flex items-center justify-center space-x-2 bg-blue-50 hover:bg-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span>¿Ya eres cliente Tronex?</span>
                </button>
                <p class="text-center text-xs text-gray-500 mt-2">Si ya compras con Tronex, ingresa tu cédula para crear tu cuenta Tuti.</p>
            </div>
        </div>

        <div id="register-section" class="border border-2 border-blue-900 p-5 rounded-lg flex flex-col items-center">
            <p class="text-center text-sm text-gray-600">Diligencia el formulario e inicia el proceso de activación como cliente TUTI</p>
            <div class="w-20 h-20 bg-blue-900 rounded-full flex items-center justify-center my-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4m4-5h8a2 2 0 012 2v10H6V9a2 2 0 012-2z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-center">Cliente nuevo</h2>
            <h3 class="text-2xl text-center mb-4">Crea tu cuenta</h3>
            <ul class="text-base text-gray-800 space-y-1">
                <li>- Promociones y descuentos exclusivos</li>
                <li>- Pago contra-entrega</li>
                <li>- Programa tu pedido 24/48 horas</li>
                <li>- Respaldo TRONEX</li>
            </ul>

            <a href="{{ route('new-client.create') }}" class="mt-8 w-full max-w-sm inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 px-4 rounded-lg transition duration-300">
                Crear cuenta
            </a>
        </div>
    </div>
</div>

<!-- Magic Link Verification Modal -->
<div id="magic-link-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="magic-modal-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative transform transition-all" id="magic-modal-content">
            <button type="button" id="magic-modal-close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-orange-500" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Ingreso sin contraseña</h3>
            <p class="text-center text-sm text-gray-500 mb-1">Hemos enviado un código de verificación a</p>
            <p class="text-center text-sm font-semibold text-gray-700 mb-6" id="magic-modal-email"></p>
            <div class="mb-4">
                <label for="magic-code-input" class="block text-sm font-medium text-gray-700 mb-2">Código de verificación</label>
                <input type="text" id="magic-code-input" maxlength="6" placeholder="000000" inputmode="numeric" pattern="[0-9]*"
                    class="w-full text-center text-2xl tracking-[0.5em] font-mono py-3 px-4 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" autocomplete="one-time-code">
                <p id="magic-code-error" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
            <button type="button" id="magic-verify-btn" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                Verificar e ingresar
            </button>
            <div class="text-center mt-4">
                <button type="button" id="magic-resend-btn" class="text-sm text-orange-600 hover:text-orange-700 hover:underline font-medium">
                    Reenviar código
                </button>
                <p id="magic-resend-timer" class="text-sm text-gray-400 hidden"></p>
            </div>
            <div id="magic-loading" class="hidden absolute inset-0 bg-white bg-opacity-80 rounded-xl flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <svg class="animate-spin h-8 w-8 text-orange-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-gray-500">Procesando...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tronex Client Modal -->
<div id="tronex-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="tronex-modal-backdrop"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative transform transition-all" id="tronex-modal-content">
            <button type="button" id="tronex-modal-close" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-xl font-bold text-center text-gray-900 mb-1">Cliente Tronex</h3>
            <p id="tronex-step-description" class="text-center text-sm text-gray-500 mb-6">Ingresa tu número de cédula para continuar con la validación</p>
            <div id="tronex-cedula-field" class="mb-4">
                <label for="tronex-cedula-input" class="block text-sm font-medium text-gray-700 mb-2">Cédula</label>
                <input type="text" id="tronex-cedula-input" placeholder="Número de cédula" inputmode="numeric"
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autocomplete="off">
            </div>
            <div id="tronex-phone-field" class="mb-4 hidden">
                <label for="tronex-phone-input" class="block text-sm font-medium text-gray-700 mb-2">Celular registrado</label>
                <input type="text" id="tronex-phone-input" placeholder="Número de celular" inputmode="tel"
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" autocomplete="off">
                <p class="mt-2 text-xs text-gray-500">Usa el número que tienes registrado con Tronex. Acepta formato con o sin +57.</p>
            </div>
            <div class="mb-4">
                <p id="tronex-error" class="mt-2 text-sm text-red-600 hidden"></p>
            </div>
            <button type="button" id="tronex-submit-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed">
                Validar cédula
            </button>
            <div id="tronex-loading" class="hidden absolute inset-0 bg-white bg-opacity-80 rounded-xl flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <svg class="animate-spin h-8 w-8 text-blue-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm text-gray-500">Verificando...</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // =============================================
    // Person Type Switching
    // =============================================
    const personTypeOptions = document.querySelectorAll('.person-type-option');
    const nitLabel = document.getElementById('nit-label');
    const nitInput = document.getElementById('reg_nit');
    const nameLabel = document.getElementById('name-label');
    const nameInput = document.getElementById('reg_name');
    const documentsHint = document.getElementById('documents-hint');

    function updatePersonType(type) {
        personTypeOptions.forEach(opt => {
            const isActive = opt.dataset.value === type;
            opt.classList.toggle('border-blue-500', isActive);
            opt.classList.toggle('bg-blue-50', isActive);
            opt.classList.toggle('border-gray-300', !isActive);
            opt.classList.toggle('bg-white', !isActive);
            opt.querySelector('span').classList.toggle('text-blue-700', isActive);
            opt.querySelector('span').classList.toggle('text-gray-600', !isActive);
        });

        if (type === 'juridica') {
            nitLabel.textContent = 'NIT';
            nitInput.placeholder = 'Número de NIT';
            nameLabel.textContent = 'Razón social';
            nameInput.placeholder = 'Razón social';
            documentsHint.textContent = 'Persona jurídica: RUT, cédula del representante legal, cámara de comercio, certificado de composición accionaria';
        } else {
            nitLabel.textContent = 'Cédula';
            nitInput.placeholder = 'Número de cédula';
            nameLabel.textContent = 'Nombre completo';
            nameInput.placeholder = 'Nombre completo';
            documentsHint.textContent = 'Persona natural: copia de cédula y/o RUT';
        }
    }

    personTypeOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            updatePersonType(radio.value);
        });
    });

    // =============================================
    // Existing Client Check
    // =============================================
    const existingAlert = document.getElementById('existing-client-alert');
    let checkTimeout = null;

    function checkExistingClient() {
        const nit = nitInput?.value?.trim();
        const email = document.getElementById('reg_email')?.value?.trim();

        if ((!nit || nit.length < 5) && (!email || !email.includes('@'))) return;

        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(async () => {
            try {
                const response = await fetch('{{ route("form.check-existing") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ nit, email }),
                });
                const data = await response.json();
                existingAlert.classList.toggle('hidden', !data.exists);
            } catch (e) {
                // silently fail
            }
        }, 600);
    }

    if (nitInput) nitInput.addEventListener('blur', checkExistingClient);
    const regEmail = document.getElementById('reg_email');
    if (regEmail) regEmail.addEventListener('blur', checkExistingClient);

    // =============================================
    // Department -> City cascade
    // =============================================
    const departmentSelect = document.getElementById('reg_department');
    const citySelect = document.getElementById('reg_city_id');

    if (departmentSelect) {
        departmentSelect.addEventListener('change', async function() {
            const selectedOption = this.options[this.selectedIndex];
            const stateId = selectedOption?.dataset?.stateId;
            if (!stateId) return;

            citySelect.innerHTML = '<option value="">Cargando ciudades...</option>';
            try {
                const response = await fetch(`{{ route("form.cities-by-state") }}?state_id=${stateId}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const cities = await response.json();
                citySelect.innerHTML = '<option value="">Selecciona tu ciudad</option>';
                for (const [id, name] of Object.entries(cities)) {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = name;
                    citySelect.appendChild(option);
                }
            } catch (e) {
                citySelect.innerHTML = '<option value="">Error al cargar ciudades</option>';
            }
        });
    }

    // =============================================
    // File Upload Management
    // =============================================
    const fileContainer = document.getElementById('file-inputs-container');
    const addFileBtn = document.getElementById('add-file-btn');
    let fileCount = 1;

    if (addFileBtn) {
        addFileBtn.addEventListener('click', function() {
            if (fileCount >= 10) return;
            fileCount++;
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 file-input-row mt-2';
            row.innerHTML = `
                <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png"
                    class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg cursor-pointer">
                <button type="button" class="text-red-500 hover:text-red-700 remove-file-btn p-1" title="Quitar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>`;
            fileContainer.appendChild(row);
            addFileBtn.classList.toggle('hidden', fileCount >= 10);
        });
    }

    // Delegate remove-file-btn clicks (covers first row + dynamically added rows)
    if (fileContainer) {
        fileContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.remove-file-btn');
            if (!btn) return;
            const row = btn.closest('.file-input-row');
            if (!row || fileContainer.querySelectorAll('.file-input-row').length <= 1) return; // keep at least one
            row.remove();
            fileCount--;
            if (addFileBtn) addFileBtn.classList.toggle('hidden', fileCount >= 10);
        });
    }

    // =============================================
    // Terms & Submit
    // =============================================
    const termsAccepted = document.getElementById('terms_accepted');
    const submitRequest = document.getElementById('submit-request');

    if (termsAccepted && submitRequest) {
        const syncSubmitState = () => { submitRequest.disabled = !termsAccepted.checked; };
        syncSubmitState();
        termsAccepted.addEventListener('change', syncSubmitState);
    }

    // =============================================
    // Address Construction
    // =============================================
    function constructAddress() {
        const streetType = document.getElementById('address_street_type')?.value || '';
        const streetNumber = document.getElementById('address_street_number')?.value || '';
        const streetComplement = document.getElementById('address_street_complement')?.value || '';
        const houseNumber = document.getElementById('address_house_number')?.value || '';
        const houseComplement = document.getElementById('address_house_complement')?.value || '';
        const references = document.getElementById('address_references')?.value || '';

        const parts = [];
        if (streetType && streetNumber) {
            let main = `${streetType} ${streetNumber}`;
            if (streetComplement) main += ` ${streetComplement}`;
            parts.push(main);
        }
        if (houseNumber) {
            let house = `# ${houseNumber}`;
            if (houseComplement) house += ` ${houseComplement}`;
            parts.push(house);
        }
        if (references) parts.push(`Ref: ${references}`);

        const addressField = document.getElementById('reg_address');
        if (addressField) addressField.value = parts.join(', ');
    }

    ['address_street_type', 'address_street_number', 'address_street_complement',
     'address_house_number', 'address_house_complement', 'address_references'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.addEventListener('change', constructAddress); el.addEventListener('input', constructAddress); }
    });

    const form = document.getElementById('registration-form');
    if (form) {
        form.addEventListener('submit', function() { constructAddress(); });
    }

    // =============================================
    // Tab functionality for mobile
    // =============================================
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const loginSection = document.getElementById('login-section');
    const registerSection = document.getElementById('register-section');

    function switchToLogin() {
        loginTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        loginTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        registerTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
        registerTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        if (window.innerWidth < 1280) {
            loginSection.classList.remove('hidden');
            registerSection.classList.add('hidden');
        }
    }

    function switchToRegister() {
        registerTab.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        registerTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        loginTab.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
        loginTab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
        if (window.innerWidth < 1280) {
            registerSection.classList.remove('hidden');
            loginSection.classList.add('hidden');
        }
    }

    if (loginTab && registerTab) {
        loginTab.addEventListener('click', switchToLogin);
        registerTab.addEventListener('click', switchToRegister);
        if (window.innerWidth < 1280) switchToLogin();
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1280) {
                loginSection.classList.remove('hidden');
                registerSection.classList.remove('hidden');
            } else {
                if (loginTab.classList.contains('border-blue-500')) switchToLogin();
                else switchToRegister();
            }
        });
    }

    // =============================================
    // Magic Link Login
    // =============================================
    const magicLinkBtn = document.getElementById('magic-link-btn');
    const magicLinkIcon = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-icon') : null;
    const magicLinkSpinner = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-spinner') : null;
    const magicLinkTextEl = magicLinkBtn ? magicLinkBtn.querySelector('.magic-link-text') : null;
    const magicLinkHint = document.getElementById('magic-link-hint');
    const magicLinkError = document.getElementById('magic-link-error');
    const magicModal = document.getElementById('magic-link-modal');
    const magicModalClose = document.getElementById('magic-modal-close');
    const magicModalBackdrop = document.getElementById('magic-modal-backdrop');
    const magicModalEmail = document.getElementById('magic-modal-email');
    const magicCodeInput = document.getElementById('magic-code-input');
    const magicCodeError = document.getElementById('magic-code-error');
    const magicVerifyBtn = document.getElementById('magic-verify-btn');
    const magicResendBtn = document.getElementById('magic-resend-btn');
    const magicResendTimer = document.getElementById('magic-resend-timer');
    const magicLoading = document.getElementById('magic-loading');
    const loginEmailInput = document.getElementById('login-email');
    let magicLinkEmail = '';
    let resendCooldown = null;

    function setMagicBtnLoading(loading) {
        if (!magicLinkBtn) return;
        magicLinkBtn.disabled = loading;
        magicLinkBtn.classList.toggle('opacity-60', loading);
        magicLinkBtn.classList.toggle('cursor-not-allowed', loading);
        if (magicLinkIcon) magicLinkIcon.classList.toggle('hidden', loading);
        if (magicLinkSpinner) magicLinkSpinner.classList.toggle('hidden', !loading);
        if (magicLinkTextEl) magicLinkTextEl.textContent = loading ? 'Enviando código...' : 'Ingresar sin contraseña';
    }

    function showBtnError(message) {
        if (magicLinkError) { magicLinkError.textContent = message; magicLinkError.classList.remove('hidden'); }
        if (magicLinkHint) magicLinkHint.classList.add('hidden');
    }
    function hideBtnError() {
        if (magicLinkError) magicLinkError.classList.add('hidden');
        if (magicLinkHint) magicLinkHint.classList.remove('hidden');
    }
    function showMagicLoading(show) { magicLoading.classList.toggle('hidden', !show); }
    function showMagicError(msg) {
        magicCodeError.textContent = msg; magicCodeError.classList.remove('hidden');
        magicCodeInput.classList.add('border-red-500');
    }
    function hideMagicError() { magicCodeError.classList.add('hidden'); magicCodeInput.classList.remove('border-red-500'); }
    function openMagicModal() {
        magicModal.classList.remove('hidden'); document.body.style.overflow = 'hidden';
        magicCodeInput.value = ''; magicVerifyBtn.disabled = true; hideMagicError();
        setTimeout(() => magicCodeInput.focus(), 100);
    }
    function closeMagicModal() {
        magicModal.classList.add('hidden'); document.body.style.overflow = '';
        if (resendCooldown) { clearInterval(resendCooldown); resendCooldown = null; }
    }
    function startResendCooldown() {
        let seconds = 60;
        magicResendBtn.classList.add('hidden'); magicResendTimer.classList.remove('hidden');
        magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';
        resendCooldown = setInterval(() => {
            seconds--;
            if (seconds <= 0) { clearInterval(resendCooldown); resendCooldown = null; magicResendBtn.classList.remove('hidden'); magicResendTimer.classList.add('hidden'); }
            else magicResendTimer.textContent = 'Reenviar código en ' + seconds + 's';
        }, 1000);
    }

    async function sendMagicCode(email, showOnBtn) {
        if (showOnBtn) { setMagicBtnLoading(true); hideBtnError(); } else { showMagicLoading(true); }
        try {
            const response = await fetch('{{ route("magic-link.send") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ email }),
            });
            const data = await response.json();
            if (!response.ok) { const err = data.message || 'Error al enviar el código.'; if (showOnBtn) showBtnError(err); else showMagicError(err); return false; }
            return true;
        } catch (e) {
            const err = 'Error de conexión. Por favor intenta de nuevo.';
            if (showOnBtn) showBtnError(err); else showMagicError(err); return false;
        } finally {
            if (showOnBtn) setMagicBtnLoading(false); else showMagicLoading(false);
        }
    }

    async function verifyMagicCode(email, code) {
        showMagicLoading(true); hideMagicError();
        try {
            const response = await fetch('{{ route("magic-link.verify") }}', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ email, code }),
            });
            const data = await response.json();
            if (response.ok && data.success) { window.location.href = data.redirect || '/'; return; }
            showMagicError(data.message || 'Código inválido o expirado.');
        } catch (e) { showMagicError('Error de conexión.'); }
        finally { showMagicLoading(false); }
    }

    if (magicLinkBtn) {
        magicLinkBtn.addEventListener('click', async function() {
            const email = loginEmailInput?.value?.trim();
            if (!email || !email.includes('@')) {
                loginEmailInput.focus(); loginEmailInput.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                showBtnError('Por favor ingresa tu correo electrónico arriba.');
                setTimeout(() => loginEmailInput.classList.remove('border-red-500', 'ring-2', 'ring-red-200'), 3000);
                return;
            }
            hideBtnError(); magicLinkEmail = email; magicModalEmail.textContent = email;
            const sent = await sendMagicCode(email, true);
            if (sent) { openMagicModal(); startResendCooldown(); }
        });
    }

    if (magicCodeInput) {
        magicCodeInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            magicVerifyBtn.disabled = this.value.length !== 6;
            if (this.value.length > 0) hideMagicError();
        });
        magicCodeInput.addEventListener('keyup', function(e) { if (this.value.length === 6 && e.key !== 'Enter') magicVerifyBtn.click(); });
        magicCodeInput.addEventListener('keydown', function(e) { if (e.key === 'Enter' && this.value.length === 6) { e.preventDefault(); magicVerifyBtn.click(); } });
    }
    if (magicVerifyBtn) { magicVerifyBtn.addEventListener('click', function() { if (magicCodeInput.value.trim().length === 6 && magicLinkEmail) verifyMagicCode(magicLinkEmail, magicCodeInput.value.trim()); }); }
    if (magicResendBtn) { magicResendBtn.addEventListener('click', async function() { if (magicLinkEmail) { magicCodeInput.value = ''; magicVerifyBtn.disabled = true; hideMagicError(); const sent = await sendMagicCode(magicLinkEmail, false); if (sent) startResendCooldown(); } }); }
    if (magicModalClose) magicModalClose.addEventListener('click', closeMagicModal);
    if (magicModalBackdrop) magicModalBackdrop.addEventListener('click', closeMagicModal);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && !magicModal.classList.contains('hidden')) closeMagicModal(); });

    // =============================================
    // Tronex existing client
    // =============================================
    const tronexBtn = document.getElementById('tronex-btn');
    const tronexModal = document.getElementById('tronex-modal');
    const tronexModalClose = document.getElementById('tronex-modal-close');
    const tronexModalBackdrop = document.getElementById('tronex-modal-backdrop');
    const tronexCedulaInput = document.getElementById('tronex-cedula-input');
    const tronexPhoneInput = document.getElementById('tronex-phone-input');
    const tronexPhoneField = document.getElementById('tronex-phone-field');
    const tronexStepDescription = document.getElementById('tronex-step-description');
    const tronexSubmitBtn = document.getElementById('tronex-submit-btn');
    const tronexError = document.getElementById('tronex-error');
    const tronexLoading = document.getElementById('tronex-loading');
    let tronexStep = 'document';
    let tronexPendingDocument = '';

    function resetTronexFlow() {
        tronexStep = 'document';
        tronexPendingDocument = '';
        tronexCedulaInput.value = '';
        if (tronexPhoneInput) tronexPhoneInput.value = '';
        if (tronexPhoneField) tronexPhoneField.classList.add('hidden');
        if (tronexStepDescription) {
            tronexStepDescription.textContent = 'Ingresa tu número de cédula para continuar con la validación';
        }
        if (tronexSubmitBtn) {
            tronexSubmitBtn.textContent = 'Validar cédula';
            tronexSubmitBtn.disabled = false;
        }
        tronexError.classList.add('hidden');
        tronexError.textContent = '';
    }

    function moveToTronexPhoneStep(documentNumber) {
        tronexStep = 'phone';
        tronexPendingDocument = documentNumber;
        if (tronexPhoneField) tronexPhoneField.classList.remove('hidden');
        if (tronexStepDescription) {
            tronexStepDescription.textContent = 'Ingresa tu celular registrado para validar tu identidad.';
        }
        if (tronexSubmitBtn) {
            tronexSubmitBtn.textContent = 'Validar y continuar';
        }
        tronexError.classList.add('hidden');
        tronexError.textContent = '';
        setTimeout(() => tronexPhoneInput?.focus(), 100);
    }

    function openTronexModal() {
        if (tronexModal) {
            tronexModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            resetTronexFlow();
            setTimeout(() => tronexCedulaInput?.focus(), 100);
        }
    }
    function closeTronexModal() {
        if (tronexModal) {
            tronexModal.classList.add('hidden');
            document.body.style.overflow = '';
            resetTronexFlow();
        }
    }
    function showTronexError(msg) {
        if (tronexError) { tronexError.textContent = msg; tronexError.classList.remove('hidden'); }
        if (window.showToast) window.showToast(msg, 'error', 6000);
    }

    if (tronexBtn) tronexBtn.addEventListener('click', openTronexModal);
    if (tronexModalClose) tronexModalClose.addEventListener('click', closeTronexModal);
    if (tronexModalBackdrop) tronexModalBackdrop.addEventListener('click', closeTronexModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && tronexModal && !tronexModal.classList.contains('hidden')) closeTronexModal();
    });

    if (tronexSubmitBtn && tronexCedulaInput) {
        tronexSubmitBtn.addEventListener('click', async function() {
            const cedula = tronexStep === 'phone'
                ? tronexPendingDocument
                : tronexCedulaInput.value.trim().replace(/\D/g, '');
            const phone = tronexPhoneInput?.value?.trim() ?? '';

            if (tronexStep === 'document') {
                if (!cedula || cedula.length < 5) {
                    showTronexError('Ingresa un número de cédula válido.');
                    return;
                }
            } else {
                const normalizedPhone = phone.replace(/\D/g, '');
                if (!normalizedPhone || normalizedPhone.length < 7) {
                    showTronexError('Ingresa un número de celular válido.');
                    return;
                }
            }

            tronexError.classList.add('hidden');
            tronexLoading.classList.remove('hidden');
            tronexSubmitBtn.disabled = true;
            try {
                const response = await fetch('{{ route("tronex.migrate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        document: cedula,
                        ...(tronexStep === 'phone' ? { phone } : {}),
                    }),
                });
                const data = await response.json();
                if (data.success && data.requires_phone_verification) {
                    moveToTronexPhoneStep(cedula);
                    return;
                }
                if (data.success && (data.verified_redirect || data.redirect)) {
                    closeTronexModal();
                    window.location.href = data.verified_redirect || data.redirect;
                    return;
                }
                showTronexError(data.message || 'No se pudo crear la cuenta. Intenta de nuevo.');
            } catch (err) {
                showTronexError('Error de conexión. Intenta de nuevo.');
            } finally {
                tronexLoading.classList.add('hidden');
                tronexSubmitBtn.disabled = false;
            }
        });
        tronexCedulaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); tronexSubmitBtn.click(); }
        });
        if (tronexPhoneInput) {
            tronexPhoneInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); tronexSubmitBtn.click(); }
            });
        }
    }
});
</script>
@endsection
