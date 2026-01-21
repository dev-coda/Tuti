<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Display a listing of shipping methods.
     */
    public function index()
    {
        $shippingMethods = ShippingMethod::orderBy('sort_order')->get();
        
        return view('admin.shipping-methods.index', compact('shippingMethods'));
    }

    /**
     * Show the form for editing a shipping method.
     */
    public function edit(ShippingMethod $shippingMethod)
    {
        return view('admin.shipping-methods.edit', compact('shippingMethod'));
    }

    /**
     * Update the specified shipping method.
     */
    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        $validated['enabled'] = $request->has('enabled');

        $shippingMethod->update($validated);

        return redirect()->route('shipping-methods.index')
            ->with('success', 'Método de envío actualizado correctamente');
    }

    /**
     * Toggle the enabled status of a shipping method.
     */
    public function toggle(ShippingMethod $shippingMethod)
    {
        $shippingMethod->update(['enabled' => !$shippingMethod->enabled]);

        $status = $shippingMethod->enabled ? 'habilitado' : 'deshabilitado';
        
        return back()->with('success', "Método de envío {$status} correctamente");
    }
}
