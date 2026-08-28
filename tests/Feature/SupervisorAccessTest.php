<?php

use App\Models\Contact;
use App\Models\Order;
use App\Models\SupervisorRoute;
use App\Models\User;
use App\Models\ZoneRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

it('blocks supervisor from the admin zone', function () {
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    actingAs($user);

    get(route('dashboard'))->assertStatus(302);
    get(route('orders.index'))->assertStatus(302);
    get(route('users.index'))->assertStatus(302);
});

it('allows supervisor to access interesados and edit them', function () {
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    $contact = Contact::create([
        'name' => 'Contacto Supervisor',
        'email' => 'contacto.supervisor@example.com',
        'phone' => '3001112233',
        'business_name' => 'Negocio Supervisor',
        'status' => 'interesado',
        'nit' => '900111222',
        'address' => 'Calle 1',
        'new_client_payload' => ['Zona' => '101'],
    ]);

    actingAs($user);

    get(route('contacts.index'))
        ->assertOk()
        ->assertSee('Interesados')
        ->assertSee('Contacto Supervisor');

    get(route('contacts.show', $contact))
        ->assertOk()
        ->assertSee('Contacto #'.$contact->id);

    $this->put(route('contacts.update', $contact), [
        'quick_status_update' => 1,
        'status' => 'contactado',
    ])->assertRedirect();

    expect($contact->fresh()->status)->toBe('contactado');
});

it('allows supervisor to filter interesados by zone', function () {
    ZoneRoute::create(['zone' => '101', 'route' => '1001']);
    ZoneRoute::create(['zone' => '202', 'route' => '2002']);

    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    Contact::create([
        'name' => 'Interesado Zona 101',
        'email' => 'z101@example.com',
        'phone' => '3001112233',
        'business_name' => 'Negocio 101',
        'status' => 'interesado',
        'nit' => '900101101',
        'new_client_payload' => ['Zona' => '101'],
    ]);

    Contact::create([
        'name' => 'Interesado Zona 202',
        'email' => 'z202@example.com',
        'phone' => '3002223344',
        'business_name' => 'Negocio 202',
        'status' => 'interesado',
        'nit' => '900202202',
        'new_client_payload' => ['Zona' => '202'],
    ]);

    actingAs($supervisor);

    get(route('contacts.index'))
        ->assertOk()
        ->assertSee('Interesado Zona 101')
        ->assertSee('Interesado Zona 202');

    get(route('contacts.index', ['zone' => '101']))
        ->assertOk()
        ->assertSee('Interesado Zona 101')
        ->assertDontSee('Interesado Zona 202');
});

it('allows supervisor to use seller setclient route', function () {
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    actingAs($user);

    // Route access is allowed for supervisors; request itself fails validation.
    $response = $this->post(route('seller.setclient'), []);
    $response->assertSessionHasErrors(['document']);
});

it('shows mis zonas tab with all orders for the selected assigned zone', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignmentA = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => null,
    ]);
    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '102',
        'route' => null,
    ]);

    $clientOnRoute = User::factory()->create(['name' => 'Cliente Ruta Asignada']);
    $zoneOnRoute = $clientOnRoute->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Otra Ruta']);
    $zoneOther = $clientOtherRoute->zones()->create([
        'zone' => '101',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 2',
        'code' => 'C199',
    ]);

    Order::create([
        'user_id' => $clientOnRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOnRoute->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0001',
            'code' => 'C101',
        ],
    ]);

    Order::create([
        'user_id' => $clientOtherRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOther->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0099',
            'code' => 'C199',
        ],
    ]);

    actingAs($supervisor);

    $response = get(route('clients.orders.index', ['tab' => 'mis-rutas', 'sr' => $assignmentA->id]))
        ->assertOk()
        ->assertSee('Mis Zonas')
        ->assertSee('Zona 101')
        ->assertSee('Zona 102')
        ->assertSee('Pedidos de la Zona 101')
        ->assertSee('2 pedidos')
        ->assertSee('Cliente Ruta Asignada')
        ->assertDontSee('data-tab-trigger="mi-ruta"', false);

    $misRutasPanel = Str::between(
        $response->getContent(),
        'data-tab-panel="mis-rutas"',
        'data-tab-panel="orders"'
    );

    expect($misRutasPanel)
        ->toContain('Cliente Ruta Asignada')
        ->toContain('Cliente Otra Ruta');
});

it('filters mis zonas orders by ruta within the selected zone', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignment = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => null,
    ]);

    $clientOnRoute = User::factory()->create(['name' => 'Cliente Ruta 0001']);
    $zoneOnRoute = $clientOnRoute->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Ruta 0099']);
    $zoneOther = $clientOtherRoute->zones()->create([
        'zone' => '101',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 2',
        'code' => 'C199',
    ]);

    Order::create([
        'user_id' => $clientOnRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOnRoute->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0001',
            'code' => 'C101',
        ],
    ]);

    Order::create([
        'user_id' => $clientOtherRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOther->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0099',
            'code' => 'C199',
        ],
    ]);

    actingAs($supervisor);

    $response = get(route('clients.orders.index', [
        'tab' => 'mis-rutas',
        'sr' => $assignment->id,
        'sr_ruta' => '0001',
    ]))
        ->assertOk()
        ->assertSee('Pedidos de la Zona 101 — Ruta 0001')
        ->assertSee('1 pedido');

    $misRutasPanel = Str::between(
        $response->getContent(),
        'data-tab-panel="mis-rutas"',
        'data-tab-panel="orders"'
    );

    expect($misRutasPanel)
        ->toContain('Cliente Ruta 0001')
        ->not->toContain('Cliente Ruta 0099');
});

