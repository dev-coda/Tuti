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
            'privacy_accepted', 'documents',
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
            'privacy_accepted' => '1',
            'documents' => [UploadedFile::fake()->image('rut.jpg')],
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
            'privacy_accepted' => '1',
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

/**
 * Valid base64 PNG data URL large enough to pass signature processing.
 */
function validSignatureDataUrl(): string
{
    $img = imagecreatetruecolor(120, 60);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);
    $black = imagecolorallocate($img, 0, 0, 0);
    imageline($img, 10, 30, 110, 30, $black);
    ob_start();
    imagepng($img);
    $png = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($png);
}

it('returns prefill data for an existing client in sucursal lookup', function () {
    User::factory()->create([
        'name' => 'Juan Carlos Perez Gomez',
        'document' => '900123456',
        'business_name' => 'Tienda El Sol',
        'phone' => '8871234',
        'mobile_phone' => '3101234567',
        'whatsapp' => '3107654321',
        'email' => 'cliente@example.com',
    ]);

    actingAs($this->seller)
        ->getJson(route('new-client.existing-client', ['document' => '900123456']))
        ->assertOk()
        ->assertJson([
            'found' => true,
            'client' => [
                'Documento' => '900123456',
                'RazonSocial' => 'Juan Carlos Perez Gomez',
                'NombreNegocio' => 'Tienda El Sol',
                'PrimerNombre' => 'Juan',
                'SegundoNombre' => 'Carlos',
                'PrimerApellido' => 'Perez',
                'SegundoApellido' => 'Gomez',
                'Telefono' => '8871234',
                'Movil' => '3101234567',
                'Whatsapp' => '3107654321',
                'Correo' => 'cliente@example.com',
            ],
        ]);
});

it('returns not found for unknown document in sucursal lookup', function () {
    actingAs($this->seller)
        ->getJson(route('new-client.existing-client', ['document' => '999999999']))
        ->assertNotFound()
        ->assertJson(['found' => false]);
});

it('blocks guests and regular customers from sucursal lookup', function () {
    $this->getJson(route('new-client.existing-client', ['document' => '900123456']))
        ->assertUnauthorized();

    // Role middleware redirects authenticated users without the required role.
    $response = actingAs($this->customer)
        ->getJson(route('new-client.existing-client', ['document' => '900123456']));

    expect($response->status())->toBeIn([302, 403]);
});

it('rejects sucursal registration when document does not belong to an existing client', function () {
    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldNotReceive('registerClient');
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'is_sucursal' => '1',
            'Documento' => '999999999',
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
            'signature' => validSignatureDataUrl(),
            'terms_accepted' => '1',
            'privacy_accepted' => '1',
            'documents' => [UploadedFile::fake()->image('rut.jpg')],
        ])
        ->assertSessionHasErrors('Documento');
});

it('registers a sucursal for an existing client without demoting their status', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $client = User::factory()->create([
        'name' => 'Tienda El Sol SAS',
        'document' => '900123456',
        'business_name' => 'Tienda El Sol',
        'status_id' => User::ACTIVE,
        'client_status' => User::CLIENT_STATUS_CLIENTE,
    ]);
    $client->zones()->create([
        'zone' => '001',
        'route' => '1111',
        'day' => '1-Lunes',
        'address' => 'Sucursal principal',
        'code' => 'SUC-1',
    ]);

    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldReceive('registerClient')->once()->andReturn([
            'success' => true,
            'id' => 55,
            'codigo_cliente' => 'C-0055',
            'message' => 'ok',
        ]);
        $mock->shouldReceive('uploadMedia')->once()->andReturn([
            'success' => true,
            'message' => 'ok',
        ]);
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), [
            'is_sucursal' => '1',
            'Documento' => '900123456',
            'TipoDocumento' => 3,
            'RazonSocial' => 'Tienda El Sol SAS',
            'NombreNegocio' => 'Tienda El Sol',
            'IdClasificacionCliente' => 1,
            'Departamento' => 'ANTIOQUIA',
            'Ciudad' => 'MEDELLIN',
            'Direccion' => 'Calle Nueva Sucursal 2',
            'Barrio' => 'Laureles',
            'Zona' => '001',
            'RutaZonaVentas' => '1234',
            'DiaRecorrido' => 'MARTES',
            'Posicion' => 3,
            'Pep' => 'NO',
            'Movil' => '3101234567',
            'signature' => validSignatureDataUrl(),
            'terms_accepted' => '1',
            'privacy_accepted' => '1',
            'documents' => [UploadedFile::fake()->image('rut.jpg')],
        ])
        ->assertRedirect(route('new-client.create'))
        ->assertSessionHas('success');

    expect(session('success'))->toContain('Sucursal registrada');

    $client->refresh();
    expect($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and((int) $client->status_id)->toBe(User::ACTIVE)
        ->and($client->zones()->count())->toBe(2);

    $newZone = $client->zones()->where('route', '1234')->first();
    expect($newZone)->not->toBeNull()
        ->and($newZone->zone)->toBe('001')
        ->and($newZone->day)->toBe('MARTES');
});

