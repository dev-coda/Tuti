<?php

use App\Models\CustomerServiceRequest;
use App\Services\MailingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function validPqrsPayload(): array
{
    return [
        'full_name' => 'Cliente Prueba',
        'email' => 'cliente@example.com',
        'city' => 'Medellín',
        'phone' => '3101234567',
        'request_type' => 'pregunta',
        'subject' => 'Consulta de prueba',
        'message' => 'Mensaje de prueba.',
    ];
}

it('stores the request and shows success when notification email is sent', function () {
    $this->mock(MailingService::class, function ($mock) {
        $mock->shouldReceive('sendCustomerServiceRequestNotification')->once()->andReturn(true);
    });

    post(route('customer-service.store'), validPqrsPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerServiceRequest::count())->toBe(1);
});

it('stores the request but warns the user when notification email fails', function () {
    $this->mock(MailingService::class, function ($mock) {
        $mock->shouldReceive('sendCustomerServiceRequestNotification')->once()->andReturn(false);
    });

    post(route('customer-service.store'), validPqrsPayload())
        ->assertRedirect()
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    expect(CustomerServiceRequest::count())->toBe(1);
});

it('throttles repeated submissions from the same client', function () {
    $this->mock(MailingService::class, function ($mock) {
        $mock->shouldReceive('sendCustomerServiceRequestNotification')->andReturn(true);
    });

    foreach (range(1, 5) as $i) {
        post(route('customer-service.store'), validPqrsPayload())->assertRedirect();
    }

    post(route('customer-service.store'), validPqrsPayload())
        ->assertStatus(429);
});
