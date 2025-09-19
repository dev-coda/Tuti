<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * Display content management page
     */
    public function index()
    {
        $contentSettings = Setting::whereIn('key', [
            'terms_conditions_content',
            'privacy_policy_content',
            'faq_content'
        ])->get()->keyBy('key');

        // Create missing content settings if they don't exist
        $defaultContent = [
            'terms_conditions_content' => [
                'name' => 'Términos y Condiciones',
                'value' => '<h2>Términos y Condiciones</h2><p>Contenido de términos y condiciones...</p>'
            ],
            'privacy_policy_content' => [
                'name' => 'Políticas de Privacidad',
                'value' => '<h2>Políticas de Privacidad</h2><p>Contenido de políticas de privacidad...</p>'
            ],
            'faq_content' => [
                'name' => 'Preguntas Frecuentes',
                'value' => '<h2>Preguntas Frecuentes</h2><p>Contenido de preguntas frecuentes...</p>'
            ]
        ];

        foreach ($defaultContent as $key => $data) {
            if (!$contentSettings->has($key)) {
                $setting = Setting::create([
                    'key' => $key,
                    'name' => $data['name'],
                    'value' => $data['value'],
                    'show' => true
                ]);
                $contentSettings->put($key, $setting);
            }
        }

        return view('admin.content.index', compact('contentSettings'));
    }

    /**
     * Show edit form for specific content
     */
    public function edit($key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        return view('admin.content.edit', compact('setting'));
    }

    /**
     * Update content
     */
    public function update(Request $request, $key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string'
        ]);

        $setting->update(['value' => $validated['content']]);

        return response()->json([
            'success' => true,
            'message' => 'Contenido actualizado correctamente'
        ]);
    }

    /**
     * Get content for API/AJAX requests
     */
    public function show($key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        return response()->json([
            'key' => $setting->key,
            'name' => $setting->name,
            'content' => $setting->value
        ]);
    }

    /**
     * Public pages for content
     */
    public function terms()
    {
        $setting = Setting::where('key', 'terms_conditions_content')->first();
        $content = $setting ? $setting->value : '<p>Contenido no disponible</p>';

        return view('content.terms', compact('content'));
    }

    public function privacy()
    {
        $setting = Setting::where('key', 'privacy_policy_content')->first();
        $content = $setting ? $setting->value : '<p>Contenido no disponible</p>';

        return view('content.privacy', compact('content'));
    }

    public function faq()
    {
        $setting = Setting::where('key', 'faq_content')->first();
        $content = $setting ? $setting->value : '<p>Contenido no disponible</p>';

        return view('content.faq', compact('content'));
    }
}
