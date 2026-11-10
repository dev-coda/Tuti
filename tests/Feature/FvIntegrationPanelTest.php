<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeFvPanelAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('shows fv integration health and order log to admins', function () {
    $admin = makeFvPanelAdmin();

    config(['services.fv.endpoint' => 'https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup']);

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
        'zip_code' => '110111',
    ]);

    $order = Order::create([
        'user_id' => $admin->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PROCESSED,
        'zone_id' => $zone->id,
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
        'fv_number' => 'PV1547062',
        'coordinadora_guide_number' => '90012345678',
        'fv_response_payload' => json_encode(['sales_order_number' => 'PV1547062', 'document_status' => 'CONFIRMADO']),
    ]);

    $this->actingAs($admin)
        ->get(route('settings.fv-integration'))
        ->assertOk()
        ->assertSee('Integración FV (Dynamics 365)')
        ->assertSee('https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup')
        ->assertSee('PV1547062')
        ->assertSee('90012345678');
});

it('blocks non-admin users from the fv panel', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.fv-integration'))
        ->assertRedirect();
});

it('runs a connectivity test and stores the result', function () {
    $admin = makeFvPanelAdmin();

    config([
        'services.fv.endpoint' => 'https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup',
        'microsoft.resource' => 'https://dynamics.test/',
    ]);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'test-token', 'show' => false]
    );

    Http::fake([
        'https://dynamics.test/*' => Http::response('<wsdl/>', 200),
    ]);

    $this->actingAs($admin)
        ->post(route('settings.fv-integration.test'))
        ->assertRedirect(route('settings.fv-integration'))
        ->assertSessionHas('success');

    $stored = json_decode((string) Setting::getByKey('fv_last_health_check'), true);
    expect($stored['ok'])->toBeTrue();
    expect($stored['http_status'])->toBe(200);
    expect($stored['token_ok'])->toBeTrue();
});

it('records a failed connectivity test when the endpoint errors', function () {
    $admin = makeFvPanelAdmin();

    config([
        'services.fv.endpoint' => 'https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup',
        'microsoft.resource' => 'https://dynamics.test/',
    ]);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'test-token', 'show' => false]
    );

    Http::fake([
        'https://dynamics.test/*' => Http::response('error', 503),
    ]);

    $this->actingAs($admin)
        ->post(route('settings.fv-integration.test'))
        ->assertRedirect(route('settings.fv-integration'))
        ->assertSessionHas('error');

    $stored = json_decode((string) Setting::getByKey('fv_last_health_check'), true);
    expect($stored['ok'])->toBeFalse();
    expect($stored['http_status'])->toBe(503);
});

it('saves which cities send bonificaciones on the fv', function () {
    $admin = makeFvPanelAdmin();
    Cache::forget('setting_'.Setting::FV_BONIFICACIONES_CITIES_KEY);

    $state = State::create(['name' => 'Cundinamarca']);
    City::create([
        'name' => 'Bogotá',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.fv-integration'))
        ->assertOk()
        ->assertSee('Bonificaciones en la FV')
        ->assertSee('Bogotá')
        ->assertSee('BG00')
        ->assertSee('MDTAT');

    $this->actingAs($admin)
        ->post(route('settings.fv-integration.bonificaciones'), [
            'fv_bonificaciones' => [
                'bogota' => '1',
                'medellin' => '0',
            ],
        ])
        ->assertRedirect(route('settings.fv-integration'))
        ->assertSessionHas('success');

    $stored = json_decode((string) Setting::getByKey(Setting::FV_BONIFICACIONES_CITIES_KEY), true);
    expect($stored['bogota'])->toBe('1')
        ->and($stored['medellin'])->toBe('0')
        ->and($stored['cali'])->toBe('0')
        ->and($stored['barranquilla'])->toBe('0');

    $guest = User::factory()->create();
    $this->actingAs($guest)
        ->post(route('settings.fv-integration.bonificaciones'), [
            'fv_bonificaciones' => ['bogota' => '1'],
        ])
        ->assertRedirect();
});