it('does not show mis zonas orders from unassigned zones', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignment = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '200',
        'route' => null,
    ]);

    $client = User::factory()->create(['name' => 'Cliente Fuera']);
    $zone = $client->zones()->create([
        'zone' => '999',
        'route' => '9999',
        'day' => 'Lunes',
        'address' => 'Calle X',
        'code' => 'CX',
    ]);

    Order::create([
        'user_id' => $client->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1000,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zone->id,
        'zone_snapshot' => [
            'zone' => '999',
            'route' => '9999',
            'code' => 'CX',
        ],
    ]);

    actingAs($supervisor);

    get(route('clients.orders.index', ['tab' => 'mis-rutas', 'sr' => $assignment->id]))
        ->assertOk()
        ->assertDontSee('Cliente Fuera')
        ->assertSee('No hay pedidos en esta zona para el rango seleccionado.');
});

it('shows mis zonas orders only for an assigned supervisor route', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignment = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => '0001',
    ]);

    $clientOnRoute = User::factory()->create(['name' => 'Cliente Ruta Asignada']);
    $zoneOnRoute = $clientOnRoute->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Otra Ruta']);
    $zoneOther = $clientOtherRoute->zones()->create([
        'zone' => '101',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 2',
        'code' => 'C199',
    ]);

    Order::create([
        'user_id' => $clientOnRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOnRoute->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0001',
            'code' => 'C101',
        ],
    ]);

    Order::create([
        'user_id' => $clientOtherRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOther->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0099',
            'code' => 'C199',
        ],
    ]);

    actingAs($supervisor);

    $response = get(route('clients.orders.index', ['tab' => 'mis-rutas', 'sr' => $assignment->id]))
        ->assertOk()
        ->assertSee('Zona 101 — Ruta 0001')
        ->assertSee('Pedidos de la Zona 101 — Ruta 0001')
        ->assertSee('1 pedido');

    $misRutasPanel = Str::between(
        $response->getContent(),
        'data-tab-panel="mis-rutas"',
        'data-tab-panel="orders"'
    );

    expect($misRutasPanel)
        ->toContain('Cliente Ruta Asignada')
        ->not->toContain('Cliente Otra Ruta')
        ->not->toContain('Todas las rutas');
});

it('limits pedidos del dia and recientes to the supervisor assigned route', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => '0001',
    ]);

    $clientOnRoute = User::factory()->create(['name' => 'Cliente Cubierto']);
    $zoneOnRoute = $clientOnRoute->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Fuera De Ruta']);
    $zoneOther = $clientOtherRoute->zones()->create([
        'zone' => '101',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 2',
        'code' => 'C199',
    ]);

    Order::create([
        'user_id' => $clientOnRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOnRoute->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0001',
            'code' => 'C101',
        ],
    ]);

    Order::create([
        'user_id' => $clientOtherRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOther->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0099',
            'code' => 'C199',
        ],
    ]);

    actingAs($supervisor);

    $todayHtml = get(route('clients.orders.index', ['tab' => 'orders-today']))->assertOk()->getContent();
    $recentHtml = get(route('clients.orders.index', ['tab' => 'orders']))->assertOk()->getContent();

    expect($todayHtml)
        ->toContain('Cliente Cubierto')
        ->not->toContain('Cliente Fuera De Ruta');

    expect($recentHtml)
        ->toContain('Cliente Cubierto')
        ->not->toContain('Cliente Fuera De Ruta');
});

it('does not let zona principal bypass a route-specific supervisor assignment', function () {
    $supervisor = User::factory()->create(['zone' => '101']);
    $supervisor->assignRole('supervisor');

    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => '0001',
    ]);

    expect($supervisor->fresh()->supervisedCoverages())->toEqualCanonicalizing([
        ['zone' => '101', 'route' => '0001'],
    ]);

    $clientOnRoute = User::factory()->create(['name' => 'Cliente Ruta Principal']);
    $zoneOnRoute = $clientOnRoute->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Zona Completa']);
    $zoneOther = $clientOtherRoute->zones()->create([
        'zone' => '101',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 2',
        'code' => 'C199',
    ]);

    Order::create([
        'user_id' => $clientOnRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOnRoute->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0001',
            'code' => 'C101',
        ],
    ]);

    Order::create([
        'user_id' => $clientOtherRoute->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneOther->id,
        'zone_snapshot' => [
            'zone' => '101',
            'route' => '0099',
            'code' => 'C199',
        ],
    ]);

    actingAs($supervisor);

    $html = get(route('clients.orders.index', ['tab' => 'orders']))->assertOk()->getContent();

    expect($html)
        ->toContain('Cliente Ruta Principal')
        ->not->toContain('Cliente Zona Completa');
});

it('loads the default mi cuenta tab and account tab for supervisors', function () {
    $supervisor = User::factory()->create(['name' => 'Supervisor Cuenta']);
    $supervisor->assignRole('supervisor');

    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => null,
    ]);

    actingAs($supervisor);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('Mi Cuenta')
        ->assertSee('data-tab-trigger="mis-rutas"', false)
        ->assertSee('Mis Zonas');

    get(route('clients.orders.index', ['tab' => 'account']))
        ->assertOk()
        ->assertSee('Información de Cuenta')
        ->assertSee('Información Personal')
        ->assertSee($supervisor->email);
});
