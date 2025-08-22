<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductHighlight;
use Illuminate\Http\Request;

class ProductHighlightController extends Controller
{
    /**
     * Show the form for managing product highlights for a specific category
     */
    public function index(Category $category)
    {
        $highlights = $category->highlightedProducts()
            ->with('product')
            ->orderBy('position')
            ->get();

        $availablePositions = ProductHighlight::getAvailablePositions($category->id);

        $products = Product::active()
            ->whereHas('categories', function ($query) use ($category) {
                $query->where('category_id', $category->id);
            })
            ->whereNotIn('id', $highlights->pluck('product_id'))
            ->orderBy('name')
            ->get();

        return view('categories.highlights', compact('category', 'highlights', 'availablePositions', 'products'));
    }

    /**
     * Store a new product highlight
     */
    public function store(Request $request, Category $category)
    {
        $validated = $request->validate(ProductHighlight::getValidationRules());
        $validated['category_id'] = $category->id;

        // Check if position is available
        $availablePositions = ProductHighlight::getAvailablePositions($category->id);
        if (!in_array($validated['position'], $availablePositions)) {
            return back()->withErrors(['position' => 'Esta posición ya está ocupada.']);
        }

        // Check if product belongs to this category
        $productInCategory = Product::whereHas('categories', function ($query) use ($category) {
            $query->where('category_id', $category->id);
        })->where('id', $validated['product_id'])->exists();

        if (!$productInCategory) {
            return back()->withErrors(['product_id' => 'El producto seleccionado no pertenece a esta categoría.']);
        }

        ProductHighlight::create($validated);

        return back()->with('success', 'Producto destacado agregado exitosamente.');
    }

    /**
     * Update a product highlight
     */
    public function update(Request $request, Category $category, ProductHighlight $highlight)
    {
        $validated = $request->validate([
            'position' => 'required|integer|min:1|max:4',
            'active' => 'boolean',
        ]);

        // Check if new position is available (excluding current highlight)
        if ($validated['position'] != $highlight->position) {
            $positionTaken = ProductHighlight::forCategory($category->id)
                ->where('id', '!=', $highlight->id)
                ->where('position', $validated['position'])
                ->exists();

            if ($positionTaken) {
                return back()->withErrors(['position' => 'Esta posición ya está ocupada.']);
            }
        }

        $highlight->update($validated);

        return back()->with('success', 'Producto destacado actualizado exitosamente.');
    }

    /**
     * Remove a product highlight
     */
    public function destroy(Category $category, ProductHighlight $highlight)
    {
        $highlight->delete();

        return back()->with('success', 'Producto destacado eliminado exitosamente.');
    }

    /**
     * Search for products to highlight
     */
    public function search(Request $request, Category $category)
    {
        $query = $request->get('q');

        $products = Product::active()
            ->whereHas('categories', function ($categoryQuery) use ($category) {
                $categoryQuery->where('category_id', $category->id);
            })
            ->where(function ($productQuery) use ($query) {
                $productQuery->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->whereNotIn('id', function ($subQuery) use ($category) {
                $subQuery->select('product_id')
                    ->from('product_highlights')
                    ->where('category_id', $category->id)
                    ->where('active', true);
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'products' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                ];
            })
        ]);
    }

    /**
     * Reorder product highlights
     */
    public function reorder(Request $request, Category $category)
    {
        $validated = $request->validate([
            'highlights' => 'required|array',
            'highlights.*.id' => 'required|exists:product_highlights,id',
            'highlights.*.position' => 'required|integer|min:1|max:4',
        ]);

        // Check that all positions are unique
        $positions = collect($validated['highlights'])->pluck('position')->toArray();
        if (count($positions) !== count(array_unique($positions))) {
            return back()->withErrors(['highlights' => 'Las posiciones deben ser únicas.']);
        }

        // Update each highlight
        foreach ($validated['highlights'] as $highlightData) {
            ProductHighlight::where('id', $highlightData['id'])
                ->where('category_id', $category->id)
                ->update(['position' => $highlightData['position']]);
        }

        return back()->with('success', 'Orden de productos destacados actualizado exitosamente.');
    }
}
