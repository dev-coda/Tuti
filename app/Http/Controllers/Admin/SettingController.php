<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Jobs\SyncProductInventory;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        // Ensure inventory management toggle exists
        Setting::firstOrCreate(
            ['key' => 'inventory_enabled'],
            [
                'name' => 'Inventario habilitado',
                'value' => '1',
                'show' => true,
            ]
        );

        $settings = Setting::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->whereShow(true)
            ->orderBy('id')
            ->paginate();

        $context = compact('settings');
        return view('settings.index', $context);
    }

    /**
     * Display the specified resource.
     */
    public function show(Setting $setting)
    {
        $context = compact('setting');
        return view('settings.show', $context);
    }

    //edit
    public function edit(Setting $setting)
    {
        $context = compact('setting');
        return view('settings.edit', $context);
    }

    //update
    public function update(Request $request, Setting $setting)
    {
        $validate = $request->validate([
            'value' => 'required',
        ]);

        $setting->update($validate);
        return to_route('settings.index')->with('success', 'Texto actualizado');
    }

    public function syncInventory()
    {
        // Dispatch synchronously to ensure it runs immediately
        try {
            SyncProductInventory::dispatchSync();
            return back()->with('success', 'Sincronización de inventario completada exitosamente');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Inventory sync failed: ' . $e->getMessage());
            return back()->with('error', 'Error al sincronizar inventario: ' . $e->getMessage());
        }
    }

    /**
     * Show mailer settings
     */
    public function mailer()
    {
        $mailerSettings = \App\Models\Setting::whereIn('key', [
            'mail_mailer',
            'mail_from_address',
            'mail_from_name',
            'mailgun_domain',
            'mailgun_secret',
            'mailgun_endpoint',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
        ])->get()->keyBy('key');

        return view('settings.mailer', compact('mailerSettings'));
    }

    /**
     * Update mailer settings
     */
    public function updateMailer(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => 'required|string|in:smtp,mailgun,sendmail,log',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string|max:255',
            'mailgun_domain' => 'nullable|string|max:255',
            'mailgun_secret' => 'nullable|string|max:255',
            'mailgun_endpoint' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|string|in:tls,ssl',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key],
                    ['name' => $this->getSettingName($key), 'value' => $value, 'show' => true]
                );
            }
        }

        // Update mail configuration
        $mailingService = app(\App\Services\MailingService::class);
        $mailingService->updateMailConfiguration();

        return back()->with('success', 'Configuración de correo actualizada exitosamente');
    }

    /**
     * Get human-readable setting name
     */
    private function getSettingName($key)
    {
        $names = [
            'mail_mailer' => 'Mail Driver',
            'mail_from_address' => 'Mail From Address',
            'mail_from_name' => 'Mail From Name',
            'mailgun_domain' => 'Mailgun Domain',
            'mailgun_secret' => 'Mailgun Secret',
            'mailgun_endpoint' => 'Mailgun Endpoint',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
            'smtp_encryption' => 'SMTP Encryption',
        ];

        return $names[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
