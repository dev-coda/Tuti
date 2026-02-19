<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Bonification;
use App\Models\Setting;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $tags = Tag::query()
            ->when($request->q, function ($query, $q) {
                $query->where('content', 'like', "%{$q}%");
            })
            ->orderBy('priority')
            ->orderBy('created_at', 'desc')
            ->paginate();

        // Get auto tag settings
        $autoTagNuevoEnabled = Setting::getByKey('auto_tag_nuevo_enabled') === '1';
        $autoTagDescuentoEnabled = Setting::getByKey('auto_tag_descuento_enabled') === '1';

        return view('tags.index', compact('tags', 'autoTagNuevoEnabled', 'autoTagDescuentoEnabled'));
    }

    /**
     * Toggle auto tag NUEVO
     */
    public function toggleAutoTagNuevo(Request $request)
    {
        $enabled = $request->boolean('enabled', false);
        Setting::updateOrCreate(
            ['key' => 'auto_tag_nuevo_enabled'],
            [
                'name' => 'Etiqueta automática NUEVO',
                'value' => $enabled ? '1' : '0',
                'show' => true,
            ]
        );

        return back()->with('success', $enabled ? 'Etiqueta automática NUEVO habilitada' : 'Etiqueta automática NUEVO deshabilitada');
    }

    /**
     * Toggle auto tag DESCUENTO
     */
    public function toggleAutoTagDescuento(Request $request)
    {
        $enabled = $request->boolean('enabled', false);
        Setting::updateOrCreate(
            ['key' => 'auto_tag_descuento_enabled'],
            [
                'name' => 'Etiqueta automática DESCUENTO',
                'value' => $enabled ? '1' : '0',
                'show' => true,
            ]
        );

        return back()->with('success', $enabled ? 'Etiqueta automática DESCUENTO habilitada' : 'Etiqueta automática DESCUENTO deshabilitada');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get products with SKU and name for better identification
        $products = Product::orderBy('name')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'display' => "{$product->sku} - {$product->name}",
            ];
        });

        $categories = Category::orderBy('name')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });

        $brands = Brand::orderBy('name')->get()->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
            ];
        });

        $bonifications = Bonification::orderBy('name')->get()->map(function ($bonification) {
            return [
                'id' => $bonification->id,
                'name' => $bonification->name,
            ];
        });

        return view('tags.create', compact('products', 'categories', 'brands', 'bonifications'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'content' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'enabled' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'bonification_ids' => 'nullable|array',
            'bonification_ids.*' => 'exists:bonifications,id',
        ]);

        $tag = Tag::create([
            'content' => $validate['content'],
            'priority' => $validate['priority'],
            'enabled' => $request->has('enabled') ? (bool)$request->input('enabled') : false,
        ]);

        // Sync relationships
        if (!empty($validate['product_ids'])) {
            $tag->products()->sync($validate['product_ids']);
        }
        if (!empty($validate['category_ids'])) {
            $tag->categories()->sync($validate['category_ids']);
        }
        if (!empty($validate['brand_ids'])) {
            $tag->brands()->sync($validate['brand_ids']);
        }
        if (!empty($validate['bonification_ids'])) {
            $tag->bonifications()->sync($validate['bonification_ids']);
        }

        return to_route('tags.index')->with('success', 'Etiqueta creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag)
    {
        $tag->load(['products', 'categories', 'brands', 'bonifications']);

        // Get products with SKU and name for better identification
        $products = Product::orderBy('name')->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'display' => "{$product->sku} - {$product->name}",
            ];
        });

        $categories = Category::orderBy('name')->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });

        $brands = Brand::orderBy('name')->get()->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
            ];
        });

        $bonifications = Bonification::orderBy('name')->get()->map(function ($bonification) {
            return [
                'id' => $bonification->id,
                'name' => $bonification->name,
            ];
        });

        return view('tags.edit', compact('tag', 'products', 'categories', 'brands', 'bonifications'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        $validate = $request->validate([
            'content' => 'required|string|max:255',
            'priority' => 'required|integer|min:0',
            'enabled' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'bonification_ids' => 'nullable|array',
            'bonification_ids.*' => 'exists:bonifications,id',
        ]);

        $tag->update([
            'content' => $validate['content'],
            'priority' => $validate['priority'],
            'enabled' => $request->has('enabled') ? (bool)$request->input('enabled') : false,
        ]);

        // Sync relationships
        $tag->products()->sync($validate['product_ids'] ?? []);
        $tag->categories()->sync($validate['category_ids'] ?? []);
        $tag->brands()->sync($validate['brand_ids'] ?? []);
        $tag->bonifications()->sync($validate['bonification_ids'] ?? []);

        return to_route('tags.index')->with('success', 'Etiqueta actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        $tag->delete();

        return to_route('tags.index')->with('success', 'Etiqueta eliminada correctamente');
    }

    /**
     * Toggle enabled status
     */
    public function toggle(Tag $tag)
    {
        $tag->update(['enabled' => !$tag->enabled]);

        $status = $tag->enabled ? 'habilitada' : 'deshabilitada';
        return back()->with('success', "Etiqueta {$status} correctamente");
    }
}
