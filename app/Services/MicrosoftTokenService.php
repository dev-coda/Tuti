<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MicrosoftTokenService
{
    public static function refresh(): string
    {
        $clientId = config('microsoft.client_id');
        $clientSecret = config('microsoft.client_secret');
        $resource = config('microsoft.resource');
        $url = config('microsoft.url_token');

        foreach ([
            'MICROSOFT_URL_TOKEN' => $url,
            'MICROSOFT_CLIENT_ID' => $clientId,
            'MICROSOFT_CLIENT_SECRET' => $clientSecret,
            'MICROSOFT_RESOURCE_URL' => $resource,
        ] as $envKey => $value) {
            if (blank($value)) {
                throw new RuntimeException("Microsoft OAuth no está configurado: falta {$envKey}.");
            }
        }

        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => $resource,
        ]);

        $payload = $response->json();
        if (! is_array($payload)) {
            Log::warning('Microsoft token refresh returned non-JSON response', [
                'status' => $response->status(),
                'body_preview' => substr(trim((string) $response->body()), 0, 300),
            ]);

            throw new RuntimeException(
                'No se pudo obtener el token de Microsoft (HTTP '.$response->status().'): respuesta no JSON.'
            );
        }

        $oauthError = data_get($payload, 'error');
        if ($oauthError) {
            $description = (string) (data_get($payload, 'error_description') ?: $oauthError);

            throw new RuntimeException(
                'No se pudo obtener el token de Microsoft (HTTP '.$response->status().'): '.$description
            );
        }

        if (! $response->successful()) {
            $description = (string) (data_get($payload, 'error_description') ?: data_get($payload, 'error') ?: $response->body());

            throw new RuntimeException(
                'No se pudo obtener el token de Microsoft (HTTP '.$response->status().'): '.$description
            );
        }

        $token = data_get($payload, 'access_token');
        if (! is_string($token) || $token === '') {
            Log::warning('Microsoft token refresh response missing access_token', [
                'status' => $response->status(),
                'keys' => array_keys($payload),
                'body_preview' => substr(trim((string) $response->body()), 0, 300),
            ]);

            throw new RuntimeException(
                'No se pudo obtener el token de Microsoft: la respuesta no incluyó access_token.'
            );
        }

        $setting = Setting::firstOrCreate(
            ['key' => 'microsoft_token'],
            ['name' => 'Microsoft Token', 'value' => '', 'show' => false]
        );
        $setting->value = $token;
        $setting->save();

        return $token;
    }

    public static function storedOrFail(): string
    {
        $setting = Setting::where('key', 'microsoft_token')->first();

        if (! $setting || blank($setting->value)) {
            throw new RuntimeException('No hay token de Microsoft almacenado. Ejecute php artisan app:get-token.');
        }

        return $setting->value;
    }

    /**
     * Return the stored Dynamics token, refreshing only when missing or forced.
     */
    public static function currentOrRefresh(bool $forceRefresh = false): string
    {
        $setting = Setting::where('key', 'microsoft_token')->first();

        if (! $forceRefresh && $setting && filled($setting->value)) {
            return $setting->value;
        }

        return self::refresh();
    }
}
