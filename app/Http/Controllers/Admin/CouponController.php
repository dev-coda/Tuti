<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Vendor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $coupons = Coupon::latest()->paginate(20);
        return view('coupons.index', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::active()->get(['id', 'name']);
        $categories = Category::active()->get(['id', 'name']);
        $brands = Brand::all(['id', 'name']);
        $vendors = Vendor::all(['id', 'name']);
        $customers = User::whereHas('roles', function ($q) {
            $q->whereNotIn('name', ['admin', 'seller']);
        })->get(['id', 'name', 'email']);
        $roles = Role::whereNotIn('name', ['admin', 'seller'])->get(['name']);

        return view('coupons.create', compact('products', 'categories', 'brands', 'vendors', 'customers', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in([Coupon::TYPE_FIXED_AMOUNT, Coupon::TYPE_PERCENTAGE])],
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'usage_limit_per_vendor' => 'nullable|integer|min:1',
            'total_usage_limit' => 'nullable|integer|min:1',
            'applies_to' => ['required', Rule::in([
                Coupon::APPLIES_TO_CART,
                Coupon::APPLIES_TO_PRODUCT,
                Coupon::APPLIES_TO_CATEGORY,
                Coupon::APPLIES_TO_BRAND,
                Coupon::APPLIES_TO_VENDOR,
                Coupon::APPLIES_TO_CUSTOMER,
                Coupon::APPLIES_TO_CUSTOMER_TYPE,
            ])],
            'applies_to_ids' => 'nullable|array',
            'applies_to_ids.*' => 'nullable|string',
            'except_product_ids' => 'nullable|array',
            'except_product_ids.*' => 'nullable|integer|exists:products,id',
            'except_category_ids' => 'nullable|array',
            'except_category_ids.*' => 'nullable|integer|exists:categories,id',
            'except_brand_ids' => 'nullable|array',
            'except_brand_ids.*' => 'nullable|integer|exists:brands,id',
            'except_vendor_ids' => 'nullable|array',
            'except_vendor_ids.*' => 'nullable|integer|exists:vendors,id',
            'except_customer_ids' => 'nullable|array',
            'except_customer_ids.*' => 'nullable|integer|exists:users,id',
            'except_customer_types' => 'nullable|array',
            'except_customer_types.*' => 'nullable|string',
            'minimum_amount' => 'nullable|numeric|min:0',
            'active' => 'boolean',
        ]);

        // Additional validation for percentage coupons
        if ($validated['type'] === Coupon::TYPE_PERCENTAGE && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'El porcentaje no puede ser mayor a 100%'])->withInput();
        }

        // Convert applies_to_ids to appropriate format based on applies_to type
        if ($validated['applies_to'] !== Coupon::APPLIES_TO_CART && empty($validated['applies_to_ids'])) {
            return back()->withErrors(['applies_to_ids' => 'Debe seleccionar al menos un elemento para aplicar el cupón'])->withInput();
        }

        // Convert string IDs to integers where appropriate
        if (!empty($validated['applies_to_ids'])) {
            if (in_array($validated['applies_to'], [
                Coupon::APPLIES_TO_PRODUCT,
                Coupon::APPLIES_TO_CATEGORY,
                Coupon::APPLIES_TO_BRAND,
                Coupon::APPLIES_TO_VENDOR,
                Coupon::APPLIES_TO_CUSTOMER
            ])) {
                $validated['applies_to_ids'] = array_map('intval', $validated['applies_to_ids']);
            }
        }

        Coupon::create($validated);

        return redirect()->route('coupons.index')->with('success', 'Cupón creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon)
    {
        $coupon->load('usages.user', 'usages.order');
        return view('coupons.show', compact('coupon'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Coupon $coupon)
    {
        $products = Product::active()->get(['id', 'name']);
        $categories = Category::active()->get(['id', 'name']);
        $brands = Brand::all(['id', 'name']);
        $vendors = Vendor::all(['id', 'name']);
        $customers = User::whereHas('roles', function ($q) {
            $q->whereNotIn('name', ['admin', 'seller']);
        })->get(['id', 'name', 'email']);
        $roles = Role::whereNotIn('name', ['admin', 'seller'])->get(['name']);

        return view('coupons.edit', compact('coupon', 'products', 'categories', 'brands', 'vendors', 'customers', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['required', Rule::in([Coupon::TYPE_FIXED_AMOUNT, Coupon::TYPE_PERCENTAGE])],
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'usage_limit_per_vendor' => 'nullable|integer|min:1',
            'total_usage_limit' => 'nullable|integer|min:1',
            'applies_to' => ['required', Rule::in([
                Coupon::APPLIES_TO_CART,
                Coupon::APPLIES_TO_PRODUCT,
                Coupon::APPLIES_TO_CATEGORY,
                Coupon::APPLIES_TO_BRAND,
                Coupon::APPLIES_TO_VENDOR,
                Coupon::APPLIES_TO_CUSTOMER,
                Coupon::APPLIES_TO_CUSTOMER_TYPE,
            ])],
            'applies_to_ids' => 'nullable|array',
            'applies_to_ids.*' => 'nullable|string',
            'except_product_ids' => 'nullable|array',
            'except_product_ids.*' => 'nullable|integer|exists:products,id',
            'except_category_ids' => 'nullable|array',
            'except_category_ids.*' => 'nullable|integer|exists:categories,id',
            'except_brand_ids' => 'nullable|array',
            'except_brand_ids.*' => 'nullable|integer|exists:brands,id',
            'except_vendor_ids' => 'nullable|array',
            'except_vendor_ids.*' => 'nullable|integer|exists:vendors,id',
            'except_customer_ids' => 'nullable|array',
            'except_customer_ids.*' => 'nullable|integer|exists:users,id',
            'except_customer_types' => 'nullable|array',
            'except_customer_types.*' => 'nullable|string',
            'minimum_amount' => 'nullable|numeric|min:0',
            'active' => 'boolean',
        ]);

        // Additional validation for percentage coupons
        if ($validated['type'] === Coupon::TYPE_PERCENTAGE && $validated['value'] > 100) {
            return back()->withErrors(['value' => 'El porcentaje no puede ser mayor a 100%'])->withInput();
        }

        // Convert applies_to_ids to appropriate format based on applies_to type
        if ($validated['applies_to'] !== Coupon::APPLIES_TO_CART && empty($validated['applies_to_ids'])) {
            return back()->withErrors(['applies_to_ids' => 'Debe seleccionar al menos un elemento para aplicar el cupón'])->withInput();
        }

        // Convert string IDs to integers where appropriate
        if (!empty($validated['applies_to_ids'])) {
            if (in_array($validated['applies_to'], [
                Coupon::APPLIES_TO_PRODUCT,
                Coupon::APPLIES_TO_CATEGORY,
                Coupon::APPLIES_TO_BRAND,
                Coupon::APPLIES_TO_VENDOR,
                Coupon::APPLIES_TO_CUSTOMER
            ])) {
                $validated['applies_to_ids'] = array_map('intval', $validated['applies_to_ids']);
            }
        }

        $coupon->update($validated);

        return redirect()->route('coupons.index')->with('success', 'Cupón actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coupon $coupon)
    {
        // Check if coupon has been used
        if ($coupon->usages()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un cupón que ya ha sido utilizado.');
        }

        $coupon->delete();

        return redirect()->route('coupons.index')->with('success', 'Cupón eliminado exitosamente.');
    }

    /**
     * Toggle coupon active status
     */
    public function toggle(Coupon $coupon)
    {
        $coupon->update(['active' => !$coupon->active]);

        $status = $coupon->active ? 'activado' : 'desactivado';
        return back()->with('success', "Cupón {$status} exitosamente.");
    }
}