function validNewClientPayload(array $overrides = []): array
{
    return array_merge([
        'Documento' => '900123456',
        'TipoDocumento' => 3,
        'RazonSocial' => 'Tienda El Sol SAS',
        'NombreNegocio' => 'Tienda El Sol',
        'IdClasificacionCliente' => 1,
        'Departamento' => 'ANTIOQUIA',
        'Ciudad' => 'MEDELLIN',
        'Direccion' => 'Calle 10 # 5-20',
        'Barrio' => 'Centro',
        'Zona' => '001',
        'RutaZonaVentas' => '1234',
        'DiaRecorrido' => 'LUNES',
        'Posicion' => 1,
        'Pep' => 'NO',
        'Movil' => '3101234567',
        'signature' => validSignatureDataUrl(),
        'terms_accepted' => '1',
        'privacy_accepted' => '1',
        'documents' => [UploadedFile::fake()->image('rut.jpg')],
    ], $overrides);
}

it('rejects submission without attached documents', function () {
    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldNotReceive('registerClient');
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), validNewClientPayload(['documents' => []]))
        ->assertSessionHasErrors('documents');
});

it('requires accepting the privacy policy (habeas data)', function () {
    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldNotReceive('registerClient');
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), validNewClientPayload(['privacy_accepted' => null]))
        ->assertSessionHasErrors('privacy_accepted');
});

it('accepts PPT as document type and requires contact names for it', function () {
    actingAs($this->seller)
        ->post(route('new-client.store'), validNewClientPayload([
            'TipoDocumento' => 4,
            'PrimerNombre' => null,
            'PrimerApellido' => null,
        ]))
        ->assertSessionHasErrors(['PrimerNombre', 'PrimerApellido'])
        ->assertSessionDoesntHaveErrors('TipoDocumento');
});

it('strips the NIT verification digit and uppercases registered data', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $captured = null;
    $this->mock(NewClientService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('registerClient')->once()->andReturnUsing(function (array $data) use (&$captured) {
            $captured = $data;

            return ['success' => true, 'id' => 10, 'codigo_cliente' => 'C-0010', 'message' => 'ok'];
        });
        $mock->shouldReceive('uploadMedia')->once()->andReturn(['success' => true, 'message' => 'ok']);
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), validNewClientPayload([
            'Documento' => '900123456-7',
            'RazonSocial' => 'tienda el sol sas',
            'NombreNegocio' => 'tienda el sol',
            'Direccion' => 'calle 10 # 5-20',
        ]))
        ->assertRedirect(route('new-client.create'))
        ->assertSessionHas('success');

    expect($captured)->not->toBeNull()
        ->and($captured['Documento'])->toBe('900123456')
        ->and($captured['RazonSocial'])->toBe('TIENDA EL SOL SAS')
        ->and($captured['NombreNegocio'])->toBe('TIENDA EL SOL')
        ->and($captured['Direccion'])->toBe('CALLE 10 # 5-20');
});

it('sends the razon social instead of the contact name for NIT clients', function () {
    $service = new NewClientService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildClientXml');
    $method->setAccessible(true);

    $xml = $method->invoke($service, [
        'Documento' => '900123456',
        'TipoDocumento' => 3,
        'RazonSocial' => 'TIENDA EL SOL SAS',
        'PrimerNombre' => 'JUAN',
        'SegundoNombre' => 'CARLOS',
        'PrimerApellido' => 'PEREZ',
        'SegundoApellido' => 'GOMEZ',
        'NombreNegocio' => 'TIENDA EL SOL',
    ]);

    expect($xml)->toContain('<PrimerNombre>TIENDA EL SOL SAS</PrimerNombre>')
        ->toContain('<SegundoNombre></SegundoNombre>')
        ->toContain('<PrimerApellido></PrimerApellido>')
        ->toContain('<SegundoApellido></SegundoApellido>')
        ->not->toContain('JUAN');
});

it('keeps the contact name for natural person clients', function () {
    $service = new NewClientService();

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildClientXml');
    $method->setAccessible(true);

    $xml = $method->invoke($service, [
        'Documento' => '123456789',
        'TipoDocumento' => 1,
        'RazonSocial' => 'JUAN PEREZ',
        'PrimerNombre' => 'JUAN',
        'PrimerApellido' => 'PEREZ',
    ]);

    expect($xml)->toContain('<PrimerNombre>JUAN</PrimerNombre>')
        ->toContain('<PrimerApellido>PEREZ</PrimerApellido>');
});

it('embeds the habeas data authorization in the signature pdf', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->mock(NewClientService::class, function ($mock) {
        $mock->shouldReceive('registerClient')->once()->andReturn([
            'success' => true, 'id' => 11, 'codigo_cliente' => 'C-0011', 'message' => 'ok',
        ]);
        $mock->shouldReceive('uploadMedia')->once()->andReturnUsing(function ($clientId, $pdf) {
            $content = $pdf->getContent();
            expect($content)->toContain('%PDF-1.4')
                ->toContain('HABEAS DATA')
                ->toContain('Firma del representante legal o suplente');

            return ['success' => true, 'message' => 'ok'];
        });
    });

    actingAs($this->seller)
        ->post(route('new-client.store'), validNewClientPayload())
        ->assertRedirect(route('new-client.create'))
        ->assertSessionHas('success');
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
