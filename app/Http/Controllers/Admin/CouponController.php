<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Zone;
use App\Exports\CouponsExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
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
        $products = Product::active()->get(['id', 'name', 'sku']);
        $categories = Category::active()->get(['id', 'name']);
        $brands = Brand::all(['id', 'name']);
        $vendors = Vendor::all(['id', 'name']);
        
        // Get customers: users who are NOT admins or sellers
        // This includes users with no roles (regular customers) or with customer-type roles
        $customers = User::where(function ($query) {
            // Include users without any roles (regular customers)
            $query->whereDoesntHave('roles')
                // Or users who only have customer-type roles (not admin/seller)
                ->orWhereHas('roles', function ($q) {
                    $q->whereNotIn('name', ['admin', 'seller']);
                });
        })
        // Exclude users who have admin or seller role
        ->whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'seller']);
        })
        ->get(['id', 'name', 'email', 'document']);
        
        $roles = Role::whereNotIn('name', ['admin', 'seller'])->get(['name']);
        
        // Get zones and unique routes for restrictions
        $zones = Zone::select('id', 'zone', 'route')->distinct()->get();
        $uniqueZones = Zone::whereNotNull('zone')->distinct()->pluck('zone')->sort()->values();
        $uniqueRoutes = Zone::whereNotNull('route')->distinct()->pluck('route')->sort()->values();

        return view('coupons.create', compact('products', 'categories', 'brands', 'vendors', 'customers', 'roles', 'zones', 'uniqueZones', 'uniqueRoutes'));
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
            'allowed_zone_ids' => 'nullable|array',
            'allowed_zone_ids.*' => 'nullable|integer|exists:zones,id',
            'allowed_zones' => 'nullable|array',
            'allowed_zones.*' => 'nullable|string',
            'allowed_routes' => 'nullable|array',
            'allowed_routes.*' => 'nullable|string',
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

        // CRITICAL FIX: Ensure empty usage limits are stored as NULL (unlimited), not empty strings or 0
        // This prevents issues where empty fields are saved as 0 or "" which would block all usage
        $validated['usage_limit_per_customer'] = !empty($validated['usage_limit_per_customer']) ? (int) $validated['usage_limit_per_customer'] : null;
        $validated['usage_limit_per_vendor'] = !empty($validated['usage_limit_per_vendor']) ? (int) $validated['usage_limit_per_vendor'] : null;
        $validated['total_usage_limit'] = !empty($validated['total_usage_limit']) ? (int) $validated['total_usage_limit'] : null;

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
        $products = Product::active()->get(['id', 'name', 'sku']);
        $categories = Category::active()->get(['id', 'name']);
        $brands = Brand::all(['id', 'name']);
        $vendors = Vendor::all(['id', 'name']);
        
        // Get customers: users who are NOT admins or sellers
        // This includes users with no roles (regular customers) or with customer-type roles
        $customers = User::where(function ($query) {
            // Include users without any roles (regular customers)
            $query->whereDoesntHave('roles')
                // Or users who only have customer-type roles (not admin/seller)
                ->orWhereHas('roles', function ($q) {
                    $q->whereNotIn('name', ['admin', 'seller']);
                });
        })
        // Exclude users who have admin or seller role
        ->whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', ['admin', 'seller']);
        })
        ->get(['id', 'name', 'email', 'document']);
        
        $roles = Role::whereNotIn('name', ['admin', 'seller'])->get(['name']);
        
        // Get zones and unique routes for restrictions
        $zones = Zone::select('id', 'zone', 'route')->distinct()->get();
        $uniqueZones = Zone::whereNotNull('zone')->distinct()->pluck('zone')->sort()->values();
        $uniqueRoutes = Zone::whereNotNull('route')->distinct()->pluck('route')->sort()->values();

        return view('coupons.edit', compact('coupon', 'products', 'categories', 'brands', 'vendors', 'customers', 'roles', 'zones', 'uniqueZones', 'uniqueRoutes'));
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
            'allowed_zone_ids' => 'nullable|array',
            'allowed_zone_ids.*' => 'nullable|integer|exists:zones,id',
            'allowed_zones' => 'nullable|array',
            'allowed_zones.*' => 'nullable|string',
            'allowed_routes' => 'nullable|array',
            'allowed_routes.*' => 'nullable|string',
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

        // CRITICAL FIX: Ensure empty usage limits are stored as NULL (unlimited), not empty strings or 0
        // This fixes the issue where removing a limit after setting it doesn't allow unlimited uses
        $validated['usage_limit_per_customer'] = !empty($validated['usage_limit_per_customer']) ? (int) $validated['usage_limit_per_customer'] : null;
        $validated['usage_limit_per_vendor'] = !empty($validated['usage_limit_per_vendor']) ? (int) $validated['usage_limit_per_vendor'] : null;
        $validated['total_usage_limit'] = !empty($validated['total_usage_limit']) ? (int) $validated['total_usage_limit'] : null;

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

    /**
     * Mass create coupons based on a base coupon
     */
    public function massCreate(Request $request, $coupon)
    {
        // Resolve coupon manually to provide better error handling
        $coupon = Coupon::find($coupon);
        
        if (!$coupon) {
            return redirect()->route('coupons.index')->with('error', 'Cupón no encontrado.');
        }

        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000',
        ]);

        $quantity = (int) $request->quantity;
        $baseCode = $coupon->code;
        $createdCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $quantity; $i++) {
                $newCode = $baseCode . $i;
                
                // Check if code already exists
                if (Coupon::where('code', $newCode)->exists()) {
                    $errors[] = "El código '{$newCode}' ya existe. Se omitió.";
                    continue;
                }

                // Create new coupon with all attributes from base coupon
                $newCouponData = $coupon->only($coupon->getFillable());
                
                // Override specific fields for the new coupon
                $newCouponData['code'] = $newCode;
                $newCouponData['parent_coupon_id'] = $coupon->id;
                $newCouponData['is_mass_created'] = true;
                $newCouponData['current_usage'] = 0;

                Coupon::create($newCouponData);
                $createdCount++;
            }

            DB::commit();

            $message = "Se crearon {$createdCount} cupón(es) exitosamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(' ', $errors);
            }

            return redirect()->route('coupons.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear los cupones: ' . $e->getMessage());
        }
    }

    /**
     * Export coupons to Excel
     */
    public function export(Request $request)
    {
        $onlyMassCreated = $request->boolean('only_mass_created', false);
        
        $filename = 'cupones_' . now()->format('Y-m-d_His');
        if ($onlyMassCreated) {
            $filename .= '_masivos';
        }
        $filename .= '.xlsx';

        return Excel::download(
            new CouponsExport($onlyMassCreated),
            $filename
        );
    }
}
