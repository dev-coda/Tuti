<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RetentionRule;
use Illuminate\Http\Request;

class RetentionRuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = RetentionRule::query()
            ->when($request->q, fn ($query, $q) => $query->where('tax_group', 'like', "%{$q}%"))
            ->orderBy('tax_group')
            ->orderBy('product_type')
            ->paginate(20);

        return view('admin.retentions.index', compact('rules'));
    }

    public function create()
    {
        $taxGroups = RetentionRule::TAX_GROUPS;
        $productTypes = RetentionRule::PRODUCT_TYPES;

        return view('admin.retentions.create', compact('taxGroups', 'productTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_group'        => 'required|string|max:50',
            'product_type'     => 'required|string|in:articulo,flete',
            'base_rte_fuente'  => 'required|numeric|min:0',
            'pct_rte_fuente'   => 'required|numeric|min:0|max:100',
            'base_rte_iva'     => 'required|numeric|min:0',
            'pct_rte_iva'      => 'required|numeric|min:0|max:100',
            'active'           => 'boolean',
        ]);

        $validated['active'] = $request->boolean('active', true);

        $existing = RetentionRule::where('tax_group', $validated['tax_group'])
            ->where('product_type', $validated['product_type'])
            ->exists();

        if ($existing) {
            return back()
                ->withInput()
                ->withErrors(['tax_group' => 'Ya existe una regla para este grupo de impuestos y tipo de producto.']);
        }

        RetentionRule::create($validated);

        return to_route('admin.retentions.index')->with('success', 'Regla de retención creada exitosamente.');
    }

    public function edit(RetentionRule $retention)
    {
        $taxGroups = RetentionRule::TAX_GROUPS;
        $productTypes = RetentionRule::PRODUCT_TYPES;

        return view('admin.retentions.edit', compact('retention', 'taxGroups', 'productTypes'));
    }

    public function update(Request $request, RetentionRule $retention)
    {
        $validated = $request->validate([
            'tax_group'        => 'required|string|max:50',
            'product_type'     => 'required|string|in:articulo,flete',
            'base_rte_fuente'  => 'required|numeric|min:0',
            'pct_rte_fuente'   => 'required|numeric|min:0|max:100',
            'base_rte_iva'     => 'required|numeric|min:0',
            'pct_rte_iva'      => 'required|numeric|min:0|max:100',
            'active'           => 'boolean',
        ]);

        $validated['active'] = $request->boolean('active', true);

        $duplicate = RetentionRule::where('tax_group', $validated['tax_group'])
            ->where('product_type', $validated['product_type'])
            ->where('id', '!=', $retention->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withInput()
                ->withErrors(['tax_group' => 'Ya existe otra regla para este grupo de impuestos y tipo de producto.']);
        }

        $retention->update($validated);

        return to_route('admin.retentions.index')->with('success', 'Regla de retención actualizada exitosamente.');
    }

    public function destroy(RetentionRule $retention)
    {
        $retention->delete();

        return to_route('admin.retentions.index')->with('success', 'Regla de retención eliminada.');
    }
}
