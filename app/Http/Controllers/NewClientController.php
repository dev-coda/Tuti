<?php

namespace App\Http\Controllers;

use App\Models\State;
use App\Services\NewClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NewClientController extends Controller
{
    private const TIPO_DOCUMENTO_OPTIONS = [
        1 => 'Cédula de Ciudadanía',
        2 => 'Cédula de Extranjería',
        3 => 'NIT',
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
        'LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO',
    ];

    public function create()
    {
        $states = State::orderBy('name')->pluck('name', 'id');
        $tipoDocumentoOptions = self::TIPO_DOCUMENTO_OPTIONS;
        $clasificacionOptions = self::CLASIFICACION_OPTIONS;
        $diaOptions = self::DIA_OPTIONS;

        $layout = $this->resolveLayout();

        return view('new-client.create', compact(
            'states', 'tipoDocumentoOptions', 'clasificacionOptions', 'diaOptions', 'layout'
        ));
    }

    public function store(Request $request, NewClientService $service)
    {
        $validated = $request->validate([
            'Documento' => ['required', 'string', 'max:20', 'regex:/^[0-9\-]+$/'],
            'TipoDocumento' => ['required', 'integer', Rule::in(array_keys(self::TIPO_DOCUMENTO_OPTIONS))],
            'PrimerNombre' => ['nullable', 'string', 'max:50', 'required_if:TipoDocumento,1,2'],
            'SegundoNombre' => ['nullable', 'string', 'max:50'],
            'PrimerApellido' => ['nullable', 'string', 'max:50', 'required_if:TipoDocumento,1,2'],
            'SegundoApellido' => ['nullable', 'string', 'max:50'],
            'NombreNegocio' => ['required', 'string', 'max:100'],
            'IdClasificacionCliente' => ['required', 'integer', Rule::in(array_keys(self::CLASIFICACION_OPTIONS))],
            'Departamento' => ['required', 'string', 'max:100'],
            'Ciudad' => ['required', 'string', 'max:100'],
            'Telefono' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'Movil' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'Whatsapp' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'Correo' => ['nullable', 'email', 'max:100'],
            'Direccion' => ['required', 'string', 'max:100'],
            'Barrio' => ['required', 'string', 'max:100'],
            'Zona' => ['required', 'string', 'max:3'],
            'RutaZonaVentas' => ['required', 'string', 'max:100'],
            'DiaRecorrido' => ['required', 'string', Rule::in(self::DIA_OPTIONS)],
            'Posicion' => ['required', 'integer', 'min:1'],
            'Pep' => ['required', 'string', Rule::in(['SI', 'NO'])],

            'signature' => ['required', 'string'],
            'imagenes' => ['nullable', 'array', 'max:3'],
            'imagenes.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $hasContact = ! empty($validated['Telefono']) || ! empty($validated['Movil']) || ! empty($validated['Whatsapp']);
        if (! $hasContact) {
            return back()->withInput()->withErrors([
                'Telefono' => 'Debe proporcionar al menos un número de contacto (Teléfono, Móvil o WhatsApp).',
            ]);
        }

        $signaturePdf = $this->convertSignatureToPdf($validated['signature']);
        if (! $signaturePdf) {
            return back()->withInput()->withErrors([
                'signature' => 'No se pudo procesar la firma. Intente de nuevo.',
            ]);
        }

        // Step 1: Register client
        $result = $service->registerClient($validated);

        if (! $result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        $clientId = $result['id'];

        // Step 2: Upload signature PDF + optional images
        $images = $request->file('imagenes') ?? [];
        $mediaResult = $service->uploadMedia($clientId, $signaturePdf, $images);

        @unlink($signaturePdf->getPathname());

        if (! $mediaResult['success']) {
            Log::warning('NewClient: client registered but media upload failed', [
                'client_id' => $clientId,
                'error' => $mediaResult['message'],
            ]);

            return back()->with('warning',
                "Cliente registrado (Código: {$result['codigo_cliente']}), pero hubo un error al subir los archivos: {$mediaResult['message']}"
            );
        }

        return redirect()->route('new-client.create')
            ->with('success', "Cliente registrado exitosamente. Código: {$result['codigo_cliente']}");
    }

    /**
     * Convert a base64 data-URL signature image into a temporary UploadedFile containing a PDF.
     *
     * Uses pure-PHP PDF generation (no external library) to embed a JPEG version of the
     * signature canvas into a single-page PDF document.
     */
    private function convertSignatureToPdf(string $signatureDataUrl): ?\Illuminate\Http\UploadedFile
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

            $pdfContent = $this->buildMinimalPdfWithJpeg($jpegData, $imgWidth, $imgHeight);

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
     * Build a minimal valid PDF 1.4 file containing a single JPEG image.
     */
    private function buildMinimalPdfWithJpeg(string $jpegData, int $imgWidth, int $imgHeight): string
    {
        $pageWidth = 612;  // Letter width in points
        $pageHeight = 792; // Letter height in points
        $margin = 36;

        $displayWidth = $pageWidth - (2 * $margin);
        $scale = $displayWidth / $imgWidth;
        $displayHeight = min($imgHeight * $scale, $pageHeight - (2 * $margin));

        $yPos = $pageHeight - $margin - $displayHeight;

        $jpegLen = strlen($jpegData);

        $offsets = [];
        $pdf = "%PDF-1.4\n";

        $offsets[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}]"
            ." /Contents 4 0 R /Resources << /XObject << /Img0 5 0 R >> >> >>\nendobj\n";

        $stream = sprintf("q %.4f 0 0 %.4f %.4f %.4f cm /Img0 Do Q", $displayWidth, $displayHeight, (float) $margin, $yPos);
        $streamLen = strlen($stream);
        $offsets[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream\nendobj\n";

        $offsets[5] = strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imgWidth} /Height {$imgHeight}"
            ." /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$jpegLen} >>\n"
            ."stream\n";
        $pdf .= $jpegData;
        $pdf .= "\nendstream\nendobj\n";

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
}
