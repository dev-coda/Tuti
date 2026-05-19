<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

afterEach(function () {
    \Mockery::close();
});

function fakeRuteroResult(?string $mobile = '3001234567', ?string $phone = null, ?string $whatsapp = null): array
{
    return [
        'name' => 'Cliente Tronex',
        'routes' => [
            [
                'route' => 'R1',
                'zone' => 'Z1',
                'day' => '1',
                'address' => 'Calle 10 # 20-30',
                'code' => 'COD-1',
                'mobile_phone' => $mobile,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
            ],
        ],
    ];
}

it('requires phone verification after document lookup', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult());

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson([
            'success' => true,
            'requires_phone_verification' => true,
        ]);
});

it('creates pending user and redirects when phone matches', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult('3001234567'));

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson(['requires_phone_verification' => true]);

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
        'phone' => '+57 300 123 4567',
    ])->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonPath('redirect', route('tronex.complete-profile'));

    $user = User::where('document', '123456789')->first();
    expect($user)->not()->toBeNull();
    expect($user->tronex_migration_pending)->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('blocks migration when phone does not match', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult('3001234567'));

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson(['requires_phone_verification' => true]);

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
        'phone' => '3110000000',
    ])->assertStatus(422)
        ->assertJson([
            'success' => false,
            'verification_failed' => true,
        ]);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['document' => '123456789']);
});

it('blocks migration when document is not found in tronex', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(null);

    $this->postJson(route('tronex.migrate'), [
        'document' => '999999999',
    ])->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

it('uses phone fallback precedence and allows country-prefix tolerant matching', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult(null, '3001234567', '3209999999'));

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson(['requires_phone_verification' => true]);

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
        'phone' => '573001234567',
    ])->assertOk()
        ->assertJsonPath('redirect', route('tronex.complete-profile'));
});

it('returns 429 after repeated document lookup failures', function () {
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->times(5)
        ->andReturn(null);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('tronex.migrate'), [
            'document' => '111111111',
        ])->assertStatus(422);
    }

    $this->postJson(route('tronex.migrate'), [
        'document' => '111111111',
    ])->assertStatus(429);
});

it('prefers rutero phone over mismatched user phone columns', function () {
    User::create([
        'name' => 'Cliente Pendiente',
        'email' => 'tronex.rutero-first@tuti.com',
        'document' => '123456789',
        'mobile_phone' => '3111111111',
        'password' => Hash::make('password123'),
        'status_id' => User::PENDING,
        'tronex_migration_pending' => true,
    ]);

    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult('3001234567'));

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson(['requires_phone_verification' => true]);

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
        'phone' => '3001234567',
    ])->assertOk()
        ->assertJsonPath('redirect', route('tronex.complete-profile'));
});

it('reuses pending tronex user after successful verification', function () {
    $existing = User::create([
        'name' => 'Cliente Pendiente',
        'email' => 'tronex.pending@tuti.com',
        'document' => '123456789',
        'mobile_phone' => '3001234567',
        'password' => Hash::make('password123'),
        'status_id' => User::PENDING,
        'tronex_migration_pending' => true,
    ]);

    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->once()
        ->andReturn(fakeRuteroResult());

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
    ])->assertOk()
        ->assertJson(['requires_phone_verification' => true]);

    $this->postJson(route('tronex.migrate'), [
        'document' => '123456789',
        'phone' => '3001234567',
    ])->assertOk()
        ->assertJsonPath('redirect', route('tronex.complete-profile'));

    expect(User::where('document', '123456789')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing->fresh());
    $this->get(route('tronex.complete-profile'))->assertOk();
});
