<?php

use App\Exports\UsersExport;
use App\Models\City;
use App\Models\ExportFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Jobs\QueueExport;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

it('queues the clients export asynchronously instead of downloading inline', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    User::factory()->create([
        'name' => 'Cliente Exportable',
        'email' => 'cliente.export@example.com',
        'phone' => '3001112233',
        'mobile_phone' => '3104445566',
    ]);

    actingAs($admin);

    get(route('admin.export.users'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ExportFile::query()->where('type', 'clients')->count())->toBe(1);

    $exportFile = ExportFile::query()->where('type', 'clients')->first();
    expect($exportFile->status)->toBe(ExportFile::STATUS_PROCESSING)
        ->and($exportFile->user_id)->toBe($admin->id)
        ->and($exportFile->file_path)->toStartWith('exports/clients/');

    Queue::assertPushed(QueueExport::class);
});

it('maps email and phone numbers plus zone logistics into the export row', function () {
    $state = \App\Models\State::query()->create(['name' => 'Cundinamarca']);
    $city = City::query()->create([
        'name' => 'Bogotá',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);

    $client = User::factory()->create([
        'name' => 'Ana Cliente',
        'business_name' => 'Tienda Ana',
        'document' => '900123456',
        'email' => 'ana.cliente@example.com',
        'phone' => '6015550101',
        'mobile_phone' => '3005550101',
        'whatsapp' => '3005550101',
        'city_id' => $city->id,
        'zone' => '101',
        'status_id' => User::ACTIVE,
        'client_status' => User::CLIENT_STATUS_CLIENTE,
    ]);

    $client->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1 #2-3',
        'code' => 'C101',
        'zip_code' => '110111',
        'dane_code' => '11001',
        'fulfillment_provider_48h' => 'tronex',
    ]);

    $client->load(['city', 'zones']);

    $export = new UsersExport();
    $row = $export->map($client);
    $headings = $export->headings();

    expect($export)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);

    expect($headings)->toContain('Email', 'Teléfono', 'Celular', 'WhatsApp', 'Ruta', 'Dirección');

    $indexed = array_combine($headings, $row);

    expect($indexed['Email'])->toBe('ana.cliente@example.com')
        ->and($indexed['Teléfono'])->toBe('6015550101')
        ->and($indexed['Celular'])->toBe('3005550101')
        ->and($indexed['WhatsApp'])->toBe('3005550101')
        ->and($indexed['Nombre'])->toBe('Ana Cliente')
        ->and($indexed['Razón Social'])->toBe('Tienda Ana')
        ->and($indexed['Documento'])->toBe('900123456')
        ->and($indexed['Ciudad'])->toBe('Bogotá')
        ->and($indexed['Zona'])->toBe('101')
        ->and($indexed['Ruta'])->toBe('0001')
        ->and($indexed['Dirección'])->toBe('Calle 1 #2-3')
        ->and($indexed['Código Cliente'])->toBe('C101')
        ->and($indexed['Puede Comprar'])->toBe('Activo');
});

it('excludes staff users from the clients export query', function () {
    $client = User::factory()->create(['email' => 'solo.cliente@example.com']);
    $seller = User::factory()->create(['email' => 'vendedor@example.com']);
    $seller->assignRole('seller');

    $emails = (new UsersExport())->query()->pluck('email');

    expect($emails)->toContain('solo.cliente@example.com')
        ->not->toContain('vendedor@example.com')
        ->and($emails)->toContain($client->email);
});
