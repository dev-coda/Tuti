<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Interesado (contact form) notifications silently stop when the
 * contact_form email template is missing (environments where the
 * EmailTemplatesSeeder was never run). Create it if absent without
 * touching existing customized templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::firstOrCreate(
            ['slug' => 'contact_form'],
            [
                'name' => 'Contact Form Admin Notification',
                'subject' => 'Nuevo contacto registrado - {contact_name}',
                'type' => EmailTemplate::TYPE_CONTACT_FORM,
                'is_active' => true,
                'variables' => ['contact_name', 'contact_email', 'contact_phone', 'business_name', 'city', 'nit', 'message', 'contact_date'],
                'body' => '<h2>Nuevo contacto registrado</h2>
        <p>Se ha registrado un nuevo contacto en el sitio web.</p>
        <table>
            <tbody>
            <tr>
                <td><strong>Nombre</strong></td>
                <td>{contact_name}</td>
            </tr>
            <tr>
                <td><strong>Email</strong></td>
                <td><a href="mailto:{contact_email}">{contact_email}</a></td>
            </tr>
            <tr>
                <td><strong>Teléfono</strong></td>
                <td>{contact_phone}</td>
            </tr>
            <tr>
                <td><strong>Empresa</strong></td>
                <td>{business_name}</td>
            </tr>
            <tr>
                <td><strong>Ciudad</strong></td>
                <td>{city}</td>
            </tr>
            <tr>
                <td><strong>NIT</strong></td>
                <td>{nit}</td>
            </tr>
            <tr>
                <td><strong>Fecha de registro</strong></td>
                <td>{contact_date}</td>
            </tr>
            </tbody>
        </table>
        <p style="color:#6b7280;font-size:13px;">Este es un mensaje automático del sistema de Tuti.</p>',
            ]
        );
    }

    public function down(): void
    {
        // Intentionally left empty: removing the template would reintroduce
        // the silent notification loss this migration fixes.
    }
};
