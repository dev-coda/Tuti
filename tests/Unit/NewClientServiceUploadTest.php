<?php

use App\Services\NewClientService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('cliente_nuevo', [
        'base_url' => 'http://example.test/clienteConectat',
        'token' => 'static-token',
        'auth' => [
            'url' => null,
            'username' => null,
            'password' => null,
            'username_field' => 'username',
            'password_field' => 'password',
            'token_field' => 'token',
            'ttl_seconds' => 3300,
        ],
    ]);
});

it('sends the signature pdf and at most three images to production cargaArchivos', function () {
    Http::fake([
        'http://example.test/*' => Http::response([
            'Success' => 'OK',
            'bdPdf' => true,
            'imagenes' => ['guardado' => ['cedula.jpg', 'local.jpg', 'rutero.png']],
        ], 200),
    ]);

    $pdf = UploadedFile::fake()->createWithContent('firma.pdf', "%PDF-1.4\n%%EOF\n");
    $attachments = [
        UploadedFile::fake()->image('Cédula.jpg'),
        UploadedFile::fake()->image('local.jpg'),
        UploadedFile::fake()->image('rutero.png'),
        UploadedFile::fake()->image('extra.jpg'),
        UploadedFile::fake()->create('camara.pdf', 20, 'application/pdf'),
    ];

    $result = (new NewClientService())->uploadMedia(42, $pdf, $attachments);

    expect($result['success'])->toBeTrue()
        ->and($result['skipped'])->toContain('extra.jpg', 'camara.pdf')
        ->and($result['warning'])->toContain('extra.jpg')
        ->and($result['warning'])->toContain('camara.pdf');

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        expect($request->url())->toBe('http://example.test/clienteConectat/AuthMediaClienteNuevo/cargaArchivos')
            ->and($request->hasHeader('Tokenconectat', 'static-token'))->toBeTrue()
            ->and($request->hasHeader('Expect'))->toBeFalse()
            ->and(substr_count($body, 'name="imagenes[]"'))->toBe(3)
            ->and(substr_count($body, 'name="pdf"'))->toBe(1)
            ->and($body)->toContain('name="id"')
            ->and($body)->toContain('filename="Cedula.jpg"')
            ->and($body)->toContain('filename="local.jpg"')
            ->and($body)->toContain('filename="rutero.png"')
            ->and($body)->toContain('Content-Type: image/jpeg')
            ->and($body)->toContain('Content-Type: image/png')
            ->and($body)->toContain('Content-Type: application/pdf')
            ->and($body)->not->toContain('filename="extra.jpg"')
            ->and($body)->not->toContain('filename="camara.pdf"')
            ->and($body)->not->toContain('Cédula');

        return true;
    });
});

it('omits imagenes when the registration has no photos', function () {
    Http::fake([
        'http://example.test/*' => Http::response([
            'Success' => 'OK',
            'bdPdf' => true,
        ], 200),
    ]);

    $pdf = UploadedFile::fake()->createWithContent('firma.pdf', "%PDF-1.4\n%%EOF\n");
    $result = (new NewClientService())->uploadMedia(7, $pdf, [
        UploadedFile::fake()->create('rut.pdf', 10, 'application/pdf'),
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['skipped'])->toBe(['rut.pdf']);

    Http::assertSent(function ($request) {
        $body = (string) $request->body();

        expect($body)->not->toContain('name="imagenes')
            ->and($body)->toContain('filename="firma.pdf"');

        return true;
    });
});

it('reports a production failure when the signature pdf was not stored', function () {
    Http::fake([
        'http://example.test/*' => Http::response([
            'Success' => 'OK',
            'bdPdf' => false,
        ], 200),
    ]);

    $pdf = UploadedFile::fake()->createWithContent('firma.pdf', "%PDF-1.4\n%%EOF\n");
    $result = (new NewClientService())->uploadMedia(7, $pdf, []);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('no guardó el PDF de la firma');
});

it('surfaces a 406 from cargaArchivos instead of treating it as success', function () {
    Http::fake([
        'http://example.test/*' => Http::response([
            'Mensaje' => 'Ha exedido la cantidad de imágenes, por favor agregue máximo 3 imágenes.',
        ], 406),
    ]);

    $pdf = UploadedFile::fake()->createWithContent('firma.pdf', "%PDF-1.4\n%%EOF\n");
    $result = (new NewClientService())->uploadMedia(7, $pdf, []);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('406')
        ->and($result['message'])->toContain('máximo 3 imágenes');
});
