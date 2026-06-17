<?php

use App\Services\NewClientService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
});

it('uses static token when credential auth is not configured', function () {
    config()->set('cliente_nuevo', [
        'base_url' => 'http://example.test/clienteConectat',
        'token' => 'static-token-123',
        'auth' => [
            'url' => null,
            'username' => null,
            'password' => null,
            'username_field' => 'username',
            'password_field' => 'password',
            'token_field' => 'token',
            'ttl_seconds' => 3300,
        ],
    ]);

    $service = new NewClientService();
    $method = new ReflectionMethod($service, 'resolveProcessToken');

    expect($method->invoke($service))->toBe('static-token-123');
});

it('fetches and caches credential-based token for ClienteNuevo flow', function () {
    config()->set('cliente_nuevo', [
        'base_url' => 'http://example.test/clienteConectat',
        'token' => '',
        'auth' => [
            'url' => 'http://example.test/auth/token',
            'username' => 'api-user',
            'password' => 'api-pass',
            'username_field' => 'username',
            'password_field' => 'password',
            'token_field' => 'token',
            'ttl_seconds' => 1200,
        ],
    ]);

    Http::fake([
        'http://example.test/auth/token' => Http::response(['token' => 'dynamic-jwt-token'], 200),
    ]);

    $service = new NewClientService();
    $method = new ReflectionMethod($service, 'resolveProcessToken');

    $first = $method->invoke($service);
    $second = $method->invoke($service);

    expect($first)->toBe('dynamic-jwt-token')
        ->and($second)->toBe('dynamic-jwt-token');

    Http::assertSentCount(1);
});
