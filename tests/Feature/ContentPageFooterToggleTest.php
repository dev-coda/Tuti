<?php

use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('can deactivate show_in_footer and enabled on update', function () {
    $page = ContentPage::create([
        'title' => 'Página Test',
        'slug' => 'pagina-test',
        'content' => 'Contenido',
        'enabled' => true,
        'show_in_footer' => true,
    ]);

    // Edit form submits hidden 0 inputs when the toggles are unchecked.
    actingAs($this->admin)
        ->put(route('content-pages.update', $page), [
            'title' => 'Página Test',
            'slug' => 'pagina-test',
            'content' => 'Contenido',
            'enabled' => '0',
            'show_in_footer' => '0',
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->show_in_footer)->toBeFalse()
        ->and($page->enabled)->toBeFalse();
});

it('can activate show_in_footer on update', function () {
    $page = ContentPage::create([
        'title' => 'Página Test',
        'slug' => 'pagina-test',
        'content' => 'Contenido',
        'enabled' => false,
        'show_in_footer' => false,
    ]);

    actingAs($this->admin)
        ->put(route('content-pages.update', $page), [
            'title' => 'Página Test',
            'slug' => 'pagina-test',
            'content' => 'Contenido',
            'enabled' => '1',
            'show_in_footer' => '1',
        ])
        ->assertRedirect();

    $page->refresh();
    expect($page->show_in_footer)->toBeTrue()
        ->and($page->enabled)->toBeTrue();
});
