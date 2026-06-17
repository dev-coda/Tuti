<?php

use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('parses rutero day values into carbon day of week', function () {
    expect(Zone::carbonDayOfWeekFromDay('5'))->toBe(5)
        ->and(Zone::carbonDayOfWeekFromDay('5-Viernes'))->toBe(5)
        ->and(Zone::carbonDayOfWeekFromDay('3-Miércoles'))->toBe(3)
        ->and(Zone::carbonDayOfWeekFromDay('miercoles'))->toBe(3)
        ->and(Zone::carbonDayOfWeekFromDay('Sábado'))->toBe(6)
        ->and(Zone::carbonDayOfWeekFromDay('7'))->toBe(0)
        ->and(Zone::carbonDayOfWeekFromDay(''))->toBeNull()
        ->and(Zone::carbonDayOfWeekFromDay(null))->toBeNull();
});

it('shows only route clients whose visit day matches today in mi ruta tab', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $todayDow = Carbon::now('America/Bogota')->dayOfWeek;
    $otherDow = ($todayDow + 1) % 7;

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
        'day' => (string) $todayDow,
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
        'day' => (string) $todayDow,
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
        ->assertDontSee('Cliente Otro Dia')
        ->assertDontSee('Cliente Otra Ruta');
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
