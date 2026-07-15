<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\State;
use App\Models\User;
use App\Models\ZoneRoute;
use App\Rules\ValidClientEmail;
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

    public function create()
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

        return view('new-client.create', compact(
            'states',
            'tipoDocumentoOptions',
            'clasificacionOptions',
            'diaOptions',
            'layout',
            'isSellerFlow',
            'sellerZone',
            'zoneRoutes'
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

        [$primerNombre, $segundoNombre, $primerApellido, $segundoApellido] = $this->splitFullName((string) $client->name);

        return response()->json([
            'found' => true,
            'client' => [
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
            ],
        ]);
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
        $rutaRules = [$isSellerFlow ? 'required' : 'nullable', 'string', 'regex:/^\d{4}$/'];
        if ($isSellerFlow) {
            $rutaRules[] = Rule::exists('zone_routes', 'route')
                ->where(fn ($q) => $q->where('zone', strtoupper((string) $request->input('Zona'))));
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
            'Correo' => [$isSellerFlow ? 'nullable' : 'required', 'email', 'max:100', new ValidClientEmail()],
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
        if (!empty($validated['Zona'])) {
            $validated['Zona'] = strtoupper((string) $validated['Zona']);
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

        // "Agregar sucursal": the document must belong to an already registered client.
        $isSucursal = $isSellerFlow && $request->boolean('is_sucursal');
        if ($isSucursal) {
            $documentExists = User::query()
                ->where('document', preg_replace('/\D+/', '', $validated['Documento']))
                ->exists();

            if (! $documentExists) {
                return back()->withInput()->withErrors([
                    'Documento' => 'Para agregar una sucursal el documento debe pertenecer a un cliente existente.',
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

        // Persist signature locally for audit/traceability in seller flow as well.
        $this->storeSignatureForContact($signaturePdf, $storedDocumentPaths);

        // Step 1: Register client
        $result = $service->registerClient($validated);

        if (! $result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        $clientId = $result['id'];

        // Step 2: Upload signature PDF + optional images
        $images = array_values(array_filter(
            $request->file('documents') ?? [],
            fn ($file) => in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)
        ));
        $mediaResult = $service->uploadMedia($clientId, $signaturePdf, $images);

        @unlink($signaturePdf->getPathname());

        if (! $mediaResult['success']) {
            Log::warning('NewClient: client registered but media upload failed', [
                'client_id' => $clientId,
                'error' => $mediaResult['message'],
            ]);

            app(PendingClientProvisioningService::class)->provisionFromNewClient(
                $validated,
                $result['codigo_cliente'] ?? null,
                preserveExistingStatus: $isSucursal
            );

            return back()->with('warning',
                "Cliente registrado (Código: {$result['codigo_cliente']}), pero hubo un error al subir los archivos: {$mediaResult['message']}"
            );
        }

        $localClient = app(PendingClientProvisioningService::class)->provisionFromNewClient(
            $validated,
            $result['codigo_cliente'] ?? null,
            preserveExistingStatus: $isSucursal
        );

        $successMessage = $isSucursal
            ? "Sucursal registrada exitosamente. Código: {$result['codigo_cliente']}. Documento del cliente: {$localClient->document}"
            : "Cliente registrado exitosamente. Código: {$result['codigo_cliente']}. Documento para pedidos: {$localClient->document}";

        return redirect()->route('new-client.create')
            ->with('success', $successMessage);
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

        app(PendingClientProvisioningService::class)->provisionFromNewClient(
            $validated,
            null,
            \App\Models\User::CLIENT_STATUS_PROSPECTO
        );

        @unlink($signaturePdf->getPathname());

        return redirect()->route('new-client.create')->with(
            'success',
            'Solicitud recibida. Un administrador validara tus documentos y completara la activacion.'
        );
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

        return $user->zone ?: $user->zones()->orderBy('id')->value('zone');
    }
}
