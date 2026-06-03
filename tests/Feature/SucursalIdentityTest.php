<?php

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Build a getRuteros SOAP response in the exact shape UserRepository::fetchRuteroData parses.
 *
 * @param  array<int, array{code:string, zone:string, route:string, day:string, address:string, name:string}>  $sucursales
 */
function fakeGetRuterosSoap(array $sucursales): string
{
    $ruteros = '';
    foreach ($sucursales as $s) {
        $ruteros .= '<aListRuteros>'
            .'<aDiaRecorrido>'.$s['day'].'</aDiaRecorrido>'
            .'<aRoute>'.$s['route'].'</aRoute>'
            .'<aZona>'.$s['zone'].'</aZona>'
            .'<aDetail><aListDetailsRuteros>'
                .'<aCustRuteroID>'.$s['code'].'</aCustRuteroID>'
                .'<aAddress>'.$s['address'].'</aAddress>'
                .'<aName>'.$s['name'].'</aName>'
            .'</aListDetailsRuteros></aDetail>'
        .'</aListRuteros>';
    }

    return '<sEnvelope><sBody><getRuterosResponse><result><agetRuterosResult>'
        .$ruteros
        .'</agetRuterosResult></result></getRuterosResponse></sBody></sEnvelope>';
}

it('derives sucursal identity from CustRuteroID when present', function () {
    expect(Zone::makeSucursalUid('SUC1', 'Calle 1'))->toBe('cust:SUC1');
    expect(Zone::makeSucursalUid('  SUC1  ', 'whatever'))->toBe('cust:SUC1');
});

it('falls back to a deterministic address-based identity when code is missing', function () {
    $a = Zone::makeSucursalUid(null, 'Calle 1');
    $b = Zone::makeSucursalUid('', '  calle   1 ');

    expect($a)->toStartWith('addr:');
    expect($a)->toBe($b); // normalized address is identity-stable
    expect($a)->not->toBe(Zone::makeSucursalUid(null, 'Calle 2'));
});

it('freezes sucursal_uid on create and never recomputes it on update', function () {
    $user = User::factory()->create();
    $zone = Zone::create([
        'user_id' => $user->id,
        'address' => 'Calle A',
        'zone' => '900',
        'route' => 'RA',
        'day' => 'L',
        'code' => null,
    ]);

    $original = $zone->sucursal_uid;
    expect($original)->toBe('addr:'.sha1('calle a'));

    // A later sync gives this same sucursal a code and a new zona — identity must not move.
    $zone->update(['code' => 'SUC-A', 'zone' => '950', 'address' => 'Calle A renamed']);

    expect($zone->fresh()->sucursal_uid)->toBe($original);
});

