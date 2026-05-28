<?php

use App\Services\NewClientService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

uses(TestCase::class);

it('registers and uploads media against the live cliente nuevo service', function () {
    if (!filter_var(env('CLIENTE_NUEVO_LIVE_TEST', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set CLIENTE_NUEVO_LIVE_TEST=true to run live ClienteNuevo integration test.');
    }

    $config = config('cliente_nuevo');
    $hasStaticToken = filled(data_get($config, 'token'));
    $hasCredentialAuth = filled(data_get($config, 'auth.url'))
        && filled(data_get($config, 'auth.username'))
        && filled(data_get($config, 'auth.password'));

    if (!$hasStaticToken && !$hasCredentialAuth) {
        $fallbackToken = trim((string) env('CLIENTE_NUEVO_LIVE_FALLBACK_TOKEN', ''));
        if ($fallbackToken === '') {
            $this->markTestSkipped(
                'Missing ClienteNuevo auth config. Set CLIENTE_NUEVO_TOKEN, CLIENTE_NUEVO_AUTH_* or CLIENTE_NUEVO_LIVE_FALLBACK_TOKEN.'
            );
        }

        config()->set('cliente_nuevo.token', $fallbackToken);
    }

    $service = app(NewClientService::class);
    $document = '9'.date('ymdHis').str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);

    $registration = $service->registerClient([
        'Documento' => $document,
        'TipoDocumento' => '3',
        'PrimerNombre' => 'Integracion',
        'SegundoNombre' => 'Live',
        'PrimerApellido' => 'Smoke',
        'SegundoApellido' => 'Test',
        'NombreNegocio' => 'Tuti Integracion '.$document,
        'IdClasificacionCliente' => '1',
        'Departamento' => 'ANTIOQUIA',
        'Ciudad' => 'ABEJORRAL',
        'Telefono' => '8871234',
        'Movil' => '3001112233',
        'Whatsapp' => '3001112233',
        'Correo' => "integracion+{$document}@example.test",
        'Direccion' => 'Calle 10 # 20-30',
        'Barrio' => 'Centro',
        'Zona' => '115',
        'RutaZonaVentas' => '1338',
        'DiaRecorrido' => 'LUNES',
        'Posicion' => '15',
        'Pep' => 'NO',
    ]);

    expect(data_get($registration, 'success'))
        ->toBeTrue((string) data_get($registration, 'message', 'Live registration failed without error message.'));

    $clientId = (int) data_get($registration, 'id', 0);
    expect($clientId)->toBeGreaterThan(0);

    $pdf = UploadedFile::fake()->createWithContent(
        'firma.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n"
    );

    $upload = $service->uploadMedia($clientId, $pdf, []);

    expect(data_get($upload, 'success'))
        ->toBeTrue((string) data_get($upload, 'message', 'Live media upload failed without error message.'));
})->group('live');
