<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromocionesApiController extends Controller
{
    /**
     * Display a listing of promotions/coupons.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        // Filter by active/valid status
        if ($request->get('active_only', true)) {
            $query->active();
        }

        if ($request->get('valid_only', false)) {
            $query->valid();
        }

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('applies_to')) {
            $query->where('applies_to', $request->get('applies_to'));
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $coupons = $query->paginate($perPage);

        // Transform data
        $coupons->transform(function ($coupon) {
            return [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'valid_from' => $coupon->valid_from,
                'valid_to' => $coupon->valid_to,
                'usage_limit_per_customer' => $coupon->usage_limit_per_customer,
                'usage_limit_per_vendor' => $coupon->usage_limit_per_vendor,
                'total_usage_limit' => $coupon->total_usage_limit,
                'current_usage' => $coupon->current_usage,
                'applies_to' => $coupon->applies_to,
                'applies_to_ids' => $coupon->applies_to_ids,
                'minimum_amount' => $coupon->minimum_amount,
                'active' => $coupon->active,
                'is_valid' => $coupon->isValid(),
                'has_exceeded_total_limit' => $coupon->hasExceededTotalLimit(),
                'created_at' => $coupon->created_at,
                'updated_at' => $coupon->updated_at,
            ];
        });

        return response()->json([
            'data' => $coupons->items(),
            'pagination' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
            ]
        ]);
    }

    /**
     * Display the specified promotion/coupon.
     */
    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'description' => $coupon->description,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'valid_from' => $coupon->valid_from,
                'valid_to' => $coupon->valid_to,
                'usage_limit_per_customer' => $coupon->usage_limit_per_customer,
                'usage_limit_per_vendor' => $coupon->usage_limit_per_vendor,
                'total_usage_limit' => $coupon->total_usage_limit,
                'current_usage' => $coupon->current_usage,
                'applies_to' => $coupon->applies_to,
                'applies_to_ids' => $coupon->applies_to_ids,
                'except_product_ids' => $coupon->except_product_ids,
                'except_category_ids' => $coupon->except_category_ids,
                'except_brand_ids' => $coupon->except_brand_ids,
                'except_vendor_ids' => $coupon->except_vendor_ids,
                'except_customer_ids' => $coupon->except_customer_ids,
                'except_customer_types' => $coupon->except_customer_types,
                'minimum_amount' => $coupon->minimum_amount,
                'active' => $coupon->active,
                'is_valid' => $coupon->isValid(),
                'has_exceeded_total_limit' => $coupon->hasExceededTotalLimit(),
                'created_at' => $coupon->created_at,
                'updated_at' => $coupon->updated_at,
            ]
        ]);
    }

    /**
     * Validate a coupon code.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id',
            'cart_total' => 'nullable|numeric|min:0',
        ]);

        $coupon = Coupon::byCode($request->code)->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupón no encontrado'
            ], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupón expirado o inactivo'
            ], 400);
        }

        if ($coupon->hasExceededTotalLimit()) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupón ha alcanzado su límite de uso'
            ], 400);
        }

        if ($request->user_id && $coupon->hasUserExceededLimit($request->user_id)) {
            return response()->json([
                'valid' => false,
                'message' => 'Has alcanzado el límite de uso para este cupón'
            ], 400);
        }

        if ($request->cart_total && $coupon->minimum_amount && $request->cart_total < $coupon->minimum_amount) {
            return response()->json([
                'valid' => false,
                'message' => "Monto mínimo requerido: {$coupon->minimum_amount}"
            ], 400);
        }

        $discountAmount = $request->cart_total ? $coupon->calculateDiscount($request->cart_total) : null;

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount_amount' => $discountAmount,
            ]
        ]);
    }
}
