<?php

use App\Jobs\BulkSyncClientsData;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Services\DraftOrderReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeStuckSelfRegisteredClient(string $document): User
{
    // Mirrors RegisteredUserController::register: status_id PENDING is set explicitly
    // while client_status falls back to the column default 'cliente'.
    return User::factory()->create([
        'document' => $document,
        'client_status' => User::CLIENT_STATUS_CLIENTE,
        'status_id' => User::PENDING,
    ]);
}

it('activates a cliente stuck in PENDING when a valid rutero code exists', function () {
    $client = makeStuckSelfRegisteredClient('1003237224');

    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8',
        'code' => 'CUST-706-1',
    ]);

    $promoted = app(DraftOrderReconciliationService::class)
        ->promoteUserIfReady($client->fresh(['zones']));

    $client->refresh();

    expect($promoted)->toBeTrue()
        ->and($client->status_id)->toBe(User::ACTIVE)
        ->and($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE);
});

it('does not activate a stuck cliente without a valid rutero code', function () {
    $client = makeStuckSelfRegisteredClient('1003237225');

    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8',
        'code' => null,
    ]);

    $promoted = app(DraftOrderReconciliationService::class)
        ->promoteUserIfReady($client->fresh(['zones']));

    expect($promoted)->toBeFalse()
        ->and($client->refresh()->status_id)->toBe(User::PENDING);
});

it('does not touch clientes that are already active', function () {
    $client = User::factory()->create([
        'document' => '1003237226',
        'client_status' => User::CLIENT_STATUS_CLIENTE,
        'status_id' => User::ACTIVE,
    ]);

    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8',
        'code' => 'CUST-706-2',
    ]);

    $promoted = app(DraftOrderReconciliationService::class)
        ->promoteUserIfReady($client->fresh(['zones']));

    expect($promoted)->toBeFalse();
});

it('never activates rejected clients', function () {
    $client = User::factory()->create([
        'document' => '1003237227',
        'client_status' => User::CLIENT_STATUS_RECHAZADO,
        'status_id' => User::PENDING,
    ]);

    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8',
        'code' => 'CUST-706-3',
    ]);

    $promoted = app(DraftOrderReconciliationService::class)
        ->promoteUserIfReady($client->fresh(['zones']));

    expect($promoted)->toBeFalse()
        ->and($client->refresh()->status_id)->toBe(User::PENDING);
});

it('activates stuck self-registered clientes during nightly reconciliation', function () {
    $client = makeStuckSelfRegisteredClient('1003237229');

    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8',
        'code' => 'CUST-RECONCILE-1',
    ]);

    $stats = app(DraftOrderReconciliationService::class)->reconcileAll();

    expect($stats['users_promoted'])->toBeGreaterThanOrEqual(1)
        ->and($client->refresh()->status_id)->toBe(User::ACTIVE);
});

it('activates stuck self-registered clientes during the nightly bulk rutero sync', function () {
    Storage::fake('local');
    config(['microsoft.resource' => 'https://dynamics.test']);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'CUST-NIGHTLY-1',
                'zone' => '706',
                'route' => '1918',
                'day' => '1- Lunes',
                'address' => 'MZ 5 CS 8 EL ROCIO',
                'name' => 'LEIDYS FERNANDA URRUTIA LOBO',
            ],
        ])),
    ]);

    $client = makeStuckSelfRegisteredClient('1003237224');

    // Self-registration stores the rutero (including CustRuteroID) as zone rows;
    // the nightly contact-only sync does not restructure zones, so activation
    // relies on this locally stored code.
    Zone::create([
        'user_id' => $client->id,
        'route' => '1918',
        'zone' => '706',
        'day' => '1',
        'address' => 'MZ 5 CS 8 EL ROCIO',
        'code' => 'CUST-NIGHTLY-1',
    ]);

    (new BulkSyncClientsData([$client->id], 'test-session'))->handle();

    $client->refresh();

    expect($client->status_id)->toBe(User::ACTIVE)
        ->and($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE);
});

it('leaves clients pending when the nightly sync finds no rutero', function () {
    Storage::fake('local');
    config(['microsoft.resource' => 'https://dynamics.test']);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );

    Http::fake([
        'https://dynamics.test*' => Http::response('<?xml version="1.0"?><Envelope></Envelope>', 200),
    ]);

    $client = makeStuckSelfRegisteredClient('1003237228');

    (new BulkSyncClientsData([$client->id], 'test-session-2'))->handle();

    expect($client->refresh()->status_id)->toBe(User::PENDING);
});
