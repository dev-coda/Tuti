<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\State;
use App\Models\User;
use App\Models\ZoneRoute;
use App\Rules\ValidClientEmail;
use App\Services\DraftOrderReconciliationService;
use App\Services\MailingService;
use App\Services\NewClientService;
use App\Services\PendingClientProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class NewClientController extends Controller
{
    private const TIPO_DOCUMENTO_OPTIONS = [
        1 => 'Cédula de Ciudadanía',
        2 => 'Cédula de Extranjería',
        3 => 'NIT',
        4 => 'Permiso por Protección Temporal (PPT)',
    ];

    /**
     * Free-text fields normalized to uppercase before storing/sending,
     * so every registration is recorded in capital letters.
     */
    private const UPPERCASE_FIELDS = [
        'PrimerNombre', 'SegundoNombre', 'PrimerApellido', 'SegundoApellido',
        'RazonSocial', 'NombreNegocio', 'Departamento', 'Ciudad',
        'Direccion', 'Barrio',
    ];

    private const CLASIFICACION_OPTIONS = [
        1 => 'Tienda de barrio',
        2 => 'Minimercado',
        3 => 'Papelería',
        4 => 'Droguería',
        5 => 'Ferretería',
        6 => 'Miscelánea',
        7 => 'Cacharrería',
        8 => 'Superette',
        9 => 'Supermercado',
        10 => 'Autoservicio',
        11 => 'Otro',
    ];

    private const DIA_OPTIONS = [
        'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES',
    ];

    public function create(Request $request)
    {
        $states = State::orderBy('name')->pluck('name', 'id');
        $tipoDocumentoOptions = self::TIPO_DOCUMENTO_OPTIONS;
        $clasificacionOptions = self::CLASIFICACION_OPTIONS;
        $diaOptions = self::DIA_OPTIONS;
        $isSellerFlow = $this->isSellerFlow();
        $sellerZone = $this->resolveSellerZone();
        $zoneRoutes = [];
        if ($isSellerFlow && $sellerZone) {
            $zoneRoutes = ZoneRoute::query()
                ->where('zone', $sellerZone)
                ->orderBy('route')
                ->pluck('route')
                ->all();
        }

        $layout = $this->resolveLayout();
        $returnTo = $request->query('return') === 'mi-ruta' ? 'mi-ruta' : null;
        $isSucursalMode = $isSellerFlow && (
            $request->query('mode') === 'sucursal'
            || old('is_sucursal') === '1'
        );
        $prefillClient = null;
        $prefillError = null;

        if ($isSellerFlow && $isSucursalMode && $request->filled('document')) {
            $document = preg_replace('/\D+/', '', (string) $request->query('document'));
            $client = User::query()->where('document', $document)->first();

            if (! $client) {
                $prefillError = 'No encontramos un cliente con ese documento.';
            } elseif (! $this->sellerCoversClient($client)) {
                $prefillError = 'No tienes cobertura sobre este cliente para agregar una sucursal.';
            } else {
                $prefillClient = $this->clientPrefillPayload($client);
            }
        }

        return view('new-client.create', compact(
            'states',
            'tipoDocumentoOptions',
            'clasificacionOptions',
            'diaOptions',
            'layout',
            'isSellerFlow',
            'sellerZone',
            'zoneRoutes',
            'returnTo',
            'isSucursalMode',
            'prefillClient',
            'prefillError'
        ));
    }

    /**
     * Lookup an existing client by document to prefill the "Agregar sucursal" mode.
     * Only non address/route/zone data is returned: the new sucursal needs its own.
     */
    public function existingClient(Request $request)
    {
        $validated = $request->validate([
            'document' => ['required', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
        ]);

        $client = User::query()
            ->where('document', preg_replace('/\D+/', '', $validated['document']))
            ->first();

        if (! $client) {
            return response()->json([
                'found' => false,
                'message' => 'No encontramos un cliente con ese documento.',
            ], 404);
        }

        if (! $this->sellerCoversClient($client)) {
            return response()->json([
                'found' => false,
                'message' => 'No tienes cobertura sobre este cliente para agregar una sucursal.',
            ], 403);
        }

        return response()->json([
            'found' => true,
            'client' => $this->clientPrefillPayload($client),
        ]);
    }

    /**
     * Identity/contact fields reused for "Agregar sucursal" prefill (JSON + deep-link).
     *
     * @return array<string, mixed>
     */
    private function clientPrefillPayload(User $client): array
    {
        [$primerNombre, $segundoNombre, $primerApellido, $segundoApellido] = $this->splitFullName((string) $client->name);

        return [
            'Documento' => $client->document,
            'RazonSocial' => $client->name,
            'NombreNegocio' => $client->business_name ?: $client->name,
            'PrimerNombre' => $primerNombre,
            'SegundoNombre' => $segundoNombre,
            'PrimerApellido' => $primerApellido,
            'SegundoApellido' => $segundoApellido,
            'Telefono' => $client->phone,
            'Movil' => $client->mobile_phone,
            'Whatsapp' => $client->whatsapp,
            'Correo' => str_ends_with((string) $client->email, '@tuti.com') ? null : $client->email,
        ];
    }

    /**
     * Sellers/supervisors may only add sucursales for clients that already have a zone
     * in one of their supervised zonas.
     */
    private function sellerCoversClient(User $client): bool
    {
        $seller = auth()->user();
        if (! $seller) {
            return false;
        }

        if ($seller->hasRole('admin')) {
            return true;
        }

        $allowedZones = $seller->supervisedZones();
        if ($allowedZones === []) {
            return false;
        }

        return $client->zones()
            ->whereIn('zone', $allowedZones)
            ->exists();
    }

    /**
     * Best-effort split of a full name into [PrimerNombre, SegundoNombre, PrimerApellido, SegundoApellido].
     * Only a prefill aid — the seller reviews and corrects before submitting.
     *
     * @return array{0:?string,1:?string,2:?string,3:?string}
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

        return match (true) {
            count($parts) === 0 => [null, null, null, null],
            count($parts) === 1 => [$parts[0], null, null, null],
            count($parts) === 2 => [$parts[0], null, $parts[1], null],
            count($parts) === 3 => [$parts[0], null, $parts[1], $parts[2]],
            default => [$parts[0], $parts[1], $parts[2], implode(' ', array_slice($parts, 3))],
        };
    }

    public function store(Request $request, NewClientService $service)
    {
        $isSellerFlow = $this->isSellerFlow();
        $sellerZone = $isSellerFlow ? $this->resolveSellerZone() : null;

        // Sellers/supervisors must always register into their assigned zone.
        // Force it server-side so the API payload cannot omit or override it.
        if ($isSellerFlow) {
            if ($sellerZone === null) {
                return back()->withInput()->withErrors([
                    'Zona' => 'Tu usuario no tiene una zona asignada. Contacta a un administrador.',
                ]);
            }
            $request->merge(['Zona' => $sellerZone]);
        }

        $rutaRules = [$isSellerFlow ? 'required' : 'nullable', 'string', 'regex:/^\d{4}$/'];
        if ($isSellerFlow) {
            $rutaRules[] = Rule::exists('zone_routes', 'route')
                ->where(fn ($q) => $q->where('zone', $sellerZone));
        }

        $validated = $request->validate([
            'Documento' => ['required', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'TipoDocumento' => ['required', 'integer', Rule::in(array_keys(self::TIPO_DOCUMENTO_OPTIONS))],
            'PrimerNombre' => ['nullable', 'string', 'max:50', 'required_if:TipoDocumento,1,2,4'],
            'SegundoNombre' => ['nullable', 'string', 'max:50'],
            'PrimerApellido' => ['nullable', 'string', 'max:50', 'required_if:TipoDocumento,1,2,4'],
            'SegundoApellido' => ['nullable', 'string', 'max:50'],
            'RazonSocial' => ['required', 'string', 'max:100'],
            'NombreNegocio' => ['required', 'string', 'max:100'],
            'IdClasificacionCliente' => ['required', 'integer', Rule::in(array_keys(self::CLASIFICACION_OPTIONS))],
            'Departamento' => ['required', 'string', 'max:100'],
            'Ciudad' => ['required', 'string', 'max:100'],
            'Telefono' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'Movil' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'Whatsapp' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'Correo' => [
                'required',
                'email',
                'max:100',
                new ValidClientEmail(),
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $document = preg_replace('/\D+/', '', (string) $request->input('Documento'));
                    $owner = User::query()->whereEmailCaseInsensitive($value)->first();
                    if ($owner && (string) $owner->document !== (string) $document) {
                        $fail('Este correo ya está registrado en otra cuenta.');
                    }
                },
            ],
            'Direccion' => ['required', 'string', 'max:100'],
            'Barrio' => ['required', 'string', 'max:100'],
            'Zona' => [$isSellerFlow ? 'required' : 'nullable', 'string', 'max:3'],
            'RutaZonaVentas' => $rutaRules,
            'DiaRecorrido' => [$isSellerFlow ? 'required' : 'nullable', 'string', Rule::in(self::DIA_OPTIONS)],
            'Posicion' => [$isSellerFlow ? 'required' : 'nullable', 'integer', 'min:1'],
            'Pep' => ['required', 'string', Rule::in(['SI', 'NO'])],
            'is_sucursal' => ['nullable', 'boolean'],

            'signature' => ['required', 'string'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            // The signature is captured separately and is NOT a valid attachment:
            // at least one real document (cédula, RUT, etc.) must be uploaded.
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'documents.required' => 'Debes adjuntar al menos un documento. La firma no es válida como documento adjunto.',
            'documents.min' => 'Debes adjuntar al menos un documento. La firma no es válida como documento adjunto.',
            'privacy_accepted.required' => 'Debes aceptar la política de privacidad y tratamiento de datos personales.',
            'privacy_accepted.accepted' => 'Debes aceptar la política de privacidad y tratamiento de datos personales.',
        ]);

        if ($isSellerFlow) {
            $validated['Zona'] = $sellerZone;
        } elseif (! empty($validated['Zona'])) {
            $validated['Zona'] = strtoupper(trim((string) $validated['Zona']));
        }

        // Register everything in uppercase.
        foreach (self::UPPERCASE_FIELDS as $field) {
            if (isset($validated[$field]) && is_string($validated[$field])) {
                $validated[$field] = mb_strtoupper($validated[$field], 'UTF-8');
            }
        }

        // NITs are registered without the verification digit (e.g. "900123456-7" -> "900123456").
        if ((int) $validated['TipoDocumento'] === 3) {
            $validated['Documento'] = preg_replace('/-.*$/', '', $validated['Documento']);
        }

        // "Agregar sucursal": the document must belong to an already registered client
        // that the seller covers (zone overlap with supervisedZones).
        $isSucursal = $isSellerFlow && $request->boolean('is_sucursal');
        if ($isSucursal) {
            $existingClient = User::query()
                ->where('document', preg_replace('/\D+/', '', $validated['Documento']))
                ->first();

            if (! $existingClient) {
                return back()->withInput()->withErrors([
                    'Documento' => 'Para agregar una sucursal el documento debe pertenecer a un cliente existente.',
                ]);
            }

            if (! $this->sellerCoversClient($existingClient)) {
                return back()->withInput()->withErrors([
                    'Documento' => 'No tienes cobertura sobre este cliente para agregar una sucursal.',
                ]);
            }
        }

        $hasContact = ! empty($validated['Telefono']) || ! empty($validated['Movil']) || ! empty($validated['Whatsapp']);
        if (! $hasContact) {
            return back()->withInput()->withErrors([
                'Telefono' => 'Debe proporcionar al menos un número de contacto (Teléfono, Móvil o WhatsApp).',
            ]);
        }

        $maxDocuments = ((int) $validated['TipoDocumento']) === 3 ? 6 : 2;
        $documentsCount = count($request->file('documents') ?? []);
        if ($documentsCount > $maxDocuments) {
            return back()->withInput()->withErrors([
                'documents' => "Puedes adjuntar maximo {$maxDocuments} archivos para este tipo de cliente.",
            ]);
        }

        $signaturePdf = $this->convertSignatureToPdf($validated['signature'], $validated);
        if (! $signaturePdf) {
            return back()->withInput()->withErrors([
                'signature' => 'No se pudo procesar la firma. Intente de nuevo.',
            ]);
        }

        $storedDocumentPaths = $this->storeUploadedDocuments($request);

        if (! $isSellerFlow) {
            return $this->storeAsInteresado($validated, $signaturePdf, $storedDocumentPaths);
        }

        // Persist signature + uploads so admins can view them on the client details screen.
        $documentPaths = $this->storeSignatureForContact($signaturePdf, $storedDocumentPaths);

        // Step 1: Register client
        $result = $service->registerClient($validated);

        if (! $result['success']) {
            $this->persistSellerRegistrationDocuments($validated, $documentPaths);

            return back()->withInput()->with('error', $result['message']);
        }

        $clientId = $result['id'];

        // Step 2: Upload signature PDF + all registration attachments (images and PDFs).
        $attachments = array_values(array_filter(
            $request->file('documents') ?? [],
            fn ($file) => in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'pdf'], true)
        ));
        $mediaResult = $service->uploadMedia($clientId, $signaturePdf, $attachments);

        @unlink($signaturePdf->getPathname());

        $this->persistSellerRegistrationDocuments(
            $validated,
            $documentPaths,
            (int) ($result['id'] ?? 0) ?: null,
            $result['codigo_cliente'] ?? null,
        );

        if (! $mediaResult['success']) {
            Log::warning('NewClient: client registered but media upload failed', [
                'client_id' => $clientId,
                'error' => $mediaResult['message'],
            ]);

            $localClient = app(PendingClientProvisioningService::class)->provisionFromNewClient(
                $validated,
                $result['codigo_cliente'] ?? null,
                preserveExistingStatus: $isSucursal
            );
            $this->attemptPostCreateRuteroSync($localClient);
            if (! $isSucursal) {
                $this->sendRegistrationInvite($localClient);
            }

            return $this->redirectAfterSellerRegistration(
                $request,
                "Cliente registrado (Código: {$result['codigo_cliente']}), pero hubo un error al subir los archivos: {$mediaResult['message']}",
                flashKey: 'warning'
            );
        }

        $localClient = app(PendingClientProvisioningService::class)->provisionFromNewClient(
            $validated,
            $result['codigo_cliente'] ?? null,
            preserveExistingStatus: $isSucursal
        );
        $this->attemptPostCreateRuteroSync($localClient);
        if (! $isSucursal) {
            $this->sendRegistrationInvite($localClient);
        }

        $successMessage = $isSucursal
            ? "Sucursal registrada exitosamente. Código: {$result['codigo_cliente']}. Documento del cliente: {$localClient->document}"
            : "Cliente registrado exitosamente. Código: {$result['codigo_cliente']}. Documento para pedidos: {$localClient->document}";

        return $this->redirectAfterSellerRegistration($request, $successMessage);
    }

    /**
     * After seller registration, optionally return to Mi Cuenta → Mi Ruta
     * when the form was opened from that surface.
     */
    private function redirectAfterSellerRegistration(Request $request, string $message, string $flashKey = 'success')
    {
        if ($request->input('return') === 'mi-ruta') {
            return redirect()
                ->route('clients.orders.index', ['tab' => 'mi-ruta'])
                ->with($flashKey, $message);
        }

        return redirect()->route('new-client.create')->with($flashKey, $message);
    }

    /**
     * Best-effort rutero sync after ClienteNuevo registration.
     * Fresh clients often are not in Dynamics yet — failures are expected and non-blocking.
     */
    private function attemptPostCreateRuteroSync(User $user): void
    {
        try {
            $result = app(DraftOrderReconciliationService::class)->syncUserFromRutero(
                $user,
                promoteIfPossible: true,
                transmitDrafts: false,
            );

            Log::info('NewClient: post-create rutero sync attempted', [
                'user_id' => $user->id,
                'document' => $user->document,
                'synced' => $result['synced'],
                'promoted' => $result['promoted'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('NewClient: post-create rutero sync failed', [
                'user_id' => $user->id,
                'document' => $user->document,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function storeAsInteresado(array $validated, UploadedFile $signaturePdf, array $storedDocumentPaths)
    {
        $documentPaths = $this->storeSignatureForContact($signaturePdf, $storedDocumentPaths);
        $payloadForReview = collect($validated)
            ->except(['signature', 'documents'])
            ->toArray();

        $fullName = trim(implode(' ', array_filter([
            $validated['PrimerNombre'] ?? '',
            $validated['SegundoNombre'] ?? '',
            $validated['PrimerApellido'] ?? '',
            $validated['SegundoApellido'] ?? '',
        ])));
        if ($fullName === '') {
            $fullName = $validated['NombreNegocio'];
        }

        $phone = $validated['Movil'] ?: ($validated['Whatsapp'] ?: ($validated['Telefono'] ?? null));
        $personType = ((int) $validated['TipoDocumento']) === 3 ? 'juridica' : 'natural';

        Contact::create([
            'person_type' => $personType,
            'name' => $fullName,
            'business_name' => $validated['NombreNegocio'],
            'email' => $validated['Correo'] ?? null,
            'phone' => $phone,
            'department' => $validated['Departamento'],
            'city' => $validated['Ciudad'],
            'address' => $validated['Direccion'],
            'nit' => $validated['Documento'],
            'terms_accepted' => (bool) ($validated['terms_accepted'] ?? false),
            'documents' => $documentPaths,
            'status' => 'interesado',
            'new_client_mode' => 'self_service',
            'new_client_payload' => $payloadForReview,
        ]);

        $localClient = app(PendingClientProvisioningService::class)->provisionFromNewClient(
            $validated,
            null,
            \App\Models\User::CLIENT_STATUS_PROSPECTO
        );
        $this->sendRegistrationInvite($localClient);

        @unlink($signaturePdf->getPathname());

        return redirect()->route('new-client.create')->with(
            'success',
            'Solicitud recibida. Un administrador validara tus documentos y completara la activacion.'
        );
    }

    /**
     * Best-effort password-setup invite after registration (never blocks the flow).
     */
    private function sendRegistrationInvite(User $user): void
    {
        try {
            app(MailingService::class)->sendClientRegistrationInviteEmail($user);
        } catch (\Throwable $e) {
            Log::warning('NewClient: registration invite email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist registration files on contacts.documents so admin client screens can resolve them.
     * Uses withoutEvents to avoid the interesado notification email (seller already submitted to Activity).
     */
    private function persistSellerRegistrationDocuments(
        array $validated,
        array $documentPaths,
        ?int $externalClientId = null,
        ?string $externalClientCode = null,
    ): void {
        $nit = trim((string) ($validated['Documento'] ?? ''));
        if ($nit === '' || $documentPaths === []) {
            return;
        }

        $fullName = trim(implode(' ', array_filter([
            $validated['PrimerNombre'] ?? '',
            $validated['SegundoNombre'] ?? '',
            $validated['PrimerApellido'] ?? '',
            $validated['SegundoApellido'] ?? '',
        ])));
        if ($fullName === '') {
            $fullName = (string) ($validated['NombreNegocio'] ?? $nit);
        }

        $phone = $validated['Movil'] ?: ($validated['Whatsapp'] ?: ($validated['Telefono'] ?? null));
        $personType = ((int) ($validated['TipoDocumento'] ?? 0)) === 3 ? 'juridica' : 'natural';
        $email = trim((string) ($validated['Correo'] ?? ''));
        if ($email === '') {
            $email = 'seller_registro_'.$nit.'@tuti.local';
        }

        Contact::withoutEvents(function () use (
            $nit,
            $documentPaths,
            $validated,
            $fullName,
            $phone,
            $personType,
            $email,
            $externalClientId,
            $externalClientCode,
        ) {
            $existing = Contact::query()->where('nit', $nit)->orderByDesc('id')->first();
            if ($existing) {
                $merged = array_values(array_unique(array_merge(
                    is_array($existing->documents) ? $existing->documents : [],
                    $documentPaths
                )));
                $updates = ['documents' => $merged];
                if ($externalClientId) {
                    $updates['external_client_id'] = $externalClientId;
                    $updates['external_submitted_at'] = now();
                    $updates['status'] = 'creado';
                }
                if ($externalClientCode) {
                    $updates['external_client_code'] = $externalClientCode;
                }
                $existing->update($updates);

                return;
            }

            Contact::create([
                'person_type' => $personType,
                'name' => $fullName,
                'business_name' => $validated['NombreNegocio'] ?? null,
                'email' => $email,
                'phone' => $phone,
                'department' => $validated['Departamento'] ?? null,
                'city' => $validated['Ciudad'] ?? null,
                'address' => $validated['Direccion'] ?? null,
                'nit' => $nit,
                'terms_accepted' => (bool) ($validated['terms_accepted'] ?? false),
                'documents' => $documentPaths,
                'status' => $externalClientCode || $externalClientId ? 'creado' : 'interesado',
                'new_client_mode' => 'seller',
                'new_client_payload' => collect($validated)->except(['signature', 'documents'])->toArray(),
                'external_client_id' => $externalClientId,
                'external_client_code' => $externalClientCode,
                'external_submitted_at' => ($externalClientId || $externalClientCode) ? now() : null,
            ]);
        });
    }

    /**
     * @return array<int, string>
     */
    private function storeUploadedDocuments(Request $request): array
    {
        $paths = [];
        foreach (($request->file('documents') ?? []) as $document) {
            $paths[] = $document->store('contact-documents/new-client-documents', 'public');
        }

        return $paths;
    }

    private function storeSignatureForContact(UploadedFile $signaturePdf, array $existingPaths = []): array
    {
        $paths = $existingPaths;
        $signatureFilename = 'signature_'.now()->format('YmdHis').'_'.uniqid().'.pdf';
        $signaturePath = 'contact-documents/signatures/'.$signatureFilename;
        Storage::disk('public')->put($signaturePath, $signaturePdf->getContent());
        $paths[] = $signaturePath;

        return $paths;
    }

    /**
     * Convert a base64 data-URL signature image into a temporary UploadedFile containing a PDF.
     *
     * Uses pure-PHP PDF generation (no external library). The resulting single-page PDF
     * contains the habeas data authorization text and the signature together, identifying
     * the signer (legal representative or substitute).
     */
    private function convertSignatureToPdf(string $signatureDataUrl, array $validated = []): ?\Illuminate\Http\UploadedFile
    {
        try {
            if (! preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $signatureDataUrl)) {
                return null;
            }

            $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $signatureDataUrl));
            if ($imageData === false || strlen($imageData) < 100) {
                return null;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->buffer($imageData);
            if (! in_array($detectedMime, ['image/png', 'image/jpeg'])) {
                Log::warning('NewClient: signature MIME mismatch', ['detected' => $detectedMime]);

                return null;
            }

            $image = @imagecreatefromstring($imageData);
            if ($image === false) {
                return null;
            }

            $imgWidth = imagesx($image);
            $imgHeight = imagesy($image);

            // Render onto a white background so transparent PNGs come out clean
            $canvas = imagecreatetruecolor($imgWidth, $imgHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopy($canvas, $image, 0, 0, 0, 0, $imgWidth, $imgHeight);
            imagedestroy($image);

            ob_start();
            imagejpeg($canvas, null, 90);
            $jpegData = ob_get_clean();
            imagedestroy($canvas);

            $pdfContent = $this->buildMinimalPdfWithJpeg($jpegData, $imgWidth, $imgHeight, $this->habeasDataLines($validated));

            $tmpPdfPath = tempnam(sys_get_temp_dir(), 'sig_pdf_').'.pdf';
            file_put_contents($tmpPdfPath, $pdfContent);

            return new \Illuminate\Http\UploadedFile(
                $tmpPdfPath,
                'firma.pdf',
                'application/pdf',
                null,
                true
            );
        } catch (\Throwable $e) {
            Log::error('NewClient: signature PDF conversion failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Lines of the habeas data authorization included in the signature PDF.
     * First entry is rendered as a bold title.
     *
     * @return array<int, string>
     */
    private function habeasDataLines(array $validated): array
    {
        $signerName = trim(implode(' ', array_filter([
            $validated['PrimerNombre'] ?? '',
            $validated['SegundoNombre'] ?? '',
            $validated['PrimerApellido'] ?? '',
            $validated['SegundoApellido'] ?? '',
        ])));
        $razonSocial = trim((string) ($validated['RazonSocial'] ?? ''));
        $documento = trim((string) ($validated['Documento'] ?? ''));

        return [
            'AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES (HABEAS DATA)',
            '',
            'De manera libre, previa, expresa e informada, autorizo a TUTI / TRONEX para',
            'recolectar, almacenar, usar y tratar mis datos personales conforme a la',
            'Ley 1581 de 2012 y sus decretos reglamentarios, de acuerdo con la política',
            'de privacidad y tratamiento de datos personales publicada por la compañía.',
            '',
            'Declaro que acepto los términos y condiciones y la política de privacidad',
            'y tratamiento de datos personales.',
            '',
            'Razón social: '.($razonSocial !== '' ? $razonSocial : '-'),
            'Documento: '.($documento !== '' ? $documento : '-'),
            'Firmante (representante legal o suplente): '.($signerName !== '' ? $signerName : $razonSocial),
            'Fecha: '.now()->format('d/m/Y H:i'),
            '',
            'Firma del representante legal o suplente:',
        ];
    }

    /**
     * Escape and encode a text line for a PDF literal string (WinAnsi).
     */
    private function pdfTextLine(string $text): string
    {
        $encoded = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    /**
     * Build a minimal valid PDF 1.4 file containing the habeas data text block
     * followed by the signature JPEG, all on a single page.
     *
     * @param  array<int, string>  $textLines
     */
    private function buildMinimalPdfWithJpeg(string $jpegData, int $imgWidth, int $imgHeight, array $textLines = []): string
    {
        $pageWidth = 612;  // Letter width in points
        $pageHeight = 792; // Letter height in points
        $margin = 36;
        $fontSize = 11;
        $lineHeight = 16;

        // Text block starts at the top; the signature image goes right below it.
        $textStream = '';
        $cursorY = $pageHeight - $margin - $fontSize;
        foreach ($textLines as $index => $line) {
            if (trim($line) !== '') {
                $font = $index === 0 ? '/F2' : '/F1';
                $textStream .= sprintf(
                    "BT {$font} %d Tf %.2f %.2f Td (%s) Tj ET\n",
                    $fontSize,
                    (float) $margin,
                    $cursorY,
                    $this->pdfTextLine($line)
                );
            }
            $cursorY -= $lineHeight;
        }

        $displayWidth = $pageWidth - (2 * $margin);
        $scale = $displayWidth / $imgWidth;
        $availableHeight = max($cursorY - $margin, 60);
        $displayHeight = min($imgHeight * $scale, $availableHeight);
        $displayWidth = min($displayWidth, $displayHeight / $imgHeight * $imgWidth);

        $yPos = $cursorY - $displayHeight;

        $jpegLen = strlen($jpegData);

        $offsets = [];
        $pdf = "%PDF-1.4\n";

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}]"
            ." /Contents 4 0 R /Resources << /XObject << /Img0 5 0 R >>"
            ." /Font << /F1 6 0 R /F2 7 0 R >> >> >>\nendobj\n";

        $stream = $textStream
            .sprintf("q %.4f 0 0 %.4f %.4f %.4f cm /Img0 Do Q", $displayWidth, $displayHeight, (float) $margin, $yPos);
        $streamLen = strlen($stream);
        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imgWidth} /Height {$imgHeight}"
            ." /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$jpegLen} >>\n"
            ."stream\n";
        $pdf .= $jpegData;
        $pdf .= "\nendstream\nendobj\n";

        $offsets[6] = strlen($pdf);
        $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[7] = strlen($pdf);
        $pdf .= "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $xrefOffset = strlen($pdf);
        $numObjects = count($offsets) + 1;
        $pdf .= "xref\n0 {$numObjects}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $numObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$numObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function resolveLayout(): string
    {
        $user = auth()->user();
        if ($user && $user->hasRole('admin')) {
            return 'layouts.admin';
        }

        return 'layouts.page';
    }

    private function isSellerFlow(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['seller', 'supervisor']);
    }

    private function resolveSellerZone(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $candidates = [
            $user->zone,
            $user->supervisorRoutes()->orderBy('id')->value('zone'),
            $user->zones()->orderBy('id')->value('zone'),
        ];

        foreach ($candidates as $candidate) {
            $zone = strtoupper(trim((string) ($candidate ?? '')));
            if ($zone !== '') {
                return substr($zone, 0, 3);
            }
        }

        return null;
    }
}
