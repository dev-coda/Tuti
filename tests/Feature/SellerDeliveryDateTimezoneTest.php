<?php

use App\Models\Setting;
use App\Models\User;
use App\Repositories\OrderRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Cache::flush();
    Setting::updateOrCreate(
        ['key' => 'closing_time'],
        ['name' => 'Closing time', 'value' => '17', 'show' => false]
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('parses date-only values without shifting a day west of UTC', function () {
    $parsed = OrderRepository::parseBusinessDate('2026-10-02');

    expect($parsed->format('Y-m-d'))->toBe('2026-10-02')
        ->and($parsed->timezoneName)->toBe('America/Bogota');

    // Classic footgun: UTC midnight of Oct 2 becomes Oct 1 evening in Bogota.
    $shifted = Carbon::parse('2026-10-02')->timezone('America/Bogota');
    expect($shifted->format('Y-m-d'))->toBe('2026-10-01');
});

it('calculates next business day on the Colombia calendar when UTC has already rolled forward', function () {
    // Thursday 21:00 America/Bogota = Friday 02:00 UTC — after closing (17).
    Carbon::setTestNow(Carbon::parse('2026-10-02 02:00:00', 'UTC'));

    expect(Carbon::now('America/Bogota')->format('Y-m-d'))->toBe('2026-10-01')
        ->and(Carbon::now('UTC')->format('Y-m-d'))->toBe('2026-10-02');

    // After closing on Thursday Colombia → treat as Friday start → next BD = Monday Oct 5.
    expect(OrderRepository::getBusinessDay(0))->toBe('2026-10-05');
});

it('does not treat UTC Friday as Colombia Friday before a late closing hour', function () {
    Cache::flush();
    Setting::updateOrCreate(
        ['key' => 'closing_time'],
        ['name' => 'Closing time', 'value' => '23', 'show' => false]
    );

    // Thursday 21:00 Bogota = Friday 02:00 UTC, still before closing 23 in Colombia.
    Carbon::setTestNow(Carbon::parse('2026-10-02 02:00:00', 'UTC'));

    // Correct: still Thursday before closing → next business day = Friday Oct 2.
    // UTC-based math would wrongly start from Friday and return Monday.
    expect(OrderRepository::getBusinessDay(0))->toBe('2026-10-02');
});

it('serves the delivery date on the web session so sellers are not treated as guests', function () {
    $route = Route::getRoutes()->match(Request::create('/api/delivery-date/tronex', 'GET'));

    expect($route->gatherMiddleware())->toContain('web');
});

it('returns the Colombia next business day for sellers on the delivery-date API', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-01 15:00:00', 'UTC')); // 10:00 Bogota Thursday

    $seller = User::factory()->create();
    $seller->assignRole('seller');

    actingAs($seller);

    $response = getJson('/api/delivery-date/tronex');

    $response->assertOk()
        ->assertJsonPath('raw_date', '2026-10-02'); // Friday

    expect($response->json('date'))->toContain('2')
        ->and($response->json('date'))->toContain('Octubre');
});
