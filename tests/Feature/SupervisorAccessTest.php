<?php

use App\Models\Contact;
use App\Models\Order;
use App\Models\SupervisorRoute;
use App\Models\User;
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

    $contact = Contact::create([
        'name' => 'Contacto Supervisor',
        'email' => 'contacto.supervisor@example.com',
        'phone' => '3001112233',
        'business_name' => 'Negocio Supervisor',
        'status' => 'interesado',
    ]);

    actingAs($user);

    get(route('dashboard'))->assertStatus(302);
    get(route('contacts.index'))->assertStatus(302);
    get(route('contacts.show', $contact))->assertStatus(302);
    get(route('orders.index'))->assertStatus(302);
    get(route('users.index'))->assertStatus(302);
});

it('allows supervisor to use seller setclient route', function () {
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    actingAs($user);

    // Route access is allowed for supervisors; request itself fails validation.
    $response = $this->post(route('seller.setclient'), []);
    $response->assertSessionHasErrors(['document']);
});

it('shows mis rutas tab with orders for the selected assigned route', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignmentA = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '101',
        'route' => '0001',
    ]);
    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '102',
        'route' => '0002',
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
        ->assertSee('Mis Rutas')
        ->assertSee('Zona 101 — Ruta 0001')
        ->assertSee('Zona 102 — Ruta 0002')
        ->assertSee('Pedidos de la Zona 101 — Ruta 0001')
        ->assertSee('1 pedido')
        ->assertSee('Cliente Ruta Asignada')
        ->assertDontSee('data-tab-trigger="mi-ruta"', false);

    // Mis Rutas scopes by zone+route; the other-route client must not appear
    // inside that panel even if Pedidos del día lists zona-level orders.
    expect($response->getContent())
        ->toContain('data-tab-panel="mis-rutas"')
        ->and(substr_count($response->getContent(), 'Cliente Ruta Asignada'))->toBeGreaterThan(0);

    $misRutasPanel = Str::between(
        $response->getContent(),
        'data-tab-panel="mis-rutas"',
        'data-tab-panel="orders"'
    );

    expect($misRutasPanel)
        ->toContain('Cliente Ruta Asignada')
        ->not->toContain('Cliente Otra Ruta');
});

it('does not show mis rutas orders from unassigned routes', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $assignment = SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '200',
        'route' => '0010',
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
        ->assertSee('No hay pedidos en esta ruta para el rango seleccionado.');
});
