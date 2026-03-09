<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpsellZone;
use App\Models\UpsellRule;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UpsellZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $zones = UpsellZone::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('position')
            ->orderBy('name')
            ->paginate();

        $context = compact('zones');

        return view('admin.upsell-zones.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $rules = UpsellRule::active()->orderBy('name')->get();
        $context = compact('rules');

        return view('admin.upsell-zones.create', $context);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:upsell_zones,slug',
            'description' => 'nullable|string',
            'display_title' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'position' => 'nullable|integer|min:0',
            'max_products' => 'required|integer|min:1|max:20',
            'context' => 'required|in:product_detail,cart,checkout',
            'rule_ids' => 'nullable|array',
            'rule_ids.*' => 'exists:upsell_rules,id',
            'rule_priorities' => 'nullable|array',
            'rule_priorities.*' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($validate, $request) {
            if (empty($validate['slug'])) {
                $validate['slug'] = Str::slug($validate['name']);
            }

            $zone = UpsellZone::create($validate);

            // Attach rules with priorities
            if ($request->has('rule_ids')) {
                $rules = [];
                foreach ($request->rule_ids as $index => $ruleId) {
                    $priority = $request->rule_priorities[$index] ?? 0;
                    $rules[$ruleId] = [
                        'priority' => $priority,
                        'active' => true,
                    ];
                }
                $zone->rules()->attach($rules);
            }
        });

        return to_route('admin.upsell-zones.index')
            ->with('success', 'Zona de upsell creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(UpsellZone $upsellZone)
    {
        $upsellZone->load(['rules', 'products']);
        $context = compact('upsellZone');

        return view('admin.upsell-zones.show', $context);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UpsellZone $upsellZone)
    {
        $upsellZone->load(['rules', 'products']);
        $allRules = UpsellRule::active()->orderBy('name')->get();
        $context = compact('upsellZone', 'allRules');

        return view('admin.upsell-zones.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UpsellZone $upsellZone)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:upsell_zones,slug,' . $upsellZone->id,
            'description' => 'nullable|string',
            'display_title' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
            'position' => 'nullable|integer|min:0',
            'max_products' => 'required|integer|min:1|max:20',
            'context' => 'required|in:product_detail,cart,checkout',
            'rule_ids' => 'nullable|array',
            'rule_ids.*' => 'exists:upsell_rules,id',
            'rule_priorities' => 'nullable|array',
            'rule_priorities.*' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($validate, $request, $upsellZone) {
            if (empty($validate['slug'])) {
                $validate['slug'] = Str::slug($validate['name']);
            }

            $upsellZone->update($validate);

            // Sync rules with priorities
            if ($request->has('rule_ids')) {
                $rules = [];
                foreach ($request->rule_ids as $index => $ruleId) {
                    $priority = $request->rule_priorities[$index] ?? 0;
                    $rules[$ruleId] = [
                        'priority' => $priority,
                        'active' => true,
                    ];
                }
                $upsellZone->rules()->sync($rules);
            } else {
                $upsellZone->rules()->detach();
            }
        });

        return to_route('admin.upsell-zones.index')
            ->with('success', 'Zona de upsell actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UpsellZone $upsellZone)
    {
        $upsellZone->delete();

        return to_route('admin.upsell-zones.index')
            ->with('success', 'Zona de upsell eliminada correctamente');
    }

    /**
     * Manage products for a zone
     */
    public function manageProducts(UpsellZone $upsellZone)
    {
        $upsellZone->load('products');
        $products = Product::active()->orderBy('name')->paginate(20);
        $context = compact('upsellZone', 'products');

        return view('admin.upsell-zones.manage-products', $context);
    }

    /**
     * Attach products to a zone
     */
    public function attachProducts(Request $request, UpsellZone $upsellZone)
    {
        $validate = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $products = [];
        foreach ($validate['product_ids'] as $index => $productId) {
            $products[$productId] = ['position' => $index];
        }

        $upsellZone->products()->sync($products);

        return back()->with('success', 'Productos asignados correctamente');
    }

    /**
     * Remove a product from a zone
     */
    public function detachProduct(UpsellZone $upsellZone, Product $product)
    {
        $upsellZone->products()->detach($product->id);

        return back()->with('success', 'Producto removido correctamente');
    }

    /**
     * Update product positions
     */
    public function updateProductPositions(Request $request, UpsellZone $upsellZone)
    {
        $validate = $request->validate([
            'positions' => 'required|array',
            'positions.*.product_id' => 'required|exists:products,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);

        foreach ($validate['positions'] as $item) {
            $upsellZone->products()->updateExistingPivot($item['product_id'], [
                'position' => $item['position'],
            ]);
        }

        return back()->with('success', 'Posiciones actualizadas correctamente');
    }
}
