<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'client_registration_invite'],
            [
                'name' => 'Client Registration Password Invite',
                'subject' => 'Bienvenido a TUTI - Genera tu contraseña y realiza tu primera compra',
                // Reuses user_registration enum value (DB check constraint).
                'type' => EmailTemplate::TYPE_USER_REGISTRATION,
                'is_active' => true,
                'variables' => ['customer_name', 'customer_email', 'password_set_url', 'login_url'],
                'body' => '<h2>Hemos recibido tu información de registro en TUTI</h2>
        <p>Hola {customer_name},</p>
        <p>Hemos recibido tu información de registro en TUTI. Realiza tu primera compra generando tu contraseña <a href="{password_set_url}" style="color:#EE4E34;font-weight:700;text-decoration:underline;">AQUÍ</a>.</p>
        <p>Validamos la información en máximo 24 horas. Recuerda que puedes hacer tu pedido desde este instante.</p>
        <p style="text-align:center;margin:28px 0;">
            <a href="{password_set_url}" style="display:inline-block;background-color:#EE4E34;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:700;">Generar mi contraseña</a>
        </p>
        <p>Una vez realices tu pedido, validaremos la información enviada y activaremos tu pedido. Cuando los documentos sean aprobados, tu pedido cambiará de estado.</p>
        <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
        <p style="word-break:break-all;"><a href="{password_set_url}">{password_set_url}</a></p>
        <p>También puedes iniciar sesión más adelante en <a href="{login_url}">{login_url}</a>.</p>',
            ]
        );
    }

    public function down(): void
    {
        EmailTemplate::where('slug', 'client_registration_invite')->delete();
    }
};
