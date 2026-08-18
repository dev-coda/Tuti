<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MicrosoftTokenService
{
    public static function refresh(?string $resource = null): string
    {
        $clientId = config('microsoft.client_id');
        $clientSecret = config('microsoft.client_secret');
        $resource = self::normalizeResource($resource ?? config('microsoft.resource'));
        $url = config('microsoft.url_token');

        foreach ([
            'MICROSOFT_URL_TOKEN' => $url,
            'MICROSOFT_CLIENT_ID' => $clientId,
            'MICROSOFT_CLIENT_SECRET' => $clientSecret,
            'MICROSOFT_RESOURCE_URL' => $resource,
        ] as $envKey => $value) {
            if (blank($value)) {
                throw new RuntimeException("Falta la variable de entorno {$envKey}.");
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
                'resource' => $resource,
                'body_preview' => substr(trim((string) $response->body()), 0, 300),
            ]);

            throw new RuntimeException(
                'Microsoft no devolvió JSON válido (HTTP '.$response->status().'). Revise MICROSOFT_URL_TOKEN.'
            );
        }

        $oauthError = data_get($payload, 'error');
        if ($oauthError) {
            $description = (string) (data_get($payload, 'error_description') ?: $oauthError);

            throw new RuntimeException(
                'Microsoft rechazó la solicitud de token (HTTP '.$response->status().'): '.$description
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
                'resource' => $resource,
                'keys' => array_keys($payload),
                'body_preview' => substr(trim((string) $response->body()), 0, 300),
            ]);

            throw new RuntimeException(
                'Microsoft respondió sin access_token (HTTP '.$response->status().'). Claves recibidas: '.implode(', ', array_keys($payload))
            );
        }

        $settingKey = self::settingKeyForResource($resource);
        $setting = Setting::firstOrCreate(
            ['key' => $settingKey],
            ['name' => 'Microsoft Token', 'value' => '', 'show' => false]
        );
        $setting->value = $token;
        $setting->save();

        return $token;
    }

    public static function currentOrRefresh(?string $resource = null): string
    {
        $resource = self::normalizeResource($resource ?? config('microsoft.resource'));
        $settingKey = self::settingKeyForResource($resource);
        $setting = Setting::where('key', $settingKey)->first();

        if ($setting && filled($setting->value) && self::tokenMatchesResource($setting->value, $resource)) {
            return $setting->value;
        }

        return self::refresh($resource);
    }

    /**
     * OAuth resource URL for a Dynamics SOAP/REST host.
     * Trailing slash is preserved to match Azure AD audience conventions.
     */
    public static function normalizeResource(?string $resource): string
    {
        $resource = trim((string) $resource);
        if ($resource === '') {
            return '';
        }

        // Accept a full SOAP endpoint and reduce it to the Dynamics host root.
        if (str_contains($resource, '/soap/')) {
            $parts = parse_url($resource);
            if (! empty($parts['scheme']) && ! empty($parts['host'])) {
                $resource = $parts['scheme'].'://'.$parts['host'].'/';
            }
        }

        return rtrim($resource, '/').'/';
    }

    private static function settingKeyForResource(string $resource): string
    {
        $default = self::normalizeResource(config('microsoft.resource'));
        if ($resource === '' || $resource === $default) {
            return 'microsoft_token';
        }

        return 'microsoft_token_'.substr(sha1($resource), 0, 12);
    }

    private static function tokenMatchesResource(string $token, string $resource): bool
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return true;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            $padded = $parts[1].str_repeat('=', (4 - strlen($parts[1]) % 4) % 4);
            $payloadJson = base64_decode(strtr($padded, '-_', '+/'), true);
        }
        if ($payloadJson === false) {
            return true;
        }

        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return true;
        }

        $aud = self::normalizeResource((string) ($payload['aud'] ?? ''));
        $expected = self::normalizeResource($resource);
        if ($aud === '' || $expected === '') {
            return true;
        }

        if ($aud !== $expected) {
            return false;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < (time() + 60)) {
            return false;
        }

        return true;
    }
}
