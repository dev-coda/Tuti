<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\CouponDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Diagnostic and unit test module for coupon workflow.
 * Creates mock orders (not transmitted) and inspects generated XML.
 */
class CouponTestController extends Controller
{
    public function index()
    {
        $recentOrders = Order::with(['user', 'zone', 'coupon'])
            ->whereNotNull('zone_id')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'user_id', 'zone_id', 'total', 'coupon_id', 'coupon_code', 'created_at']);

        $coupons = Coupon::active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'value', 'applies_to']);

        return view('admin.coupon-tests.index', compact('recentOrders', 'coupons'));
    }

    /**
     * Preview XML for an existing order (no transmission).
     */
    public function previewOrderXml(Request $request)
    {
        $orderId = $request->query('order_id');
        if (!$orderId) {
            return redirect()->route('coupon-tests.index')->with('error', 'Selecciona una orden.');
        }
        $order = Order::find($orderId);
        if (!$order) {
            return redirect()->route('coupon-tests.index')->with('error', 'Orden no encontrada.');
        }
        $order->load(['products.product', 'user', 'zone', 'coupon']);

        $xml = OrderRepository::buildOrderXmlForDiagnostic($order);

        if (!$xml) {
            return redirect()->route('coupon-tests.index')->with('error', 'La orden no tiene zona asignada o no se pudo generar el XML.');
        }

        $productSummary = $order->products->map(fn ($op) => [
            'product_id' => $op->product_id,
            'name' => $op->product?->name ?? 'N/A',
            'quantity' => $op->quantity,
            'price' => $op->price,
            'percentage' => $op->percentage,
            'discount_type' => $op->discount_type ?? 'percentage',
            'flat_discount_amount' => $op->flat_discount_amount ?? 0,
        ]);

        return view('admin.coupon-tests.preview-xml', [
            'order' => $order,
            'xml' => $xml,
            'productSummary' => $productSummary,
        ]);
    }

    /**
     * Show form to run a mock coupon test.
     */
    public function showMockForm()
    {
        $users = User::whereHas('zones')->orderBy('name')->get(['id', 'name', 'document']);
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'price']);
        $coupons = Coupon::active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'value']);

        return view('admin.coupon-tests.mock-form', compact('users', 'products', 'coupons'));
    }

    /**
     * Run a mock coupon test: build mock order with selected products + coupons, generate XML.
     * Order is NOT saved or transmitted.
     */
    public function runMockTest(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_id' => 'nullable|integer',
            'coupon_codes_text' => 'nullable|string',
        ]);

        $couponCodesInput = $validated['coupon_codes_text'] ?? '';
        $validated['coupon_codes'] = array_values(array_filter(array_map('trim', explode(',', $couponCodesInput))));

        $user = User::with('zones')->findOrFail($validated['user_id']);
        $zone = $user->zones()->first();
        if (!$zone) {
            return back()->with('error', 'El usuario no tiene zonas asignadas. Asigna una zona para poder generar el XML.');
        }

        // Build cart array (same structure as session cart)
        $cart = [];
        foreach ($validated['products'] as $row) {
            $cart[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
                'variation_id' => $row['variation_id'] ?? null,
            ];
        }

        // Apply coupons to get modified products
        $couponCodes = array_filter($validated['coupon_codes'] ?? []);
        $hasOrders = $user->orders()->exists();
        $modifiedProductsLookup = [];
        $couponResult = null;

        if (!empty($couponCodes)) {
            $coupons = Coupon::whereIn('code', $couponCodes)->get();
            $couponDiscountService = app(CouponDiscountService::class);
            $couponResult = $couponDiscountService->applyMultipleCouponsToProducts(
                $coupons->all(),
                $user,
                collect($cart),
                $hasOrders
            );
            if ($couponResult['success']) {
                foreach ($couponResult['modified_products'] ?? [] as $modProduct) {
                    $key = $modProduct['product_id'] . '_' . ($modProduct['variation_id'] ?? 'null');
                    $modifiedProductsLookup[$key] = $modProduct;
                }
            }
        }

        // Build mock Order and OrderProducts
        $order = new Order([
            'id' => 0,
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'total' => 0,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'observations' => '[TEST] Mock order - not transmitted',
            'created_at' => now(),
        ]);
        $order->id = 0;
        $order->setRelation('zone', $zone);
        $order->setRelation('user', $user);

        $orderProducts = collect();
        $totalOrder = 0;

        foreach ($cart as $row) {
            $product = Product::with(['brand.vendor', 'items'])->find($row['product_id']);
            if (!$product) continue;

            $lookupKey = $row['product_id'] . '_' . ($row['variation_id'] ?? 'null');
            $basePrice = $product->price;
            $variation = $row['variation_id']
                ? $product->items->where('id', $row['variation_id'])->first()
                : null;
            if ($variation && $variation->pivot) {
                $basePrice = $variation->pivot->price;
            }

            $lineDiscountPercent = 0;
            $orderDiscountType = 'percentage';
            $flatDiscountAmount = 0;
            $unitPrice = 0;

            if (isset($modifiedProductsLookup[$lookupKey])) {
                $modProduct = $modifiedProductsLookup[$lookupKey];
                $discountType = $modProduct['applied_discount_type'] ?? 'percentage';
                $basePrice = $modProduct['base_price'];
                if ($discountType === 'fixed_amount') {
                    if ($product->calculate_package_price) {
                        $unitPrice = $basePrice * ($product->package_quantity ?? 1);
                    } else {
                        $unitPrice = $basePrice;
                    }
                    $lineDiscountPercent = 0;
                    $orderDiscountType = 'fixed_amount';
                    $flatDiscountAmount = $modProduct['unit_price_reduction'] ?? 0;
                } else {
                    if ($product->calculate_package_price) {
                        $unitPrice = $basePrice * ($product->package_quantity ?? 1);
                    } else {
                        $unitPrice = $basePrice;
                    }
                    $lineDiscountPercent = (int) ($modProduct['applied_discount_percentage'] ?? 0);
                }
            } else {
                $vendorId = $product->brand && $product->brand->vendor ? $product->brand->vendor->id : null;
                $vendorTotal = $vendorId ? 0 : null;
                $lineFinal = $product->getFinalPriceForUser($hasOrders, $vendorTotal);
                $lineDiscountPercent = max(0, min(100, (int) ($lineFinal['discount'] ?? 0)));
                if ($product->calculate_package_price) {
                    $unitPrice = $lineFinal['originalPrice'] ?? ($basePrice * ($product->package_quantity ?? 1));
                } else {
                    $unitPrice = $lineFinal['price'] ?? $basePrice;
                }
                if ($variation && isset($variation->pivot->price)) {
                    if ($product->calculate_package_price) {
                        $unitPrice = $variation->pivot->price * ($product->package_quantity ?? 1);
                    } else {
                        $unitPrice = $variation->pivot->price;
                    }
                }
            }

            $packageQty = $product->package_quantity ?? 1;
            $lineTotal = $unitPrice * $row['quantity'];

            $op = new OrderProduct([
                'order_id' => 0,
                'product_id' => $product->id,
                'quantity' => $row['quantity'],
                'price' => $unitPrice,
                'percentage' => $lineDiscountPercent,
                'discount_type' => $orderDiscountType,
                'flat_discount_amount' => $flatDiscountAmount,
                'variation_item_id' => $row['variation_id'] ?? null,
                'package_quantity' => $packageQty,
            ]);
            $op->setRelation('product', $product);
            $orderProducts->push($op);
            $totalOrder += $lineTotal;
        }

        $order->total = $totalOrder;
        $order->setRelation('products', $orderProducts);

        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $orderProducts) ?? '<!-- No zone -->';

        $productSummary = $orderProducts->map(fn ($op) => [
            'product_id' => $op->product_id,
            'name' => $op->product?->name ?? 'N/A',
            'quantity' => $op->quantity,
            'price' => $op->price,
            'percentage' => $op->percentage,
            'discount_type' => $op->discount_type ?? 'percentage',
            'flat_discount_amount' => $op->flat_discount_amount ?? 0,
        ]);

        return view('admin.coupon-tests.preview-xml', [
            'order' => $order,
            'xml' => $xml,
            'productSummary' => $productSummary,
            'isMockTest' => true,
            'couponResult' => $couponResult,
        ]);
    }

}
