<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth client for the Coordinadora APIs (docs/COORDINADORA/API Cotizador Nacional).
 * The token endpoint uses HTTP Basic auth with the credentials provided by
 * Coordinadora and a x-www-form-urlencoded body with grant_type=client_credentials.
 */
class CoordinadoraAuthService
{
    public function getAccessToken(): string
    {
        $cacheKey = 'coordinadora:oauth:token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $config = config('services.coordinadora');
        $url = $config['oauth_url'] ?? null;
        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;

        if (!$url || !$key || !$secret) {
            throw new RuntimeException('Coordinadora credentials are not configured.');
        }

        $response = Http::asForm()
            ->timeout(20)
            ->withBasicAuth($key, $secret)
            ->post($url, [
                'grant_type' => 'client_credentials',
            ])
            ->throw()
            ->json();

        // The vendor docs show "acces_token" (sic); accept both spellings.
        $token = (string) (
            data_get($response, 'access_token')
            ?? data_get($response, 'acces_token')
            ?? ''
        );
        if ($token === '') {
            throw new RuntimeException('Coordinadora token response did not include access_token.');
        }

        $expiresIn = (int) data_get($response, 'expires_in', 300);
        $ttl = max(60, $expiresIn - 60);

        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        return $token;
    }
}
