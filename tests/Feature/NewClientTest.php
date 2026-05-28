<?php

use App\Models\User;
use App\Models\ZoneRoute;
use App\Services\NewClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'seller', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->seller = User::factory()->create();
    $this->seller->assignRole('seller');
    $this->seller->update(['zone' => '001']);

    $this->customer = User::factory()->create();

    \App\Models\State::create(['name' => 'ANTIOQUIA']);
    ZoneRoute::create(['zone' => '001', 'route' => '1234']);
});

it('allows guests to access new client form', function () {
    $this->get(route('new-client.create'))
        ->assertOk()
        ->assertSeeText('Registrar Cliente Nuevo');
});

it('allows admins to access the new client form', function () {
    actingAs($this->admin)
        ->get(route('new-client.create'))
        ->assertOk()
        ->assertSeeText('Registrar Cliente Nuevo');
});

it('allows sellers to access the new client form', function () {
    actingAs($this->seller)
        ->get(route('new-client.create'))
        ->assertOk()
        ->assertSeeText('Registrar Cliente Nuevo');
});

it('uses admin layout for admins', function () {
    actingAs($this->admin)
        ->get(route('new-client.create'))
        ->assertOk()
        ->assertSee('sidebar');
});

it('uses page layout for sellers', function () {
    actingAs($this->seller)
        ->get(route('new-client.create'))
        ->assertOk()
        ->assertDontSee('sidebar-toggle-item');
});

it('validates required fields on store', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), [])
        ->assertSessionHasErrors([
            'Documento', 'TipoDocumento', 'NombreNegocio',
            'RazonSocial',
            'IdClasificacionCliente', 'Departamento', 'Ciudad',
            'Direccion', 'Barrio', 'Zona', 'RutaZonaVentas',
            'DiaRecorrido', 'Posicion', 'Pep', 'signature', 'terms_accepted',
        ]);
});

it('validates document format', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'Documento' => 'ABC!@#',
            'TipoDocumento' => 1,
            'RazonSocial' => 'Razon Test',
            'NombreNegocio' => 'Test',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle 1',
            'Barrio' => 'Centro',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'LUNES',
            'Posicion' => 1,
            'Pep' => 'NO',
            'Movil' => '3101234567',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
            'terms_accepted' => '1',
        ])
        ->assertSessionHasErrors('Documento');
});

it('requires PrimerNombre and PrimerApellido for CC and CE documents', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'Documento' => '123456789',
            'TipoDocumento' => 1,
            'RazonSocial' => 'Razon Test',
            'NombreNegocio' => 'Test',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle 1',
            'Barrio' => 'Centro',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'LUNES',
            'Posicion' => 1,
            'Pep' => 'NO',
            'Movil' => '3101234567',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
            'terms_accepted' => '1',
        ])
        ->assertSessionHasErrors(['PrimerNombre', 'PrimerApellido']);
});

it('requires at least one contact number', function () {
    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldNotReceive('registerClient');
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'Documento' => '123456789',
            'TipoDocumento' => 3,
            'RazonSocial' => 'Razon Test',
            'NombreNegocio' => 'Test',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle 1',
            'Barrio' => 'Centro',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'LUNES',
            'Posicion' => 1,
            'Pep' => 'NO',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
            'terms_accepted' => '1',
        ])
        ->assertSessionHasErrors('Telefono');
});

it('rejects more than 6 documents for juridica', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'Documento' => '123456789',
            'TipoDocumento' => 3,
            'RazonSocial' => 'Razon Test',
            'NombreNegocio' => 'Test',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle 1',
            'Barrio' => 'Centro',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'LUNES',
            'Posicion' => 1,
            'Pep' => 'NO',
            'Movil' => '3101234567',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
            'terms_accepted' => '1',
            'documents' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
                UploadedFile::fake()->image('d.jpg'),
                UploadedFile::fake()->image('e.jpg'),
                UploadedFile::fake()->image('f.jpg'),
                UploadedFile::fake()->image('g.jpg'),
            ],
        ])
        ->assertSessionHasErrors('documents');
});

it('rejects unsupported document files', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'Documento' => '123456789',
            'TipoDocumento' => 3,
            'RazonSocial' => 'Razon Test',
            'NombreNegocio' => 'Test',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle 1',
            'Barrio' => 'Centro',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'LUNES',
            'Posicion' => 1,
            'Pep' => 'NO',
            'Movil' => '3101234567',
            'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg',
            'terms_accepted' => '1',
            'documents' => [
                UploadedFile::fake()->create('doc.txt', 100, 'text/plain'),
            ],
        ])
        ->assertSessionHasErrors('documents.0');
});

it('builds correct XML in service', function () {
    $service = new NewClientService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildClientXml');
    $method->setAccessible(true);

    $xml = $method->invoke($service, [
        'Documento' => '123456789',
        'TipoDocumento' => '3',
        'PrimerNombre' => 'Juan',
        'SegundoNombre' => '',
        'PrimerApellido' => 'Perez',
        'SegundoApellido' => '',
        'NombreNegocio' => 'Tienda El Sol',
        'IdClasificacionCliente' => '3',
        'Departamento' => 'ANTIOQUIA',
        'Ciudad' => 'MEDELLIN',
        'Telefono' => '8871234',
        'Movil' => '3101234567',
        'Whatsapp' => '',
        'Correo' => 'test@example.com',
        'Direccion' => 'Calle 10 # 5-20',
        'Barrio' => 'Centro',
        'Zona' => '001',
        'RutaZonaVentas' => 'RUTA-NORTE',
        'DiaRecorrido' => 'LUNES',
        'Posicion' => '5',
        'Pep' => 'SI',
    ]);

    expect($xml)->toContain('<ClienteNuevo>')
        ->toContain('<Documento>123456789</Documento>')
        ->toContain('<TipoDocumento>3</TipoDocumento>')
        ->toContain('<PrimerNombre>Juan</PrimerNombre>')
        ->toContain('<NombreNegocio>Tienda El Sol</NombreNegocio>')
        ->toContain('<Direccion>Calle 10 # 5-20</Direccion>')
        ->toContain('<Pep>SI</Pep>')
        ->toContain('</ClienteNuevo>');
});

it('escapes XML special characters in service', function () {
    $service = new NewClientService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildClientXml');
    $method->setAccessible(true);

    $xml = $method->invoke($service, [
        'NombreNegocio' => 'Tienda <El & Sol>',
        'Direccion' => 'Calle "5" & 10',
    ]);

    expect($xml)->toContain('Tienda &lt;El &amp; Sol&gt;')
        ->toContain('Calle "5" &amp; 10');
});
