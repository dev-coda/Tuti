<?php

use App\Models\InventorySyncLog;
use App\Models\User;
use App\Models\ZoneWarehouse;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('reports configured bodegas that had no successful sync yesterday', function () {
    $tz = InventorySyncLog::reportTimezone();
    Carbon::setTestNow(Carbon::parse('2026-08-17 21:30:00', $tz));

    ZoneWarehouse::create(['zone_code' => '101', 'bodega_code' => 'BOD-OK']);
    ZoneWarehouse::create(['zone_code' => '102', 'bodega_code' => 'BOD-FAIL']);
    ZoneWarehouse::create(['zone_code' => '103', 'bodega_code' => 'BOD-MISS']);

    InventorySyncLog::create([
        'bodega_code' => 'BOD-OK',
        'status' => 'error',
        'error_message' => 'first attempt failed',
    ]);
    InventorySyncLog::create([
        'bodega_code' => 'BOD-OK',
        'status' => 'success',
        'skus_received' => 10,
    ]);
    InventorySyncLog::create([
        'bodega_code' => 'BOD-FAIL',
        'status' => 'error',
        'error_message' => 'HTTP request failed with status 500',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00', $tz));

    $report = InventorySyncLog::unsyncedBodegasYesterday();

    expect($report->pluck('bodega_code')->all())->toBe(['BOD-FAIL', 'BOD-MISS'])
        ->and($report->firstWhere('bodega_code', 'BOD-FAIL')['last_error'])->toBe('HTTP request failed with status 500')
        ->and($report->firstWhere('bodega_code', 'BOD-MISS')['attempt_count'])->toBe(0)
        ->and($report->firstWhere('bodega_code', 'BOD-MISS')['last_status'])->toBeNull();
});

it('shows yesterday unsynced bodegas on the admin inventory logs page', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $tz = InventorySyncLog::reportTimezone();
    Carbon::setTestNow(Carbon::parse('2026-08-17 21:30:00', $tz));

    ZoneWarehouse::create(['zone_code' => '201', 'bodega_code' => 'MDTAT']);
    InventorySyncLog::create([
        'bodega_code' => 'MDTAT',
        'status' => 'error',
        'error_message' => 'Dynamics timeout',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-18 09:15:00', $tz));

    actingAs($admin)
        ->get(route('settings.inventory-logs'))
        ->assertOk()
        ->assertSee('Bodegas no sincronizadas ayer')
        ->assertSee('MDTAT')
        ->assertSee('Dynamics timeout');

    actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Bodegas sin sincronización exitosa ayer')
        ->assertSee('MDTAT');
});
