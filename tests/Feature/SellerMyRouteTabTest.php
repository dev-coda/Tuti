<?php

use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('parses rutero day values into carbon day of week', function () {
    expect(Zone::carbonDayOfWeekFromDay('5'))->toBe(5)
        ->and(Zone::carbonDayOfWeekFromDay('5-Viernes'))->toBe(5)
        ->and(Zone::carbonDayOfWeekFromDay('3-Miércoles'))->toBe(3)
        ->and(Zone::carbonDayOfWeekFromDay('miercoles'))->toBe(3)
        ->and(Zone::carbonDayOfWeekFromDay('Sábado'))->toBe(6)
        ->and(Zone::carbonDayOfWeekFromDay('7'))->toBe(0)
        ->and(Zone::carbonDayOfWeekFromDay(''))->toBeNull()
        ->and(Zone::carbonDayOfWeekFromDay(null))->toBeNull()
        ->and(Zone::deliveryDayOfWeekForVisitOn(Carbon::parse('2026-11-12', 'America/Bogota')))->toBe(Carbon::FRIDAY)
        ->and(Zone::deliveryDayOfWeekForVisitOn(Carbon::parse('2026-11-13', 'America/Bogota')))->toBe(Carbon::MONDAY)
        ->and(Zone::deliveryDayOfWeekForVisitOn(Carbon::parse('2026-11-09', 'America/Bogota')))->toBe(Carbon::TUESDAY);
});

it('shows route clients the vendor visits today and delivers tomorrow', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    Carbon::setTestNow(Carbon::parse('2026-11-13 09:00:00', 'America/Bogota'));
    $deliveryDow = Zone::deliveryDayOfWeekForVisitOn(Carbon::now('America/Bogota'));
    $otherDow = Carbon::FRIDAY;

    $clientToday = User::factory()->create([
        'name' => 'Cliente Visita Hoy',
        'document' => '901234567',
        'business_name' => 'Tienda El Progreso',
        'phone' => '6012345678',
        'mobile_phone' => '3001234567',
        'whatsapp' => '3007654321',
        'email' => 'tienda@example.com',
    ]);
    Zone::create([
        'user_id' => $clientToday->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => (string) $deliveryDow,
        'address' => 'Calle 1 # 2-3',
        'code' => 'RUT-HOY',
    ]);

    $clientOtherDay = User::factory()->create(['name' => 'Cliente Otro Dia', 'document' => '444555666']);
    Zone::create([
        'user_id' => $clientOtherDay->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => (string) $otherDow,
        'address' => 'Calle 4 # 5-6',
        'code' => 'RUT-OTRO-DIA',
    ]);

    $clientOtherRoute = User::factory()->create(['name' => 'Cliente Otra Ruta', 'document' => '777888999']);
    Zone::create([
        'user_id' => $clientOtherRoute->id,
        'zone' => '301',
        'route' => 'R200',
        'day' => (string) $deliveryDow,
        'address' => 'Calle 7 # 8-9',
        'code' => 'RUT-OTRA-RUTA',
    ]);

    $response = actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100');

    $response->assertOk()
        ->assertSee('Mi Ruta')
        ->assertSee('Cliente Visita Hoy')
        ->assertSee('Tienda El Progreso')
        ->assertSee('901234567')
        ->assertSee('6012345678')
        ->assertSee('3001234567')
        ->assertSee('3007654321')
        ->assertSee('tienda@example.com')
        ->assertSee('Calle 1 # 2-3')
        ->assertSee('Entrega:')
        ->assertSee('Lunes')
        ->assertDontSee('Cliente Otro Dia')
        ->assertDontSee('Cliente Otra Ruta');

    Carbon::setTestNow(Carbon::parse('2026-11-12 09:00:00', 'America/Bogota'));

    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertOk()
        ->assertSee('Cliente Otro Dia')
        ->assertSee('Entrega:')
        ->assertSee('Viernes')
        ->assertDontSee('Cliente Visita Hoy');

    Carbon::setTestNow();
});

it('filters mi ruta clients by name or document', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    Carbon::setTestNow(Carbon::parse('2026-11-13 09:00:00', 'America/Bogota'));
    $deliveryDow = (string) Zone::deliveryDayOfWeekForVisitOn(Carbon::now('America/Bogota'));

    $clientA = User::factory()->create([
        'name' => 'Maria Lopez',
        'business_name' => 'Tienda La Esquina',
        'document' => '901234567',
    ]);
    Zone::create([
        'user_id' => $clientA->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => $deliveryDow,
        'address' => 'Calle 1 # 2-3',
        'code' => 'RUT-A',
    ]);

    $clientB = User::factory()->create([
        'name' => 'Pedro Gomez',
        'business_name' => 'Supermercado Central',
        'document' => '800111222',
    ]);
    Zone::create([
        'user_id' => $clientB->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => $deliveryDow,
        'address' => 'Calle 4 # 5-6',
        'code' => 'RUT-B',
    ]);

    // By partial name (case-insensitive).
    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100&ruta_q=maria')
        ->assertOk()
        ->assertSee('Maria Lopez')
        ->assertDontSee('Pedro Gomez');

    // By business name.
    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100&ruta_q=Supermercado')
        ->assertOk()
        ->assertSee('Pedro Gomez')
        ->assertDontSee('Maria Lopez');

    // By partial document.
    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100&ruta_q=901234')
        ->assertOk()
        ->assertSee('Maria Lopez')
        ->assertDontSee('Pedro Gomez');

    // No matches shows the search-specific empty state.
    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100&ruta_q=inexistente')
        ->assertOk()
        ->assertSee('que coincidan con')
        ->assertDontSee('Maria Lopez')
        ->assertDontSee('Pedro Gomez');
});

it('lists the routes available for the seller zone', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create(['document' => '123123123']);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-1',
    ]);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '999',
        'route' => 'R900',
        'day' => '1',
        'address' => 'Calle 9',
        'code' => 'RUT-9',
    ]);

    $response = actingAs($seller)->get('/ordenes?tab=mi-ruta');

    $response->assertOk()
        ->assertSee('R100')
        ->assertDontSee('R900');
});
