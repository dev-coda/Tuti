<?php

use App\Models\ClientDataUpdateRequest;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function mockRuteroForDataUpdate(array $routeOverrides = []): void
{
    $route = array_merge([
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 10 # 20-30',
        'code' => 'RUT-1',
    ], $routeOverrides);

    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->andReturn([
            'name' => 'Cliente Tronex',
            'routes' => [$route],
        ]);
}

afterEach(function () {
    \Mockery::close();
});

it('allows a seller to submit a client data update request', function () {
    mockRuteroForDataUpdate();

    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'name' => 'Cliente Original',
        'document' => '901234567',
        'business_name' => 'Tienda Original',
        'phone' => '6011111111',
        'mobile_phone' => '3001111111',
        'whatsapp' => '3001111111',
        'email' => 'cliente@example.com',
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 10 # 20-30',
        'code' => 'RUT-1',
    ]);

    actingAs($seller)
        ->post(route('client-data-updates.store', $zone), [
            'document' => '901234567',
            'name' => 'Cliente Actualizado',
            'business_name' => 'Tienda Actualizada',
            'email' => 'nuevo@example.com',
            'phone' => '6022222222',
            'mobile_phone' => '3002222222',
            'whatsapp' => '3003333333',
            'address' => 'Calle 20 # 30-40',
            'city_name' => 'Bogotá',
            'seller_notes' => 'Corrección de teléfonos',
            'return_tab' => 'mi-ruta',
            'return_route' => 'R100',
        ])
        ->assertRedirect(route('clients.orders.index', ['tab' => 'mi-ruta', 'ruta' => 'R100']));

    $request = ClientDataUpdateRequest::first();

    expect($request)->not->toBeNull()
        ->and($request->name)->toBe('Cliente Actualizado')
        ->and($request->business_name)->toBe('Tienda Actualizada')
        ->and($request->zone_code)->toBe('301')
        ->and($request->route)->toBe('R100')
        ->and($request->day)->toBe('1')
        ->and($request->submitted_by)->toBe($seller->id)
        ->and($request->previous_data['name'])->toBe('Cliente Original')
        ->and(ClientDataUpdateRequest::count())->toBe(1);
});

it('stores zone and route from getRutero even if the request tries to override them', function () {
    mockRuteroForDataUpdate(['zone' => '301', 'route' => 'R100']);

    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'document' => '901234567',
        'email' => 'cliente@example.com',
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 10',
        'code' => 'RUT-1',
    ]);

    actingAs($seller)
        ->post(route('client-data-updates.store', $zone), [
            'document' => '901234567',
            'name' => 'Cliente Actualizado',
            'address' => 'Calle 20',
            'zone_code' => '999',
            'route' => 'R999',
            'day' => '9',
            'return_tab' => 'mi-ruta',
        ])
        ->assertRedirect();

    $request = ClientDataUpdateRequest::first();

    expect($request->zone_code)->toBe('301')
        ->and($request->route)->toBe('R100')
        ->and($request->day)->toBe('1');
});

it('shows the seller edit form prefilled with client data and rutero zone route', function () {
    mockRuteroForDataUpdate();

    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'name' => 'Cliente Formulario',
        'document' => '123456789',
        'business_name' => 'Negocio Formulario',
        'mobile_phone' => '3009999999',
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-FORM',
    ]);

    actingAs($seller)
        ->get(route('client-data-updates.edit', ['zone' => $zone->id, 'return_tab' => 'mi-ruta', 'ruta' => 'R100']))
        ->assertOk()
        ->assertSee('Actualizar datos del cliente')
        ->assertSee('123456789')
        ->assertSee('Negocio Formulario')
        ->assertSee('3009999999')
        ->assertSee('Zona (Tronex)')
        ->assertSee('R100')
        ->assertDontSee('name="zone_code"', false);
});

it('includes only changed fields in the update notification email', function () {
    $client = User::factory()->create(['document' => '901234567']);
    $seller = User::factory()->create(['name' => 'Vendedor Uno', 'email' => 'vendedor@example.com']);

    $updateRequest = ClientDataUpdateRequest::create([
        'user_id' => $client->id,
        'submitted_by' => $seller->id,
        'document' => '901234567',
        'name' => 'Cliente Original',
        'business_name' => 'Tienda Original',
        'email' => 'nuevo@example.com',
        'phone' => '6011111111',
        'mobile_phone' => '3002222222',
        'address' => 'Calle 10 # 20-30',
        'previous_data' => [
            'name' => 'Cliente Original',
            'business_name' => 'Tienda Original',
            'document' => '901234567',
            'email' => 'viejo@example.com',
            'phone' => '6011111111',
            'mobile_phone' => '3001111111',
            'whatsapp' => null,
            'address' => 'Calle 10 # 20-30',
        ],
    ]);

    $changes = $updateRequest->changedFields();

    expect(array_keys($changes))->toBe(['email', 'mobile_phone'])
        ->and($changes['email']['old'])->toBe('viejo@example.com')
        ->and($changes['email']['new'])->toBe('nuevo@example.com')
        ->and($changes['mobile_phone']['old'])->toBe('3001111111')
        ->and($changes['mobile_phone']['new'])->toBe('3002222222');

    $html = view('emails.client-data-update', ['updateRequest' => $updateRequest])->render();

    expect($html)->toContain('Datos actualizados')
        ->toContain('nuevo@example.com')
        ->toContain('viejo@example.com')
        ->toContain('3002222222')
        ->not->toContain('Tienda Original</strong>')
        ->not->toContain('<td style="border-bottom: 1px solid #E5E7EB;"><strong>Dirección</strong></td>');
});

it('reports when an update request has no changes', function () {
    $client = User::factory()->create(['document' => '901234567']);
    $seller = User::factory()->create();

    $updateRequest = ClientDataUpdateRequest::create([
        'user_id' => $client->id,
        'submitted_by' => $seller->id,
        'document' => '901234567',
        'name' => 'Cliente Igual',
        'address' => 'Calle 10',
        'previous_data' => [
            'name' => 'Cliente Igual',
            'document' => '901234567',
            'address' => 'Calle 10',
        ],
    ]);

    expect($updateRequest->changedFields())->toBe([]);

    $html = view('emails.client-data-update', ['updateRequest' => $updateRequest])->render();

    expect($html)->toContain('no contiene cambios');
});

it('lists client data update requests in admin', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create(['document' => '555666777']);
    $seller = User::factory()->create(['name' => 'Vendedor Uno']);

    ClientDataUpdateRequest::create([
        'user_id' => $client->id,
        'submitted_by' => $seller->id,
        'document' => '555666777',
        'name' => 'Cliente Admin',
        'business_name' => 'Tienda Admin',
        'address' => 'Calle 99',
    ]);

    actingAs($admin)
        ->get(route('admin.client-data-update-requests.index'))
        ->assertOk()
        ->assertSee('Actualización de datos')
        ->assertSee('555666777')
        ->assertSee('Tienda Admin');
});
