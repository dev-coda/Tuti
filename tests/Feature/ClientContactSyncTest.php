<?php

use App\Jobs\BulkSyncClientsData;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );
});

function fakeRuteroWithContact(array $overrides = []): void
{
    Http::fake([
        'https://dynamics.test/*' => Http::response(fakeGetRuterosSoap([
            array_merge([
                'code' => 'SUC1',
                'zone' => '905',
                'route' => 'R9',
                'day' => '2',
                'address' => 'Calle Nueva 123',
                'name' => 'Nombre Dynamics',
                'phone' => '6041234567',
                'mobile_phone' => '3001234567',
                'whatsapp' => '3001234567',
                'email' => 'nuevo@example.com',
                'balance' => '999999',
                'quota_value' => '888888',
                'business_name' => 'Razon Social Dynamics',
                'price_group' => 'GRUPO9',
            ], $overrides),
        ]), 200),
    ]);
}

it('contact-only sync updates email and phones but nothing else', function () {
    $user = User::factory()->create([
        'document' => '123456789',
        'name' => 'Nombre Local',
        'email' => 'viejo@example.com',
        'phone' => '111',
        'mobile_phone' => '222',
        'whatsapp' => '333',
        'business_name' => 'Razon Local',
        'balance' => 100,
        'quota_value' => 200,
        'price_group' => 'GRUPO1',
    ]);
    $zone = Zone::create([
        'user_id' => $user->id,
        'address' => 'Calle Vieja 1',
        'zone' => '900',
        'route' => 'RA',
        'day' => '1',
        'code' => 'SUC-OLD',
    ]);

    fakeRuteroWithContact();

    $result = UserRepository::syncUserContactData($user);
    expect($result)->toBeTrue();

    $user->refresh();

    // Contact data updated
    expect($user->email)->toBe('nuevo@example.com');
    expect($user->phone)->toBe('6041234567');
    expect($user->mobile_phone)->toBe('3001234567');
    expect($user->whatsapp)->toBe('3001234567');
    expect($user->rutero_synced_at)->not->toBeNull();

    // Everything else untouched
    expect($user->name)->toBe('Nombre Local');
    expect($user->business_name)->toBe('Razon Local');
    expect((float) $user->balance)->toBe(100.0);
    expect((float) $user->quota_value)->toBe(200.0);
    expect($user->price_group)->toBe('GRUPO1');

    // Zones untouched: no new row created, existing row not modified or pruned
    expect(Zone::where('user_id', $user->id)->count())->toBe(1);
    $zone->refresh();
    expect($zone->code)->toBe('SUC-OLD');
    expect($zone->zone)->toBe('900');
    expect($zone->address)->toBe('Calle Vieja 1');
});

it('contact-only sync skips empty values and keeps local phones', function () {
    $user = User::factory()->create([
        'document' => '123456789',
        'phone' => '111',
        'mobile_phone' => '222',
        'whatsapp' => '333',
    ]);

    // Dynamics returns no contact fields at all
    fakeRuteroWithContact([
        'phone' => '',
        'mobile_phone' => '',
        'whatsapp' => '',
        'email' => '',
    ]);

    expect(UserRepository::syncUserContactData($user))->toBeTrue();

    $user->refresh();
    expect($user->phone)->toBe('111');
    expect($user->mobile_phone)->toBe('222');
    expect($user->whatsapp)->toBe('333');
});

it('contact-only sync does not steal an email already used by another user', function () {
    User::factory()->create(['email' => 'nuevo@example.com']);
    $user = User::factory()->create([
        'document' => '123456789',
        'email' => 'viejo@example.com',
    ]);

    fakeRuteroWithContact();

    expect(UserRepository::syncUserContactData($user))->toBeTrue();

    $user->refresh();
    expect($user->email)->toBe('viejo@example.com');
    // Phones still refreshed
    expect($user->phone)->toBe('6041234567');
});

it('full sync still updates the whole profile and zones', function () {
    $user = User::factory()->create([
        'document' => '123456789',
        'name' => 'Nombre Local',
        'balance' => 100,
    ]);

    fakeRuteroWithContact();

    expect(UserRepository::syncUserRuteroData($user))->toBeTrue();

    $user->refresh();
    expect($user->name)->toBe('Nombre Dynamics');
    expect((float) $user->balance)->toBe(999999.0);
    expect($user->phone)->toBe('6041234567');
    expect(Zone::where('user_id', $user->id)->where('code', 'SUC1')->exists())->toBeTrue();
});

it('bulk sync job only refreshes contact data for each client', function () {
    $user = User::factory()->create([
        'document' => '123456789',
        'name' => 'Nombre Local',
        'phone' => '111',
        'balance' => 100,
    ]);
    Zone::create([
        'user_id' => $user->id,
        'address' => 'Calle Vieja 1',
        'zone' => '900',
        'route' => 'RA',
        'day' => '1',
        'code' => 'SUC-OLD',
    ]);

    fakeRuteroWithContact();

    (new BulkSyncClientsData([$user->id], 'test-session'))->handle();

    $user->refresh();
    expect($user->email)->toBe('nuevo@example.com');
    expect($user->phone)->toBe('6041234567');
    expect($user->name)->toBe('Nombre Local');
    expect((float) $user->balance)->toBe(100.0);
    expect(Zone::where('user_id', $user->id)->pluck('code')->all())->toBe(['SUC-OLD']);

    expect(Setting::getByKey('last_client_rutero_bulk_sync_session'))->toBe('test-session');
    expect(Setting::getByKey('last_client_rutero_bulk_sync_report'))->toBe('bulk-client-sync-test-session.csv');
});

it('daily command dispatches the bulk sync only for clients with document', function () {
    Bus::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $client = User::factory()->create(['document' => '123456789']);
    User::factory()->create(['document' => null]); // client without document: excluded
    $admin = User::factory()->create(['document' => '999999999']);
    $admin->assignRole('admin'); // has role: excluded

    $this->artisan('clients:sync-rutero-daily')->assertSuccessful();

    Bus::assertDispatched(BulkSyncClientsData::class);
});

it('daily command respects the disable setting', function () {
    Bus::fake();

    Setting::updateOrCreate(
        ['key' => 'daily_client_rutero_sync_enabled'],
        ['name' => 'Daily rutero sync', 'value' => '0', 'show' => false]
    );
    User::factory()->create(['document' => '123456789']);

    $this->artisan('clients:sync-rutero-daily')->assertSuccessful();

    Bus::assertNotDispatched(BulkSyncClientsData::class);
});
