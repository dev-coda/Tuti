<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class MailerSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mailerSettings = [
            [
                'name' => 'Mail Driver',
                'key' => 'mail_mailer',
                'value' => 'mailgun',
                'show' => true,
            ],
            [
                'name' => 'Mail From Address',
                'key' => 'mail_from_address',
                'value' => 'noreply@tuti.com',
                'show' => true,
            ],
            [
                'name' => 'Mail From Name',
                'key' => 'mail_from_name',
                'value' => 'Tuti',
                'show' => true,
            ],
            [
                'name' => 'Mailgun Domain',
                'key' => 'mailgun_domain',
                'value' => '',
                'show' => true,
            ],
            [
                'name' => 'Mailgun Secret',
                'key' => 'mailgun_secret',
                'value' => '',
                'show' => true,
            ],
            [
                'name' => 'Mailgun Endpoint',
                'key' => 'mailgun_endpoint',
                'value' => 'api.mailgun.net',
                'show' => true,
            ],
            [
                'name' => 'SMTP Host',
                'key' => 'smtp_host',
                'value' => 'smtp.mailgun.org',
                'show' => true,
            ],
            [
                'name' => 'SMTP Port',
                'key' => 'smtp_port',
                'value' => '587',
                'show' => true,
            ],
            [
                'name' => 'SMTP Username',
                'key' => 'smtp_username',
                'value' => '',
                'show' => true,
            ],
            [
                'name' => 'SMTP Password',
                'key' => 'smtp_password',
                'value' => '',
                'show' => true,
            ],
            [
                'name' => 'SMTP Encryption',
                'key' => 'smtp_encryption',
                'value' => 'tls',
                'show' => true,
            ],
        ];

        foreach ($mailerSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
