<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Setting;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * Show Terms and Conditions page
     */
    public function terms()
    {
        $content = Setting::getByKey('terms_conditions_content') ?? 'Contenido de términos y condiciones no disponible.';

        return view('content.terms', [
            'title' => 'Términos y Condiciones',
            'content' => $content
        ]);
    }

    /**
     * Show Privacy Policy page
     */
    public function privacy()
    {
        $content = Setting::getByKey('privacy_policy_content') ?? 'Contenido de políticas de privacidad no disponible.';

        return view('content.privacy', [
            'title' => 'Políticas de Privacidad',
            'content' => $content
        ]);
    }

    /**
     * Show FAQ page
     */
    public function faq()
    {
        $content = Setting::getByKey('faq_content') ?? 'Contenido de preguntas frecuentes no disponible.';

        return view('content.faq', [
            'title' => 'Preguntas Frecuentes',
            'content' => $content
        ]);
    }

    /**
     * Show dynamic content page by slug
     */
    public function showPage($slug)
    {
        $page = ContentPage::where('slug', $slug)
            ->where('enabled', true)
            ->firstOrFail();

        return view('content.page', [
            'title' => $page->title,
            'content' => $page->content
        ]);
    }
}
