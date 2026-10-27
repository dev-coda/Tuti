<?php

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function makeBannerAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeBanner(array $overrides = []): Banner
{
    return Banner::create(array_merge([
        'path' => 'banners/slide.jpg',
        'url' => 'https://example.com/banner',
        'type_id' => 1,
        'is_active' => true,
    ], $overrides));
}

it('defaults new banners to active so existing slides stay visible', function () {
    $banner = Banner::create([
        'path' => 'banners/legacy.jpg',
        'type_id' => 1,
    ]);

    expect($banner->fresh()->is_active)->toBeTrue();
});

it('lets an admin create a banner as active or inactive', function () {
    Storage::fake('public');
    $admin = makeBannerAdmin();

    actingAs($admin)
        ->post(route('banners.store'), [
            'file' => UploadedFile::fake()->image('hero.jpg'),
            'url' => 'https://example.com/on',
            'type_id' => 1,
            'is_active' => '1',
        ])
        ->assertRedirect(route('banners.index'));

    expect(Banner::where('url', 'https://example.com/on')->first()->is_active)->toBeTrue();

    actingAs($admin)
        ->post(route('banners.store'), [
            'file' => UploadedFile::fake()->image('hidden.jpg'),
            'url' => 'https://example.com/off',
            'type_id' => 1,
        ])
        ->assertRedirect(route('banners.index'));

    expect(Banner::where('url', 'https://example.com/off')->first()->is_active)->toBeFalse();
});

it('lets an admin update the active checkbox on edit', function () {
    $admin = makeBannerAdmin();
    $banner = makeBanner();

    actingAs($admin)
        ->get(route('banners.edit', $banner))
        ->assertOk()
        ->assertSee('name="is_active"', false);

    actingAs($admin)
        ->put(route('banners.update', $banner), [
            'url' => $banner->url,
            'type_id' => $banner->type_id,
        ])
        ->assertRedirect(route('banners.index'));

    expect($banner->fresh()->is_active)->toBeFalse();

    actingAs($admin)
        ->put(route('banners.update', $banner), [
            'url' => $banner->url,
            'type_id' => $banner->type_id,
            'is_active' => '1',
        ])
        ->assertRedirect(route('banners.index'));

    expect($banner->fresh()->is_active)->toBeTrue();
});

it('shows status on the admin index and toggles it quickly', function () {
    $admin = makeBannerAdmin();
    $banner = makeBanner(['url' => 'https://example.com/toggle']);

    actingAs($admin)
        ->get(route('banners.index'))
        ->assertOk()
        ->assertSee('Activo')
        ->assertSee('Desactivar');

    actingAs($admin)
        ->post(route('banners.toggle', $banner))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($banner->fresh()->is_active)->toBeFalse();

    actingAs($admin)
        ->get(route('banners.index'))
        ->assertOk()
        ->assertSee('Inactivo')
        ->assertSee('Activar');

    actingAs($admin)
        ->post(route('banners.toggle', $banner))
        ->assertRedirect();

    expect($banner->fresh()->is_active)->toBeTrue();
});

it('shows only active slides on the homepage carousel', function () {
    makeBanner([
        'path' => 'banners/enabled.jpg',
        'url' => 'https://example.com/visible',
        'type_id' => 1,
        'is_active' => true,
    ]);
    makeBanner([
        'path' => 'banners/disabled.jpg',
        'url' => 'https://example.com/hidden',
        'type_id' => 1,
        'is_active' => false,
    ]);
    makeBanner([
        'path' => 'banners/ads-enabled.jpg',
        'url' => 'https://example.com/ads',
        'type_id' => 2,
        'is_active' => true,
    ]);
    makeBanner([
        'path' => 'banners/ads-disabled.jpg',
        'url' => 'https://example.com/ads-off',
        'type_id' => 2,
        'is_active' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('banners/enabled.jpg')
        ->assertSee('https://example.com/visible')
        ->assertSee('banners/ads-enabled.jpg')
        ->assertDontSee('banners/disabled.jpg')
        ->assertDontSee('https://example.com/hidden')
        ->assertDontSee('banners/ads-disabled.jpg');
});

it('hides the homepage carousel when every slide is disabled', function () {
    makeBanner([
        'path' => 'banners/off-main.jpg',
        'url' => 'https://example.com/off-main',
        'type_id' => 1,
        'is_active' => false,
    ]);
    makeBanner([
        'path' => 'banners/off-ads.jpg',
        'url' => 'https://example.com/off-ads',
        'type_id' => 2,
        'is_active' => false,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee("id='banners'", false)
        ->assertDontSee("id='ads'", false)
        ->assertDontSee('banners/off-main.jpg')
        ->assertDontSee('banners/off-ads.jpg');
});
