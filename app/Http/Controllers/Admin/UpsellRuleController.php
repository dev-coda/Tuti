<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UpsellRule;
use App\Models\Product;
use Illuminate\Http\Request;

class UpsellRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $rules = UpsellRule::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->paginate();

        $context = compact('rules');

        return view('admin.upsell-rules.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $ruleTypes = UpsellRule::getRuleTypes();
        
        // Get products with SKU and name for better identification
        $products = Product::orderBy('name')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'display' => "{$product->sku} - {$product->name}",
            ];
        });
        
        $context = compact('ruleTypes', 'products');

        return view('admin.upsell-rules.create', $context);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $ruleTypes = array_keys(UpsellRule::getRuleTypes());

        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', $ruleTypes),
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        // Set default config based on rule type
        if (empty($validate['config'])) {
            $validate['config'] = $this->getDefaultConfigForType($validate['type']);
        }
        
        // If type is manual and product_ids are provided, store them in config
        if ($validate['type'] === 'manual' && !empty($validate['product_ids'])) {
            $validate['config']['product_ids'] = $validate['product_ids'];
        }

        UpsellRule::create($validate);

        return to_route('admin.upsell-rules.index')
            ->with('success', 'Regla de upsell creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(UpsellRule $upsellRule)
    {
        $upsellRule->load('zones');
        $context = compact('upsellRule');

        return view('admin.upsell-rules.show', $context);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UpsellRule $upsellRule)
    {
        $ruleTypes = UpsellRule::getRuleTypes();
        
        // Get products with SKU and name for better identification
        $products = Product::orderBy('name')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'display' => "{$product->sku} - {$product->name}",
            ];
        });
        
        $context = compact('upsellRule', 'ruleTypes', 'products');

        return view('admin.upsell-rules.edit', $context);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UpsellRule $upsellRule)
    {
        $ruleTypes = array_keys(UpsellRule::getRuleTypes());

        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', $ruleTypes),
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'priority' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        // Set default config if empty
        if (empty($validate['config'])) {
            $validate['config'] = $this->getDefaultConfigForType($validate['type']);
        }
        
        // If type is manual and product_ids are provided, store them in config
        if ($validate['type'] === 'manual' && !empty($validate['product_ids'])) {
            $validate['config']['product_ids'] = $validate['product_ids'];
        } elseif ($validate['type'] === 'manual' && empty($validate['product_ids'])) {
            // Clear product_ids if type is manual but no products selected
            $validate['config']['product_ids'] = [];
        }

        $upsellRule->update($validate);

        return to_route('admin.upsell-rules.index')
            ->with('success', 'Regla de upsell actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UpsellRule $upsellRule)
    {
        $upsellRule->delete();

        return to_route('admin.upsell-rules.index')
            ->with('success', 'Regla de upsell eliminada correctamente');
    }

    /**
     * Get default configuration for a rule type
     */
    protected function getDefaultConfigForType(string $type): array
    {
        return match ($type) {
            'recent_orders' => ['days' => 90, 'limit' => 10],
            'favorite_products' => ['limit' => 10],
            'same_category' => ['limit' => 10],
            'same_brand' => ['limit' => 10],
            'best_selling' => ['limit' => 10],
            'related_products' => ['limit' => 10],
            'manual' => ['product_ids' => []],
            default => ['limit' => 10],
        };
    }
}
