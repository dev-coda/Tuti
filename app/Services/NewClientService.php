<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NewClientService
{
    private string $baseUrl;

    private string $staticToken;

    /**
     * @var array{url:?string,username:?string,password:?string,username_field:string,password_field:string,token_field:string,ttl_seconds:int}
     */
    private array $authConfig;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('cliente_nuevo.base_url'), '/');
        $this->staticToken = trim((string) config('cliente_nuevo.token'));
        $this->authConfig = [
            'url' => config('cliente_nuevo.auth.url'),
            'username' => config('cliente_nuevo.auth.username'),
            'password' => config('cliente_nuevo.auth.password'),
            'username_field' => (string) config('cliente_nuevo.auth.username_field', 'username'),
            'password_field' => (string) config('cliente_nuevo.auth.password_field', 'password'),
            'token_field' => (string) config('cliente_nuevo.auth.token_field', 'token'),
            'ttl_seconds' => (int) config('cliente_nuevo.auth.ttl_seconds', 3300),
        ];
    }

    /**
     * Step 1: Register the client by sending XML to AuthClienteNuevo.
     *
     * @return array{success: bool, id?: int, codigo_cliente?: string, message: string, raw?: array}
     */
    public function registerClient(array $data): array
    {
        $xml = $this->buildClientXml($data);

        Log::info('NewClientService: sending client registration', [
            'url' => $this->baseUrl.'/AuthClienteNuevo/clienteNuevo',
            'xml_length' => strlen($xml),
        ]);

        try {
            $response = $this->sendWithTokenRetry(function (string $token) use ($xml) {
                return Http::withHeaders([
                    'Content-Type' => 'application/xml',
                    'Tokenconectat' => $token,
                ])->withBody($xml, 'application/xml')
                    ->timeout(30)
                    ->post($this->baseUrl.'/AuthClienteNuevo/clienteNuevo');
            });

            $body = $response->json() ?? [];

            if ($response->successful() && ($body['Success'] ?? '') === 'OK') {
                return [
                    'success' => true,
                    'id' => (int) ($body['id'] ?? 0),
                    'codigo_cliente' => $body['CodigoCliente'] ?? '',
                    'message' => $body['Mensaje'] ?? 'Cliente registrado exitosamente.',
                    'raw' => $body,
                ];
            }

            $errorMessage = $body['Mensaje'] ?? $body['message'] ?? $response->body();

            Log::warning('NewClientService: registration failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => "Error del servidor ({$response->status()}): {$errorMessage}",
            ];
        } catch (\Throwable $e) {
            Log::error('NewClientService: registration exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servicio externo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Step 2: Upload the signature PDF and registration photos.
     *
     * Production AuthMediaClienteNuevo/cargaArchivos accepts one `pdf` plus
     * at most three `imagenes[]` (jpeg/png). More than three images, or sending
     * the `imagenes` field when there are none, rejects the whole request —
     * including the signature — with HTTP 406. Document PDFs stay in TUTI;
     * this endpoint does not store them.
     *
     * @param  UploadedFile[]  $attachments  Registration files, excluding the signature PDF.
     * @return array{success: bool, message: string, warning?: string, skipped?: array<int, string>, raw?: array}
     */
    public function uploadMedia(int $clientId, UploadedFile $pdf, array $attachments = []): array
    {
        try {
            $pdfContents = $pdf->getContent();
            $pdfName = $this->activityFilename($pdf, 'firma');
            [$images, $skipped] = $this->activityImages($attachments);
        } catch (\Throwable $e) {
            Log::error('NewClientService: media upload exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión al subir archivos: '.$e->getMessage(),
            ];
        }

        Log::info('NewClientService: uploading media', [
            'client_id' => $clientId,
            'images_count' => count($images),
            'skipped' => $skipped,
        ]);

        try {
            $response = $this->sendWithTokenRetry(function (string $token) use ($pdfContents, $pdfName, $images, $clientId) {
                // Expect: 100-continue breaks uploads larger than 1MB on the
                // production Activity host. Strict redirects keep the multipart
                // body when that host answers with an http/https redirect.
                $request = Http::withHeaders([
                    'Tokenconectat' => $token,
                ])->withOptions([
                    'expect' => false,
                    'allow_redirects' => [
                        'max' => 5,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                    ],
                ])->timeout(60)
                    ->attach('pdf', $pdfContents, $pdfName, ['Content-Type' => 'application/pdf']);

                foreach ($images as $image) {
                    $request = $request->attach(
                        'imagenes[]',
                        $image['contents'],
                        $image['filename'],
                        ['Content-Type' => $image['mime']]
                    );
                }

                return $request->post($this->baseUrl.'/AuthMediaClienteNuevo/cargaArchivos', [
                    'id' => (string) $clientId,
                ]);
            });

            $decoded = $response->json();
            $body = is_array($decoded) ? $decoded : [];
            $problems = $this->mediaProblemMessages($body);
            $skippedWarning = $this->skippedFilesWarning($skipped);
            $warning = $this->joinWarnings($skippedWarning, $problems);

            if ($response->successful() && ($body['Success'] ?? '') === 'OK' && $this->pdfWasStored($body)) {
                return [
                    'success' => true,
                    'message' => 'Archivos cargados exitosamente.',
                    'warning' => $warning,
                    'skipped' => $skipped,
                    'raw' => $body,
                ];
            }

            $errorMessage = $body['Mensaje'] ?? $body['message'] ?? $response->body();
            if (! $this->pdfWasStored($body) && ($body['Success'] ?? '') === 'OK') {
                $errorMessage = 'El servicio externo no guardó el PDF de la firma.';
            }
            if ($warning) {
                $errorMessage = trim($errorMessage.' '.$warning);
            }

            Log::warning('NewClientService: media upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'skipped' => $skipped,
            ]);

            return [
                'success' => false,
                'message' => "Error al subir archivos ({$response->status()}): {$errorMessage}",
                'skipped' => $skipped,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('NewClientService: media upload exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión al subir archivos: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  UploadedFile[]  $attachments
     * @return array{0: array<int, array{contents: string, filename: string, mime: string}>, 1: array<int, string>}
     */
    private function activityImages(array $attachments): array
    {
        $images = [];
        $skipped = [];

        foreach ($attachments as $attachment) {
            if (! $attachment instanceof UploadedFile) {
                continue;
            }

            $originalName = $attachment->getClientOriginalName() ?: 'archivo';
            if (! $this->isActivityImage($attachment)) {
                $skipped[] = $originalName;

                continue;
            }

            if (count($images) >= 3) {
                $skipped[] = $originalName;

                continue;
            }

            try {
                $contents = $attachment->getContent();
            } catch (\Throwable $e) {
                Log::warning('NewClientService: skipping unreadable image', [
                    'name' => $originalName,
                    'error' => $e->getMessage(),
                ]);
                $skipped[] = $originalName;

                continue;
            }

            $images[] = [
                'contents' => $contents,
                'filename' => $this->activityFilename($attachment, 'imagen'.(count($images) + 1)),
                'mime' => $this->activityImageMime($attachment),
            ];
        }

        return [$images, $skipped];
    }

    private function isActivityImage(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return false;
        }

        $mime = strtolower((string) $file->getMimeType());

        return in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'], true);
    }

    private function activityImageMime(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        return $extension === 'png' ? 'image/png' : 'image/jpeg';
    }

    private function activityFilename(UploadedFile $file, string $fallbackBase): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = Str::ascii((string) $base);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? '';
        $base = trim((string) $base, '._-');
        if ($base === '') {
            $base = $fallbackBase;
        }

        return $base.'.'.$extension;
    }

    /**
     * @param  array<int, string>  $skipped
     */
    private function skippedFilesWarning(array $skipped): ?string
    {
        if ($skipped === []) {
            return null;
        }

        return 'El servicio de Cliente Nuevo solo recibe el PDF de la firma y máximo 3 imágenes JPG o PNG. Estos archivos quedaron guardados en TUTI: '.implode(', ', $skipped).'.';
    }

    /**
     * @param  array<int, string>  $problems
     */
    private function joinWarnings(?string $skippedWarning, array $problems): ?string
    {
        $parts = [];
        if ($skippedWarning) {
            $parts[] = $skippedWarning;
        }
        if ($problems !== []) {
            $parts[] = 'El servicio externo reportó: '.implode(' ', $problems);
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function pdfWasStored(array $body): bool
    {
        if (! array_key_exists('bdPdf', $body)) {
            return true;
        }

        $value = $body['bdPdf'];
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['false', '0', 'no', ''], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<int, string>
     */
    private function mediaProblemMessages(array $body): array
    {
        $imagenes = data_get($body, 'imagenes', []);
        if (! is_array($imagenes)) {
            return [];
        }

        $messages = [];
        foreach ($imagenes as $key => $value) {
            if ($key === 'guardado') {
                continue;
            }
            foreach (Arr::wrap($value) as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $messages[] = trim($item);
                }
            }
        }

        return $messages;
    }

    private function buildClientXml(array $data): string
    {
        $fields = [
            'Documento', 'TipoDocumento', 'PrimerNombre', 'SegundoNombre',
            'PrimerApellido', 'SegundoApellido', 'NombreNegocio',
            'IdClasificacionCliente', 'Departamento', 'Ciudad',
            'Telefono', 'Movil', 'Whatsapp', 'Correo',
            'Direccion', 'Barrio', 'Zona', 'RutaZonaVentas',
            'DiaRecorrido', 'Posicion', 'Pep',
        ];

        // For NIT clients (persona jurídica) the ERP must receive the razón
        // social as the client name, not the commercial contact's name.
        $razonSocial = trim((string) ($data['RazonSocial'] ?? ''));
        if ((int) ($data['TipoDocumento'] ?? 0) === 3 && $razonSocial !== '') {
            $data['PrimerNombre'] = $razonSocial;
            $data['SegundoNombre'] = '';
            $data['PrimerApellido'] = '';
            $data['SegundoApellido'] = '';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n<ClienteNuevo>\n";

        foreach ($fields as $field) {
            $value = htmlspecialchars((string) ($data[$field] ?? ''), ENT_XML1, 'UTF-8');
            $xml .= "  <{$field}>{$value}</{$field}>\n";
        }

        $xml .= '</ClienteNuevo>';

        return $xml;
    }

    /**
     * Execute a request authenticated with Tokenconectat and retry once on 401 when using credential-based auth.
     */
    private function sendWithTokenRetry(callable $requestFactory): \Illuminate\Http\Client\Response
    {
        $response = $requestFactory($this->resolveProcessToken());
        if ($response->status() === 401 && $this->usesCredentialAuth()) {
            Log::warning('NewClientService: token rejected, forcing token refresh and retry');
            $response = $requestFactory($this->resolveProcessToken(true));
        }

        return $response;
    }

    private function resolveProcessToken(bool $forceRefresh = false): string
    {
        if ($this->usesCredentialAuth()) {
            return $this->fetchCredentialToken($forceRefresh);
        }

        if ($this->staticToken === '') {
            throw new RuntimeException(
                'ClienteNuevo token is not configured. Set CLIENTE_NUEVO_TOKEN or configure CLIENTE_NUEVO_AUTH_* credentials.'
            );
        }

        return $this->staticToken;
    }

    private function usesCredentialAuth(): bool
    {
        return !empty($this->authConfig['url'])
            && !empty($this->authConfig['username'])
            && !empty($this->authConfig['password']);
    }

    private function fetchCredentialToken(bool $forceRefresh = false): string
    {
        $cacheKey = sprintf(
            'cliente_nuevo:token:%s',
            md5((string) $this->authConfig['url'].'|'.(string) $this->authConfig['username'])
        );

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $authUrl = (string) $this->authConfig['url'];
        $usernameField = (string) $this->authConfig['username_field'];
        $passwordField = (string) $this->authConfig['password_field'];
        $tokenField = (string) $this->authConfig['token_field'];

        $response = Http::asForm()
            ->timeout(20)
            ->post($authUrl, [
                $usernameField => (string) $this->authConfig['username'],
                $passwordField => (string) $this->authConfig['password'],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                "ClienteNuevo auth failed ({$response->status()}): ".$response->body()
            );
        }

        $body = $response->json();
        $token = is_array($body) ? trim((string) data_get($body, $tokenField, '')) : '';

        if ($token === '') {
            throw new RuntimeException(
                "ClienteNuevo auth succeeded but token field '{$tokenField}' was not found in response."
            );
        }

        $ttl = max(60, (int) $this->authConfig['ttl_seconds']);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }
}
