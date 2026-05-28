<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

it('allows supervisor to access interesados admin routes', function () {
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

    get(route('dashboard'))->assertRedirect(route('contacts.index'));
    get(route('contacts.index'))->assertOk();
    get(route('contacts.show', $contact))->assertOk();
});

it('blocks supervisor from full admin modules', function () {
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    actingAs($user);

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