it('does not cross-assign zona between uncoded sucursales when routes arrive reordered', function () {
    $user = User::factory()->create();
    $a = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L', 'code' => null]);
    $b = Zone::create(['user_id' => $user->id, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M', 'code' => null]);

    // Dynamics returns the same two sucursales, still uncoded, in the opposite order.
    UserRepository::applyRoutesToZones($user, [
        ['code' => null, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M'],
        ['code' => null, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L'],
    ]);

    // Each address keeps its own zona — the old index fallback would have swapped them.
    expect($a->fresh()->zone)->toBe('900');
    expect($a->fresh()->address)->toBe('Calle A');
    expect($b->fresh()->zone)->toBe('933');
    expect($b->fresh()->address)->toBe('Calle B');
    expect(Zone::where('user_id', $user->id)->count())->toBe(2);
});

it('updates a coded sucursal zona in place without touching identity or creating duplicates', function () {
    $user = User::factory()->create();
    $a = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L', 'code' => 'SUC1']);
    $b = Zone::create(['user_id' => $user->id, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M', 'code' => 'SUC2']);

    // Reordered, and SUC1's logistics zona genuinely changed in Dynamics.
    UserRepository::applyRoutesToZones($user, [
        ['code' => 'SUC2', 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M'],
        ['code' => 'SUC1', 'address' => 'Calle A', 'zone' => '950', 'route' => 'RA', 'day' => 'L'],
    ]);

    expect($a->fresh()->zone)->toBe('950');
    expect($a->fresh()->sucursal_uid)->toBe('cust:SUC1');
    expect($b->fresh()->zone)->toBe('933');
    expect(Zone::where('user_id', $user->id)->count())->toBe(2);
});

it('matches a legacy address-keyed row once Dynamics starts returning its CustRuteroID', function () {
    $user = User::factory()->create();
    $legacy = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L', 'code' => null]);
    expect($legacy->sucursal_uid)->toBe('addr:'.sha1('calle a'));

    UserRepository::applyRoutesToZones($user, [
        ['code' => 'SUC1', 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L'],
    ]);

    // Same physical row updated (matched by code-equality fallback), no duplicate created.
    expect(Zone::where('user_id', $user->id)->count())->toBe(1);
    expect($legacy->fresh()->code)->toBe('SUC1');
    expect($legacy->fresh()->sucursal_uid)->toBe('addr:'.sha1('calle a'));
});

it('does not delete rows absent from the payload when pruning is disabled (reorder path)', function () {
    $user = User::factory()->create();
    $kept = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L', 'code' => 'SUC1']);
    $absent = Zone::create(['user_id' => $user->id, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M', 'code' => 'SUC2']);

    UserRepository::applyRoutesToZones($user, [
        ['code' => 'SUC1', 'address' => 'Calle A', 'zone' => '905', 'route' => 'RA', 'day' => 'L'],
    ], pruneMissing: false);

    expect($kept->fresh()->zone)->toBe('905');
    expect($absent->fresh())->not->toBeNull(); // preserved despite being absent and orderless
    expect(Zone::where('user_id', $user->id)->count())->toBe(2);
});

it('syncs zones end-to-end through getRuteros without cross-assigning zonas', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );

    $user = User::factory()->create(['document' => '900900900']);
    $a = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => '1', 'code' => 'SUC1']);
    $b = Zone::create(['user_id' => $user->id, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => '2', 'code' => 'SUC2']);

    // Dynamics returns the same sucursales in reversed order (the classic bug trigger).
    Http::fake([
        '*' => Http::response(fakeGetRuterosSoap([
            ['code' => 'SUC2', 'zone' => '933', 'route' => 'RB', 'day' => '2', 'address' => 'Calle B', 'name' => 'Cliente'],
            ['code' => 'SUC1', 'zone' => '900', 'route' => 'RA', 'day' => '1', 'address' => 'Calle A', 'name' => 'Cliente'],
        ]), 200),
    ]);

    $result = UserRepository::syncUserRuteroData($user);

    expect($result)->toBeTrue();
    expect(Zone::where('user_id', $user->id)->count())->toBe(2);
    // Each sucursal identity kept its own zona — no swap.
    expect($a->fresh()->zone)->toBe('900');
    expect($a->fresh()->code)->toBe('SUC1');
    expect($b->fresh()->zone)->toBe('933');
    expect($b->fresh()->code)->toBe('SUC2');
});

it('refreshes a sucursal zona end-to-end when Dynamics changes it', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );

    $user = User::factory()->create(['document' => '900900901']);
    $a = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => '1', 'code' => 'SUC1']);
    $originalUid = $a->sucursal_uid;

    Http::fake([
        '*' => Http::response(fakeGetRuterosSoap([
            ['code' => 'SUC1', 'zone' => '950', 'route' => 'RA', 'day' => '1', 'address' => 'Calle A', 'name' => 'Cliente'],
        ]), 200),
    ]);

    UserRepository::syncUserRuteroData($user);

    expect($a->fresh()->zone)->toBe('950'); // legitimate zona change is applied
    expect($a->fresh()->sucursal_uid)->toBe($originalUid); // identity frozen
    expect(Zone::where('user_id', $user->id)->count())->toBe(1);
});

it('keeps zone rows that disappear from the rutero when an order references them', function () {
    $user = User::factory()->create();
    $kept = Zone::create(['user_id' => $user->id, 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L', 'code' => 'SUC1']);
    $dropped = Zone::create(['user_id' => $user->id, 'address' => 'Calle B', 'zone' => '933', 'route' => 'RB', 'day' => 'M', 'code' => 'SUC2']);

    Order::create(['user_id' => $user->id, 'zone_id' => $dropped->id, 'total' => 0, 'discount' => 0]);

    UserRepository::applyRoutesToZones($user, [
        ['code' => 'SUC1', 'address' => 'Calle A', 'zone' => '900', 'route' => 'RA', 'day' => 'L'],
    ]);

    expect($kept->fresh())->not->toBeNull();
    expect($dropped->fresh())->not->toBeNull(); // referenced by an order → preserved
});
