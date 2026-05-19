<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewClientService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('cliente_nuevo.base_url'), '/');
        $this->token = (string) config('cliente_nuevo.token');
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
            $response = Http::withHeaders([
                'Content-Type' => 'application/xml',
                'Tokenconectat' => $this->token,
            ])->withBody($xml, 'application/xml')
                ->timeout(30)
                ->post($this->baseUrl.'/AuthClienteNuevo/clienteNuevo');

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
            $request = Http::withHeaders([
                'Tokenconectat' => $this->token,
            ])->timeout(60)
                ->attach('pdf', $pdf->getContent(), $pdf->getClientOriginalName());

            foreach ($images as $i => $image) {
                $request = $request->attach(
                    'imagenes[]',
                    $image->getContent(),
                    $image->getClientOriginalName()
                );
            }

            $response = $request->post($this->baseUrl.'/AuthMediaClienteNuevo/cargaArchivos', [
                'id' => (string) $clientId,
            ]);

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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n<ClienteNuevo>\n";

        foreach ($fields as $field) {
            $value = htmlspecialchars((string) ($data[$field] ?? ''), ENT_XML1, 'UTF-8');
            $xml .= "  <{$field}>{$value}</{$field}>\n";
        }

        $xml .= '</ClienteNuevo>';

        return $xml;
    }
}
