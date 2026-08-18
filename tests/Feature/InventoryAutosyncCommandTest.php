<?php

use App\Jobs\SyncProductInventory;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function configureMicrosoftOAuth(): void
{
    config([
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://dynamics.test',
        'queue.default' => 'redis',
    ]);
}

function enableInventoryAutosyncSettings(bool $syncEnabled = true, bool $inventoryEnabled = true): void
{
    Setting::updateOrCreate(
        ['key' => 'inventory_sync_enabled'],
        ['name' => 'Inventory sync enabled', 'value' => $syncEnabled ? '1' : '0', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => $inventoryEnabled ? '1' : '0', 'show' => false]
    );
}

it('refreshes the microsoft token then dispatches inventory sync', function () {
    configureMicrosoftOAuth();
    enableInventoryAutosyncSettings();
    Queue::fake();

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response(['access_token' => 'nightly-token'], 200),
    ]);

    $this->artisan('inventory:sync')->assertSuccessful();

    expect(Setting::where('key', 'microsoft_token')->value('value'))->toBe('nightly-token');

    $progress = json_decode((string) Setting::getByKey('inventory_sync_progress'), true);
    expect($progress)->toBeArray()
        ->and($progress['status'])->toBe('queued');

    Queue::assertPushedOn('inventory', SyncProductInventory::class);
});

it('skips dispatch when inventory_sync_enabled is off', function () {
    configureMicrosoftOAuth();
    enableInventoryAutosyncSettings(syncEnabled: false);
    Queue::fake();
    Http::fake();

    $this->artisan('inventory:sync')->assertSuccessful();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('skips dispatch when inventory_enabled is off', function () {
    configureMicrosoftOAuth();
    enableInventoryAutosyncSettings(inventoryEnabled: false);
    Queue::fake();
    Http::fake();

    $this->artisan('inventory:sync')->assertSuccessful();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('fails visibly when token refresh fails and does not dispatch', function () {
    configureMicrosoftOAuth();
    enableInventoryAutosyncSettings();
    Queue::fake();

    Http::fake([
        'https://login.microsoftonline.com/token' => Http::response([
            'error' => 'invalid_client',
            'error_description' => 'Client secret expired',
        ], 401),
    ]);

    $this->artisan('inventory:sync')->assertFailed();

    Queue::assertNothingPushed();

    $progress = json_decode((string) Setting::getByKey('inventory_sync_progress'), true);
    expect($progress)->toBeArray()
        ->and($progress['status'])->toBe('error')
        ->and($progress['message'])->toBe('No se pudo renovar el token de Microsoft.')
        ->and($progress['error_message'])->toContain('Client secret expired');
});
