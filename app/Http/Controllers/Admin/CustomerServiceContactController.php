<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class CustomerServiceContactController extends Controller
{
    private const KEYS = [
        'customer_service_address' => 'Dirección de Servicio al Cliente',
        'customer_service_phone' => 'Teléfono de Servicio al Cliente',
        'customer_service_whatsapp' => 'WhatsApp de Servicio al Cliente',
        'customer_service_whatsapp_note' => 'Nota de WhatsApp de Servicio al Cliente',
    ];

    private const DEFAULTS = [
        'customer_service_address' => 'Cra. 67 #1 S-92, Guayabal, Medellín, Guayabal, Medellín, Antioquia',
        'customer_service_phone' => '44488090',
        'customer_service_whatsapp' => '',
        'customer_service_whatsapp_note' => 'Número de WhatsApp pendiente de confirmación.',
    ];

    public function edit()
    {
        $contact = [];

        foreach (self::KEYS as $key => $name) {
            $contact[$key] = Setting::getByKeyWithDefault($key, self::DEFAULTS[$key] ?? '');
        }

        return view('admin.customer-service.contact', compact('contact'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:50',
            'whatsapp' => 'nullable|string|max:20',
            'whatsapp_note' => 'nullable|string|max:255',
        ]);

        $values = [
            'customer_service_address' => $validated['address'],
            'customer_service_phone' => $validated['phone'],
            'customer_service_whatsapp' => preg_replace('/\D+/', '', $validated['whatsapp'] ?? ''),
            'customer_service_whatsapp_note' => $validated['whatsapp_note'] ?? '',
        ];

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'name' => self::KEYS[$key],
                    'value' => $value,
                    'show' => false,
                ]
            );
        }

        return back()->with('success', 'Información de contacto actualizada correctamente.');
    }
}
