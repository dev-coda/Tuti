<?php

use App\Models\City;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\State;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Captures the Mail::html message-builder closure so tests can assert
 * recipient/subject without a real transport.
 */
class InteresadoMessageSpy
{
    public array $to = [];
    public ?string $subject = null;
    public array $attachments = [];

    public function to($address)
    {
        $this->to[] = $address;

        return $this;
    }

    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    public function from($address, $name = null)
    {
        return $this;
    }

    public function attach($path)
    {
        $this->attachments[] = $path;

        return $this;
    }
}

function spyOnMailHtml(int $expectedTimes = 1): array
{
    $spies = [];

    Mail::shouldReceive('html')
        ->times($expectedTimes)
        ->andReturnUsing(function ($html, $callback) use (&$spies) {
            $spy = new InteresadoMessageSpy();
            $callback($spy);
            $spies[] = $spy;

            return null;
        });

    return ['spies' => &$spies];
}

function seedContactFormTemplate(bool $active = true): EmailTemplate
{
    // The ensure_contact_form_email_template_exists migration may have
    // created it already; normalize to the test fixture.
    return EmailTemplate::updateOrCreate(
        ['slug' => 'contact_form'],
        [
            'name' => 'Contact Form Admin Notification',
            'subject' => 'Nuevo contacto registrado - {contact_name}',
            'type' => EmailTemplate::TYPE_CONTACT_FORM,
            'body' => '<p>{contact_name} - {contact_email} - {nit}</p>',
            'variables' => ['contact_name', 'contact_email', 'nit'],
            'is_active' => $active,
        ]
    );
}

function makeInteresadoContact(array $overrides = []): Contact
{
    return Contact::create(array_merge([
        'person_type' => 'natural',
        'name' => 'Juan Interesado',
        'email' => 'juan@example.com',
        'phone' => '3001234567',
        'department' => 'Antioquia',
        'nit' => '900123456',
        'address' => 'Calle 1 # 2-3',
        'terms_accepted' => true,
        'documents' => [],
        'status' => 'interesado',
    ], $overrides));
}

beforeEach(function () {
    config(['queue.default' => 'sync']);
});

it('sends the interesado notification to the configured destination email', function () {
    seedContactFormTemplate();
    Setting::updateOrCreate(
        ['key' => 'interesados_admin_email'],
        ['name' => 'Interesados Admin Email', 'value' => 'comercial@tronex.com', 'show' => true]
    );

    $capture = spyOnMailHtml(1);

    makeInteresadoContact();

    $spy = $capture['spies'][0];
    expect($spy->to)->toBe(['comercial@tronex.com']);
    expect($spy->subject)->toBe('Nuevo contacto registrado - Juan Interesado');
});

it('supports multiple comma-separated destination emails', function () {
    seedContactFormTemplate();
    Setting::updateOrCreate(
        ['key' => 'interesados_admin_email'],
        ['name' => 'Interesados Admin Email', 'value' => 'a@tronex.com, b@tronex.com', 'show' => true]
    );

    $capture = spyOnMailHtml(2);

    makeInteresadoContact();

    $recipients = collect($capture['spies'])->flatMap(fn ($spy) => $spy->to)->all();
    expect($recipients)->toBe(['a@tronex.com', 'b@tronex.com']);
});

it('falls back to the mail from-address when no destination is configured', function () {
    seedContactFormTemplate();
    Setting::updateOrCreate(
        ['key' => 'mail_from_address'],
        ['name' => 'Mail From Address', 'value' => 'noreply@tronex.com', 'show' => true]
    );

    $capture = spyOnMailHtml(1);

    makeInteresadoContact();

    expect($capture['spies'][0]->to)->toBe(['noreply@tronex.com']);
});

it('still notifies with a built-in fallback when the template is missing', function () {
    EmailTemplate::where('slug', 'contact_form')->delete();
    Setting::updateOrCreate(
        ['key' => 'interesados_admin_email'],
        ['name' => 'Interesados Admin Email', 'value' => 'comercial@tronex.com', 'show' => true]
    );

    $capture = spyOnMailHtml(1);

    makeInteresadoContact();

    $spy = $capture['spies'][0];
    expect($spy->to)->toBe(['comercial@tronex.com']);
    expect($spy->subject)->toBe('Nuevo interesado registrado - Juan Interesado');
});

it('does not send when the template was deliberately deactivated', function () {
    seedContactFormTemplate(active: false);
    Setting::updateOrCreate(
        ['key' => 'interesados_admin_email'],
        ['name' => 'Interesados Admin Email', 'value' => 'comercial@tronex.com', 'show' => true]
    );

    Mail::shouldReceive('html')->never();

    makeInteresadoContact();
});

it('sends the notification when the public interesado form is submitted', function () {
    seedContactFormTemplate();
    Setting::updateOrCreate(
        ['key' => 'interesados_admin_email'],
        ['name' => 'Interesados Admin Email', 'value' => 'comercial@tronex.com', 'show' => true]
    );

    $state = State::create(['name' => 'Antioquia']);
    $city = City::create(['name' => 'Medellín', 'state_id' => $state->id, 'active' => true, 'is_preferred' => true]);

    $capture = spyOnMailHtml(1);

    $response = $this->post(route('form_post'), [
        'reg_person_type' => 'natural',
        'reg_nit' => '900123456',
        'reg_name' => 'Juan Interesado',
        'reg_email' => 'juan@example.com',
        'reg_phone' => '3001234567',
        'reg_department' => 'Antioquia',
        'reg_city_id' => $city->id,
        'reg_address' => 'Calle 1 # 2-3',
        'terms_accepted' => '1',
    ]);

    $response->assertSessionHas('success');

    expect(Contact::where('email', 'juan@example.com')->where('status', 'interesado')->exists())->toBeTrue();
    expect($capture['spies'][0]->to)->toBe(['comercial@tronex.com']);
});

it('dispatches the email job to the emails queue when a queue worker exists', function () {
    config(['queue.default' => 'redis']);
    \Illuminate\Support\Facades\Bus::fake();

    seedContactFormTemplate();

    makeInteresadoContact();

    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendContactFormEmail::class, function ($job) {
        return $job->queue === 'emails' && $job->connection === 'redis';
    });
});
