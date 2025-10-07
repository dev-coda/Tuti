<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promocion;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Vendor;
use Illuminate\Http\Request;

class PromocionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $promociones = Promocion::query()
            ->withCount('usages')
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('promociones.promociones', compact('promociones'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::pluck('name', 'id');
        $categories = Category::pluck('name', 'id');
        $brands = Brand::pluck('name', 'id');
        $vendors = Vendor::pluck('name', 'id');

        return view('promocion.create', compact('products', 'categories', 'brands', 'vendors'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'level' => 'required|in:products,categories,brands,vendors,zones',
            'level_ids' => 'nullable|array',
            'minimum_cart_value' => 'nullable|numeric|min:0',
            'minimum_cart_units' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'active' => 'nullable|boolean',
        ]);

        // Validate discount based on type
        if ($validate['discount_type'] === 'percentage' && $validate['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'El descuento porcentual no puede ser mayor a 100%']);
        }

        $promocion = Promocion::create($validate);

        return redirect()->route('promociones.promociones')->with('success', 'Promoción creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Promocion $promocion)
    {
        $promocion->load('usages.user', 'usages.order');
        return view('promocion.show', compact('promocion'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Promocion $promocion)
    {
        $products = Product::pluck('name', 'id');
        $categories = Category::pluck('name', 'id');
        $brands = Brand::pluck('name', 'id');
        $vendors = Vendor::pluck('name', 'id');

        return view('promocion.edit', compact('promocion', 'products', 'categories', 'brands', 'vendors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Promocion $promocion)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'level' => 'required|in:products,categories,brands,vendors,zones',
            'level_ids' => 'nullable|array',
            'minimum_cart_value' => 'nullable|numeric|min:0',
            'minimum_cart_units' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'active' => 'nullable|boolean',
        ]);

        // Validate discount based on type
        if ($validate['discount_type'] === 'percentage' && $validate['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'El descuento porcentual no puede ser mayor a 100%']);
        }

        $promocion->update($validate);

        return redirect()->route('promociones.promociones')->with('success', 'Promoción actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Promocion $promocion)
    {
        $promocion->delete();
        return redirect()->route('promociones.promociones')->with('success', 'Promoción eliminada exitosamente');
    }
}
