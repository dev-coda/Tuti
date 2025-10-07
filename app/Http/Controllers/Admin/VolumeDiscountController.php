<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VolumeDiscount;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VolumeDiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $volumeDiscounts = VolumeDiscount::query()
            ->withCount('products')
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('promociones.descuento-volumen', compact('volumeDiscounts'));
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

        return view('promociones.volume-discounts.create', compact('products', 'categories', 'brands', 'vendors'));
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
            'min_quantity' => 'required|integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
            'applies_to' => 'required|in:products,categories,brands,vendors,cart',
            'applies_to_ids' => 'nullable|array',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'active' => 'nullable|boolean',
        ]);

        // Validate discount based on type
        if ($validate['discount_type'] === 'percentage' && $validate['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'El descuento porcentual no puede ser mayor a 100%']);
        }

        // Validate max_quantity is greater than min_quantity
        if ($validate['max_quantity'] && $validate['max_quantity'] <= $validate['min_quantity']) {
            return back()->withErrors(['max_quantity' => 'La cantidad máxima debe ser mayor que la cantidad mínima']);
        }

        $volumeDiscount = VolumeDiscount::create($validate);

        // Sync products if applies_to is products
        if ($validate['applies_to'] === 'products' && $request->has('product_ids')) {
            $volumeDiscount->products()->sync($request->product_ids);
        }

        return redirect()->route('promociones.descuento-volumen')->with('success', 'Descuento por volumen creado exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(VolumeDiscount $volumeDiscount)
    {
        $volumeDiscount->load('products');
        return view('promociones.volume-discounts.show', compact('volumeDiscount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VolumeDiscount $volumeDiscount)
    {
        $products = Product::pluck('name', 'id');
        $categories = Category::pluck('name', 'id');
        $brands = Brand::pluck('name', 'id');
        $vendors = Vendor::pluck('name', 'id');

        $volumeDiscount->load('products');

        return view('promociones.volume-discounts.edit', compact('volumeDiscount', 'products', 'categories', 'brands', 'vendors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VolumeDiscount $volumeDiscount)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0',
            'min_quantity' => 'required|integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
            'applies_to' => 'required|in:products,categories,brands,vendors,cart',
            'applies_to_ids' => 'nullable|array',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'active' => 'nullable|boolean',
        ]);

        // Validate discount based on type
        if ($validate['discount_type'] === 'percentage' && $validate['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'El descuento porcentual no puede ser mayor a 100%']);
        }

        // Validate max_quantity is greater than min_quantity
        if ($validate['max_quantity'] && $validate['max_quantity'] <= $validate['min_quantity']) {
            return back()->withErrors(['max_quantity' => 'La cantidad máxima debe ser mayor que la cantidad mínima']);
        }

        $volumeDiscount->update($validate);

        // Sync products if applies_to is products
        if ($validate['applies_to'] === 'products' && $request->has('product_ids')) {
            $volumeDiscount->products()->sync($request->product_ids);
        }

        return redirect()->route('promociones.descuento-volumen')->with('success', 'Descuento por volumen actualizado exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VolumeDiscount $volumeDiscount)
    {
        $volumeDiscount->delete();
        return redirect()->route('promociones.descuento-volumen')->with('success', 'Descuento por volumen eliminado exitosamente');
    }
}
