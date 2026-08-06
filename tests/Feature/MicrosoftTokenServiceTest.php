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

it('mints and caches a separate token when the resource audience differs', function () {
    config([
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://uattrx.test/',
    ]);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'tronex-token', 'show' => false]
    );

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response(['access_token' => 'fv-host-token'], 200),
    ]);

    $token = MicrosoftTokenService::currentOrRefresh('https://dev03.test/soap/services/DYNPRODWSSalesForceGroup');

    expect($token)->toBe('fv-host-token')
        ->and(Setting::where('key', 'microsoft_token')->value('value'))->toBe('tronex-token')
        ->and(Setting::where('key', 'microsoft_token')->count())->toBe(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://login.microsoftonline.com/token'
            && $request['resource'] === 'https://dev03.test/';
    });
});