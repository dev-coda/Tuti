<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftTokenService
{
    /** Refresh stored access tokens before Dynamics rejects them (~60 min lifetime). */
    private const MAX_TOKEN_AGE_MINUTES = 25;

    public static function refresh(): string
    {
        $clientId = config('microsoft.client_id');
        $clientSecret = config('microsoft.client_secret');
        $resource = config('microsoft.resource');
        $url = config('microsoft.url_token');

        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => $resource,
        ]);

        $json = $response->json() ?? [];

        if (! $response->successful()) {
            $description = $json['error_description'] ?? $json['error'] ?? $response->body();
            throw new RuntimeException(
                'No se pudo obtener el token de Microsoft (HTTP '.$response->status().'): '.$description
            );
        }

        $token = $json['access_token'] ?? null;
        if (! $token) {
            throw new RuntimeException('La respuesta de Microsoft no incluyó access_token.');
        }

        $setting = Setting::firstOrCreate(
            ['key' => 'microsoft_token'],
            ['name' => 'Microsoft Token', 'value' => '', 'show' => false]
        );
        $setting->value = $token;
        $setting->save();

        return $token;
    }

    public static function currentOrRefresh(bool $forceRefresh = false): string
    {
        $setting = Setting::where('key', 'microsoft_token')->first();

        if (
            ! $forceRefresh
            && $setting
            && filled($setting->value)
            && $setting->updated_at->diffInMinutes(now()) <= self::MAX_TOKEN_AGE_MINUTES
        ) {
            return $setting->value;
        }

        return self::refresh();
    }
}
