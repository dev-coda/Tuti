<?php

use App\Exports\ClientsExportRows;
use App\Jobs\ExportClientsJob;
use App\Models\City;
use App\Models\ExportFile;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Storage::fake('local');
});

it('queues the clients export job on the exports queue', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    get(route('admin.export.users'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $exportFile = ExportFile::query()->where('type', 'clients')->first();
    expect($exportFile)->not->toBeNull()
        ->and($exportFile->status)->toBe(ExportFile::STATUS_PROCESSING)
        ->and($exportFile->filename)->toEndWith('.csv')
        ->and($exportFile->file_path)->toStartWith('exports/clients/');

    Queue::assertPushedOn('exports', ExportClientsJob::class, function (ExportClientsJob $job) use ($exportFile) {
        return $job->exportFileId === $exportFile->id;
    });
});

it('streams a csv with email and phone numbers for every client', function () {
    $state = State::query()->create(['name' => 'Cundinamarca']);
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

    $seller = User::factory()->create(['email' => 'vendedor@example.com']);
    $seller->assignRole('seller');

    $exportFile = ExportFile::create([
        'user_id' => $client->id,
        'type' => 'clients',
        'filename' => 'clientes_test.csv',
        'file_path' => 'exports/clients/clientes_test.csv',
        'status' => ExportFile::STATUS_PENDING,
        'params' => ['label' => 'test'],
    ]);

    (new ExportClientsJob($exportFile->id))->handle();

    $exportFile->refresh();
    expect($exportFile->status)->toBe(ExportFile::STATUS_COMPLETED)
        ->and($exportFile->total_records)->toBe(1);

    $csv = Storage::disk('local')->get($exportFile->file_path);
    expect($csv)->toContain('Email')
        ->toContain('Teléfono')
        ->toContain('Celular')
        ->toContain('ana.cliente@example.com')
        ->toContain('6015550101')
        ->toContain('3005550101')
        ->toContain('Ana Cliente')
        ->toContain('0001')
        ->not->toContain('vendedor@example.com');
});

it('maps email and phone numbers plus zone logistics into export rows', function () {
    $state = State::query()->create(['name' => 'Antioquia']);
    $city = City::query()->create([
        'name' => 'Medellín',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);

    $client = User::factory()->create([
        'name' => 'Ana Cliente',
        'email' => 'ana.cliente@example.com',
        'phone' => '6015550101',
        'mobile_phone' => '3005550101',
        'whatsapp' => '3005550101',
        'city_id' => $city->id,
        'zone' => '101',
        'status_id' => User::ACTIVE,
    ]);
    $client->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1 #2-3',
        'code' => 'C101',
    ]);
    $client->load(['city', 'zones']);

    $row = ClientsExportRows::map($client);
    $indexed = array_combine(ClientsExportRows::headings(), $row);

    expect($indexed['Email'])->toBe('ana.cliente@example.com')
        ->and($indexed['Teléfono'])->toBe('6015550101')
        ->and($indexed['Celular'])->toBe('3005550101')
        ->and($indexed['Ruta'])->toBe('0001')
        ->and($indexed['Ciudad'])->toBe('Medellín');
});
