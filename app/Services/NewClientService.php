<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Step 2: Upload signature PDF and optional photos.
     *
     * @param  UploadedFile[]  $images  Up to 3 images (jpeg/png).
     * @return array{success: bool, message: string, raw?: array}
     */
    public function uploadMedia(int $clientId, UploadedFile $pdf, array $images = []): array
    {
        Log::info('NewClientService: uploading media', [
            'client_id' => $clientId,
            'images_count' => count($images),
        ]);

        try {
            $response = $this->sendWithTokenRetry(function (string $token) use ($pdf, $images, $clientId) {
                $request = Http::withHeaders([
                    'Tokenconectat' => $token,
                ])->timeout(60)
                    ->attach('pdf', $pdf->getContent(), $pdf->getClientOriginalName());

                foreach ($images as $image) {
                    $request = $request->attach(
                        'imagenes[]',
                        $image->getContent(),
                        $image->getClientOriginalName()
                    );
                }

                return $request->post($this->baseUrl.'/AuthMediaClienteNuevo/cargaArchivos', [
                    'id' => (string) $clientId,
                ]);
            });

            $body = $response->json() ?? [];

            if ($response->successful() && ($body['Success'] ?? '') === 'OK') {
                return [
                    'success' => true,
                    'message' => 'Archivos cargados exitosamente.',
                    'raw' => $body,
                ];
            }

            $errorMessage = $body['Mensaje'] ?? $body['message'] ?? $response->body();

            Log::warning('NewClientService: media upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => "Error al subir archivos ({$response->status()}): {$errorMessage}",
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
