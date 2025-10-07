<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = EmailTemplate::orderBy('type')->orderBy('name')->paginate(20);

        return view('admin.email-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = EmailTemplate::getTypes();
        return view('admin.email-templates.create', compact('types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:email_templates',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'header_image' => 'nullable|image|max:2048',
            'footer_image' => 'nullable|image|max:2048',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
            'type' => 'required|in:' . implode(',', array_keys(EmailTemplate::getTypes())),
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Handle header image upload
        if ($request->hasFile('header_image')) {
            $validated['header_image'] = $request->file('header_image')->store('email-templates', 'public');
        }

        // Handle footer image upload
        if ($request->hasFile('footer_image')) {
            $validated['footer_image'] = $request->file('footer_image')->store('email-templates', 'public');
        }

        EmailTemplate::create($validated);

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Plantilla de correo creada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EmailTemplate $template)
    {
        return view('admin.email-templates.show', compact('template'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmailTemplate $template)
    {
        $types = EmailTemplate::getTypes();
        return view('admin.email-templates.edit', compact('template', 'types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmailTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:email_templates,slug,' . $template->id,
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'header_image' => 'nullable|image|max:2048',
            'footer_image' => 'nullable|image|max:2048',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
            'type' => 'required|in:' . implode(',', array_keys(EmailTemplate::getTypes())),
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Handle header image upload
        if ($request->hasFile('header_image')) {
            // Delete old image if exists
            if ($template->header_image && \Storage::disk('public')->exists($template->header_image)) {
                \Storage::disk('public')->delete($template->header_image);
            }
            $validated['header_image'] = $request->file('header_image')->store('email-templates', 'public');
        }

        // Handle footer image upload
        if ($request->hasFile('footer_image')) {
            // Delete old image if exists
            if ($template->footer_image && \Storage::disk('public')->exists($template->footer_image)) {
                \Storage::disk('public')->delete($template->footer_image);
            }
            $validated['footer_image'] = $request->file('footer_image')->store('email-templates', 'public');
        }

        $template->update($validated);

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Plantilla de correo actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmailTemplate $template)
    {
        // Prevent deletion of default templates
        if (in_array($template->slug, ['order_confirmation', 'user_registration', 'contact_form'])) {
            return redirect()->route('admin.email-templates.index')
                ->with('error', 'No se pueden eliminar las plantillas predeterminadas del sistema.');
        }

        $template->delete();

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Plantilla de correo eliminada exitosamente.');
    }

    /**
     * Preview email template
     */
    public function preview(Request $request, EmailTemplate $template)
    {
        $sampleData = $this->getSampleData($template->type);
        $processedContent = $template->replaceVariables($sampleData);

        return response()->json([
            'subject' => $processedContent['subject'],
            'body' => nl2br(e($processedContent['body'])),
        ]);
    }

    /**
     * Get sample data for preview
     */
    private function getSampleData($type)
    {
        $sampleData = [
            EmailTemplate::TYPE_ORDER_STATUS => [
                'order_id' => '12345',
                'order_status' => 'Procesado',
                'customer_name' => 'Juan Pérez',
                'customer_email' => 'juan@example.com',
                'order_total' => '150.00',
                'order_date' => '25/09/2025',
                'delivery_date' => '27/09/2025',
                'tracking_url' => 'https://tuti.com/pedidos/12345',
            ],
            EmailTemplate::TYPE_ORDER_CONFIRMATION => [
                'order_id' => '12345',
                'customer_name' => 'Juan Pérez',
                'customer_email' => 'juan@example.com',
                'order_total' => '150.00',
                'order_products' => '<tr><td>Producto 1</td><td>2</td><td>$50.00</td></tr><tr><td>Producto 2</td><td>1</td><td>$100.00</td></tr>',
                'delivery_date' => '27/09/2025',
                'order_url' => 'https://tuti.com/pedidos/12345',
            ],
            EmailTemplate::TYPE_USER_REGISTRATION => [
                'customer_name' => 'Juan Pérez',
                'customer_email' => 'juan@example.com',
                'activation_link' => 'https://tuti.com/activar/abc123',
                'login_url' => 'https://tuti.com/login',
            ],
            EmailTemplate::TYPE_CONTACT_FORM => [
                'contact_name' => 'María González',
                'contact_email' => 'maria@example.com',
                'contact_phone' => '555-1234',
                'business_name' => 'Empresa ABC',
                'city' => 'Bogotá',
                'nit' => '123456789',
                'message' => 'Nuevo contacto registrado',
                'contact_date' => '25/09/2025 15:30',
            ],
        ];

        return $sampleData[$type] ?? [];
    }
}
