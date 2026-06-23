<?php

use App\Models\Setting;
use App\Services\MicrosoftTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns a clear oauth error when microsoft rejects the token request', function () {
    config([
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://dynamics.test',
    ]);

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Client secret expired',
        ], 401),
    ]);

    expect(fn () => MicrosoftTokenService::refresh())
        ->toThrow(RuntimeException::class, 'Microsoft rechazó la solicitud de token (HTTP 401): Client secret expired');
});

it('returns a clear error when microsoft responds without access_token', function () {
    config([
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://dynamics.test',
    ]);

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response(['token_type' => 'Bearer'], 200),
    ]);

    expect(fn () => MicrosoftTokenService::refresh())
        ->toThrow(RuntimeException::class, 'Microsoft respondió sin access_token');
});

it('stores a refreshed token in settings', function () {
    config([
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://dynamics.test',
    ]);

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response(['access_token' => 'fresh-token'], 200),
    ]);

    expect(MicrosoftTokenService::refresh())->toBe('fresh-token')
        ->and(Setting::where('key', 'microsoft_token')->value('value'))->toBe('fresh-token');
});
