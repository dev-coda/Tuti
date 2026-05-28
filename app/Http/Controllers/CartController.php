<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Services\BonificationCheckoutService;
use App\Services\CouponService;
use App\Services\Shipping\CoordinadoraQuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Validate and clean cart data
     * Removes any malformed items from the cart
     */
    private function validateCart($cart)
    {
        if (! is_array($cart)) {
            Log::error('Cart is not an array', [
                'cart_type' => gettype($cart),
                'user_id' => auth()->id(),
            ]);

            return [];
        }

        $validCart = [];
        $invalidCount = 0;
        $invalidReasons = [];

        foreach ($cart as $key => $item) {
            // Check if item has required keys
            if (! is_array($item) || ! isset($item['product_id']) || ! isset($item['quantity'])) {
                Log::warning('Invalid cart item detected: missing required fields', [
                    'key' => $key,
                    'item' => $item,
                    'user_id' => auth()->id(),
                    'has_product_id' => isset($item['product_id']) ?? false,
                    'has_quantity' => isset($item['quantity']) ?? false,
                ]);
                $invalidCount++;
                $invalidReasons[] = 'Producto con datos incompletos';

                continue;
            }

            // Validate product_id is numeric
            if (! is_numeric($item['product_id']) || $item['product_id'] <= 0) {
                Log::warning('Invalid product_id in cart item', [
                    'key' => $key,
                    'product_id' => $item['product_id'],
                    'user_id' => auth()->id(),
                ]);
                $invalidCount++;
                $invalidReasons[] = 'Producto con ID inválido';

                continue;
            }

            // Validate quantity is numeric and positive
            if (! is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                Log::warning('Invalid quantity in cart item', [
                    'key' => $key,
                    'quantity' => $item['quantity'],
                    'product_id' => $item['product_id'],
                    'user_id' => auth()->id(),
                ]);
                $invalidCount++;
                $invalidReasons[] = 'Producto con cantidad inválida';

                continue;
            }

            $validCart[] = $item;
        }

        // Update session if we removed invalid items
        if ($invalidCount > 0) {
            if (! empty($validCart)) {
                session()->put('cart', $validCart);
                Log::info('Cart cleaned of invalid items', [
                    'original_count' => count($cart),
                    'valid_count' => count($validCart),
                    'invalid_count' => $invalidCount,
                    'user_id' => auth()->id(),
                ]);

                // Flash a warning message for the user
                session()->flash('warning', "Se removieron {$invalidCount} producto(s) inválido(s) de tu carrito.");
            } else {
                // All items were invalid
                Log::warning('All cart items were invalid', [
                    'original_count' => count($cart),
                    'invalid_count' => $invalidCount,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        return $validCart;
    }

    public function cart()
    {

        // session()->forget('cart');
        // back();

        $cart = session()->get('cart');

        if (! $cart) {
            return redirect()->route('home');
        }

        // Validate and clean cart data
        $cart = $this->validateCart($cart);

        if (empty($cart)) {
            session()->forget('cart');

            return redirect()->route('home')->with('error', 'Tu carrito estaba vacío o contenía productos inválidos.');
        }

        $user = auth()->user()->load('zones');
        $zoneOptions = $user->zones;

        $set_user = false;
        $client = null;
        if ($user->hasAnyRole(['seller', 'supervisor'])) {
            $user_id = session()->get('user_id');
            $set_user = true;
            if ($user_id) {
                $client = User::with('zones')->find($user_id);
                $zoneOptions = $client->zones;
                $set_user = false;
            }
        }

        // When inventory is enabled, hide addresses whose logistics zone has no bodega mapping.
        // This prevents users from selecting an address that will fail at checkout.
        $inventoryEnabledRaw = Setting::getByKey('inventory_enabled');
        $isInventoryEnabledForFilter = ($inventoryEnabledRaw === '1' || $inventoryEnabledRaw === 1 || $inventoryEnabledRaw === true);

        if ($isInventoryEnabledForFilter && $zoneOptions->count() > 0) {
            $zoneCodes = $zoneOptions->pluck('zone')->filter()->unique()->values();
            $bodegaByZone = $zoneCodes->mapWithKeys(fn ($code) => [$code => ZoneWarehouse::getBodegaForZone($code)]);

            $filtered = $zoneOptions->filter(fn ($z) => $z->zone && ($bodegaByZone[$z->zone] ?? null) !== null);

            if ($filtered->isEmpty()) {
                \Log::warning('All zones filtered out — no bodega mapping for any address', [
                    'user_id' => ($client ?? $user)->id,
                    'zone_count' => $zoneOptions->count(),
                    'zones' => $zoneOptions->map(fn ($z) => ['id' => $z->id, 'zone' => $z->zone, 'code' => $z->code])->toArray(),
                ]);
            } else {
                $zoneOptions = $filtered->values();
            }
        }

        $products = [];
        $total_cart = 0;

        // Check if the target user has orders - needed for discount calculations
        $targetUser = $client ?? $user;
        $has_orders = Order::with('user')
            ->withCount('products')
            ->whereBelongsTo($targetUser)
            ->exists();

        // Check for applied coupons and calculate proper discounts FIRST
        $couponDiscount = 0;
        $couponMessage = null;
        $couponResult = null;

        // Load coupons from multi-coupon session (primary) or single-coupon session (backward compat)
        $validCoupons = $this->loadValidCouponsFromSession();

        if (! empty($validCoupons)) {
            $couponDiscountService = app(\App\Services\CouponDiscountService::class);
            $couponResult = $couponDiscountService->applyMultipleCouponsToProducts(
                $validCoupons,
                $targetUser,
                collect($cart),
                $has_orders
            );

            if ($couponResult['success']) {
                $couponDiscount = $couponResult['total_coupon_discount'];
                $codes = collect($validCoupons)->pluck('code')->implode(', ');
                $couponMessage = count($validCoupons) === 1
                    ? "Cupón '{$codes}' aplicado"
                    : "Cupones aplicados: {$codes}";
            } else {
                // All coupons failed
                $this->clearAllCouponSessions();
            }
        }

        // Create products array with potential coupon-modified pricing
        $modifiedProductsLookup = [];
        if ($couponResult && $couponResult['success']) {
            foreach ($couponResult['modified_products'] as $modProduct) {
                $key = $modProduct['product_id'].'_'.($modProduct['variation_id'] ?? 'null');
                $modifiedProductsLookup[$key] = $modProduct;
            }
        }

        foreach ($cart as $item) {
            $product = Product::with('brand.vendor', 'variation', 'tax', 'bonifications')->find($item['product_id']);

            // Skip if product not found (might have been deleted)
            if (! $product) {
                Log::warning('Product not found in cart', [
                    'product_id' => $item['product_id'],
                    'user_id' => auth()->id(),
                ]);

                continue;
            }

            // Skip if product has no brand or vendor
            if (! $product->brand || ! $product->brand->vendor) {
                Log::warning('Product missing brand or vendor in cart', [
                    'product_id' => $product->id,
                    'has_brand' => ! is_null($product->brand),
                    'user_id' => auth()->id(),
                ]);

                continue;
            }

            $product->item = $product->items->where('id', $item['variation_id'])->first();
            $product->quantity = $item['quantity'];
            $product->vendor_id = $product->brand->vendor->id;

            // Check if this product was modified by coupon
            $lookupKey = $item['product_id'].'_'.($item['variation_id'] ?? 'null');
            if (isset($modifiedProductsLookup[$lookupKey])) {
                // Use coupon-modified pricing
                $finalPrice = $product->getFinalPriceWithCoupon($has_orders, $modifiedProductsLookup[$lookupKey]);
            } else {
                // Use regular pricing without vendor discount (pass 0 to prevent vendor discount)
                // This will be recalculated later with proper vendor totals
                $variationItemId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
                $finalPrice = $product->getFinalPriceForUser($has_orders, 0, $variationItemId);
            }

            $product->calculatedFinalPrice = $finalPrice;
            $products[] = $product;
        }
        $products = collect($products);

        // Calculate total cart value
        $total_cart = $products->sum(function ($product) {
            return $product->quantity * $product->calculatedFinalPrice['price'];
        });

        //compra minima por vendor y calcular totales para descuentos
        $byVendors = collect($products)->groupBy('vendor_id');
        $alertVendors = [];
        $vendorDiscountAlerts = [];
        $vendorTotals = [];

        foreach ($byVendors as $key => $vendor) {
            $total = $vendor->sum(function ($product) {
                return $product->quantity * $product->calculatedFinalPrice['price'];
            });

            $v = Vendor::find($key);
            $vendorTotals[$key] = $total;

            // Check minimum purchase requirement
            if ($total < $v->minimum_purchase) {
                $v->current = $total;
                $alertVendors[] = $v;
            }

            // Check vendor discount minimum requirement (only if vendor has discount > 0 and minimum_discount_amount > 0)
            if ($v->discount > 0 && $v->minimum_discount_amount > 0 && $total < $v->minimum_discount_amount) {
                $vendorDiscountAlerts[] = [
                    'vendor' => $v,
                    'current_total' => $total,
                    'needed_amount' => $v->minimum_discount_amount - $total,
                    'discount_percentage' => $v->discount,
                ];
            }
        }

        // Recalculate products with vendor totals for proper discount application
        $products = collect($products)->map(function ($product) use ($vendorTotals, $has_orders, $modifiedProductsLookup) {
            $vendorTotal = $vendorTotals[$product->vendor_id] ?? 0;

            $lookupKey = $product->id.'_'.($product->item?->id ?? 'null');
            if (isset($modifiedProductsLookup[$lookupKey])) {
                return $product;
            }

            $variationItemId = $product->item?->id;
            $finalPrice = $product->getFinalPriceForUser($has_orders, $vendorTotal, $variationItemId);
            $product->calculatedFinalPrice = $finalPrice;

            return $product;
        });

        // Recalculate total cart value after vendor discount adjustments
        $total_cart = $products->sum(function ($product) {
            return $product->quantity * $product->calculatedFinalPrice['price'];
        });

        $min_amount = Setting::where('key', 'min_amount')->value('value');
        $alertTotal = [];

        if ($total_cart < $min_amount) {
            $alertTotal[] = true;
        }

        $targetUser = $client ?? $user;

        // Check if first order discount is enabled
        $firstOrderDiscountEnabled = config('app.first_order_discount_enabled', true);

        // Only check for existing orders if the feature is enabled
        $has_orders = $firstOrderDiscountEnabled
            ? Order::with('user')
                ->withCount('products')
                ->whereBelongsTo($targetUser)
                ->exists()
            : false; // If disabled, treat as if user has no orders (always apply discount)

        // Build view-friendly coupon data from the multi-coupon session
        $appliedCoupon = null;
        $appliedCouponsForView = session()->get('applied_coupons', []);
        if (! empty($appliedCouponsForView) && $couponDiscount > 0) {
            // Build legacy-compatible $appliedCoupon for Blade
            $codes = collect($appliedCouponsForView)->pluck('coupon_code')->implode(', ');
            $appliedCoupon = [
                'coupon_code' => $codes,
                'discount_amount' => $couponDiscount,
                'coupons' => $appliedCouponsForView, // Full list for multi-coupon views
            ];
        }

        // Get enabled shipping methods (hide Envío 48h / Coordinadora unless explicitly enabled)
        $shippingMethods = \App\Models\ShippingMethod::getEnabled();
        if (! Setting::isExpress48hEnabled()) {
            $shippingMethods = $shippingMethods->filter(
                fn ($m) => $m->code !== Order::DELIVERY_METHOD_EXPRESS
            )->values();
        }

        $cartRetentions = app(\App\Services\CartRetentionService::class)->calculateForCart(
            $targetUser->tax_group ?? null,
            $products,
            0.0
        );

        $bonificationPreview = $this->buildCartBonificationPreview($cart);

        $context = compact('products', 'alertVendors', 'vendorDiscountAlerts', 'zoneOptions', 'set_user', 'client', 'alertTotal', 'min_amount', 'total_cart', 'has_orders', 'appliedCoupon', 'couponDiscount', 'couponMessage', 'shippingMethods', 'cartRetentions', 'bonificationPreview');

        return view('pages.cart', $context);
    }

    //TODO crear plugin de agregar al carrito
    public function add(Request $request, Product $product)
    {
        // Check vacation mode (active based on date range)
        $vacationInfo = Setting::getVacationModeInfo();

        if ($vacationInfo['active']) {
            $message = $vacationInfo['message'] ?? 'Tuti está de vacaciones. Te esperamos pronto. ¡Gracias!';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->back()->with('error', $message);
        }

        $request->validate([
            'variation_id' => 'nullable|numeric',
            'quantity' => 'required|numeric',
        ]);

        // Get user if authenticated (optional for cart operations)
        $user = auth()->user();

        // Enforce safety stock only when inventory management is enabled AND user is authenticated
        // (We need user's zone to determine the bodega for inventory check)
        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $isInventoryEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        if ($isInventoryEnabled && $product->isInventoryManaged() && $user) {
            // Determine user bodega from zone mapping
            $zone = $user->zones()->orderBy('id')->first();
            // Use zone field only (actual zone number like "933")
            // Note: code field contains CustRuteroID and should NOT be used for zone determination
            $zoneCode = $zone?->zone ?? $user->zone;
            $bodega = ZoneWarehouse::getBodegaForZone($zoneCode);

            $variationItemId = $request->variation_id ? (int) $request->variation_id : null;
            $safety = $product->getEffectiveSafetyStock();
            $inventory = $product->inventoryForBodega($bodega, $variationItemId);
            $available = (int) ($inventory?->available ?? 0);

            if ($available <= $safety) {
                \Log::info('Product blocked due to safety stock', [
                    'product_id' => $product->id,
                    'variation_inventory_row_id' => $inventory?->id,
                    'product_name' => $product->name,
                    'has_variation' => ! is_null($product->variation_id),
                    'variation_id_selected' => $request->variation_id,
                    'available' => $available,
                    'safety' => $safety,
                    'bodega' => $bodega,
                ]);

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este producto no está disponible por debajo del stock de seguridad.',
                    ], 400);
                }

                return back()->with('error', 'Este producto no está disponible por debajo del stock de seguridad.');
            }
        }

        $product_id = $product->id;
        $variation_id = $request->variation_id;

        $cart = session()->get('cart');

        if (! $cart) {
            $cart[] = [
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'variation_id' => $request->variation_id,
            ];
            session()->put('cart', $cart);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Producto agregado al carrito exitosamente!',
                ]);
            }

            return redirect()->back()
                ->with('success', 'Producto agregado al carrito exitosamente!')
                ->with('cart_updated', true);
        }

        $found_index = null;

        foreach ($cart as $index => $row) {
            if ($row['product_id'] == $product_id && $row['variation_id'] == $variation_id) {
                $found_index = $index;
                break;
            }
        }

        if ($found_index === null) {
            $cart[] = [
                'product_id' => $product_id,
                'quantity' => $request->quantity,
                'variation_id' => $request->variation_id,
            ];
            session()->put('cart', $cart);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Producto agregado al carrito exitosamente!',
                ]);
            }

            return redirect()->back()
                ->with('success', 'Producto agregado al carrito exitosamente!')
                ->with('cart_updated', true);
        }

        $cart[$found_index]['quantity'] = $request->quantity;

        session()->put('cart', $cart);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito exitosamente!',
            ]);
        }

        return redirect()->back()
            ->with('success', 'Producto agregado al carrito exitosamente!')
            ->with('cart_updated', true);
    }

    public function remove(Request $request, $key)
    {

        $cart = session()->get('cart');

        if (isset($cart[$key])) {
            unset($cart[$key]);
            $cart = array_values($cart);

            session()->put('cart', $cart);
        }

        return redirect()->back()
            ->with('success', 'Producto eliminado del carrito exitosamente!')
            ->with('cart_updated', true);
    }

    public function update(Request $request)
    {
        $cart = session()->get('cart', []);
        $couponRemoved = false;
        $couponRemovedMessage = null;

        // Handle single item AJAX update
        if ($request->expectsJson() || $request->ajax()) {
            $cartKey = (int) $request->input('cart_key');
            $quantity = (int) $request->input('quantity');

            if (! isset($cart[$cartKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in cart',
                    'debug' => [
                        'cart_key' => $cartKey,
                        'cart_keys' => array_keys($cart),
                    ],
                ], 404);
            }

            if ($quantity < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cantidad debe ser al menos 1',
                ], 400);
            }

            // Get the product to check step
            $product = Product::find($cart[$cartKey]['product_id']);
            if ($product && $product->step > 1) {
                // Round to nearest step
                $quantity = max($product->step, round($quantity / $product->step) * $product->step);
            }

            $cart[$cartKey]['quantity'] = $quantity;
            session()->put('cart', $cart);

            // Revalidate applied coupon after quantity changes
            // Revalidate applied coupons after quantity change
            $validCoupons = $this->loadValidCouponsFromSession();
            if (! empty($validCoupons)) {
                $user = auth()->user();
                $targetUser = $user;
                if ($user && $user->hasAnyRole(['seller', 'supervisor'])) {
                    $clientId = session()->get('user_id');
                    $targetUser = $clientId ? User::find($clientId) : $user;
                }

                if ($targetUser) {
                    $has_orders = Order::with('user')
                        ->withCount('products')
                        ->whereBelongsTo($targetUser)
                        ->exists();

                    $couponDiscountService = app(\App\Services\CouponDiscountService::class);
                    $couponResult = $couponDiscountService->applyMultipleCouponsToProducts(
                        $validCoupons,
                        $targetUser,
                        collect($cart),
                        $has_orders
                    );

                    $totalCouponDiscount = (float) ($couponResult['total_coupon_discount'] ?? 0);
                    if (! $couponResult['success'] || $totalCouponDiscount <= 0) {
                        $this->clearAllCouponSessions();
                        $couponRemoved = true;
                        $couponRemovedMessage = 'Los cupones ya no aplican a tu carrito y fueron removidos.';
                    }
                }
            }

            // Calculate new totals
            $subtotal = 0;
            $discount = 0;

            foreach ($cart as $item) {
                $prod = Product::find($item['product_id']);
                if ($prod) {
                    $itemSubtotal = $prod->price * $item['quantity'];
                    $subtotal += $itemSubtotal;

                    // Calculate discount if applicable
                    if ($prod->discount_percentage > 0) {
                        $discount += $itemSubtotal * ($prod->discount_percentage / 100);
                    }
                }
            }

            // Check for coupon discount from session
            $couponDiscount = 0;
            if (! $couponRemoved) {
                $couponDiscount = (float) session()->get('total_coupon_discount', 0);
            }

            $total = $subtotal - $discount - $couponDiscount;

            return response()->json([
                'success' => true,
                'message' => 'Cantidad actualizada',
                'quantity' => $quantity,
                'subtotal' => number_format($subtotal, 0, ',', '.'),
                'discount' => number_format($discount, 0, ',', '.'),
                'coupon_discount' => number_format($couponDiscount, 0, ',', '.'),
                'total' => number_format($total, 0, ',', '.'),
                'coupon_removed' => $couponRemoved,
                'coupon_removed_message' => $couponRemovedMessage,
            ]);
        }

        // Handle batch update (original behavior)
        $items = $request->items;

        foreach ($items as $key => $item) {
            $cart[$key]['quantity'] = $item;
        }

        session()->put('cart', $cart);
        if ($couponRemoved) {
            session()->flash('coupon_removed_message', $couponRemovedMessage ?? 'El cupón fue removido del carrito.');
        }

        return redirect()->back()
            ->with('success', 'Carrito actualizado exitosamente!')
            ->with('cart_updated', true);
    }

    public function processOrder(Request $request)
    {
        // Check vacation mode (active based on date range)
        $vacationInfo = Setting::getVacationModeInfo();

        if ($vacationInfo['active']) {
            $message = $vacationInfo['message'] ?? 'Tuti está de vacaciones. Te esperamos pronto. ¡Gracias!';

            return redirect()->route('cart')->with('error', $message);
        }

        //   dd($request->all());
        $cart = session()->get('cart');

        if (! $cart || empty($cart)) {
            return redirect()->route('home')->with('error', 'El carrito está vacío');
        }

        // Validate and clean cart data before processing
        $cart = $this->validateCart($cart);

        if (empty($cart)) {
            session()->forget('cart');

            return redirect()->route('home')->with('error', 'Tu carrito contenía productos inválidos y ha sido limpiado.');
        }

        $observations = $request->observations;

        $total = 0;
        $discount = 0;

        $user = auth()->user();

        $seller_id = null;
        $user_id = $user->id;
        $actingUser = $user;

        if ($user->hasAnyRole(['seller', 'supervisor'])) {
            $seller_id = $user->id;
            $user_id = session()->get('user_id');
            $actingUser = User::find($user_id) ?: $user;
        }

        $isDraftClientCheckout = $actingUser && ($actingUser->isPendingClient() || $actingUser->isProspectClient());

        // Sync rutero data before processing order to ensure we have current data
        // This handles cases where zone data might have changed in external service
        // Pending clients (24h rutero delay) skip getRuteros at checkout.
        if ($actingUser && $actingUser->document && ! $isDraftClientCheckout) {
            try {
                \Log::info('Syncing rutero data before order processing', [
                    'user_id' => $actingUser->id,
                    'document' => $actingUser->document,
                ]);
                UserRepository::syncUserRuteroData($actingUser);
                // Reload zones after sync
                $actingUser->refresh();
                $actingUser->load('zones');
            } catch (\Throwable $th) {
                \Log::warning('Failed to sync rutero data before order processing', [
                    'user_id' => $actingUser->id,
                    'error' => $th->getMessage(),
                ]);
                // Continue with existing data if sync fails
            }
        }

        // After syncing rutero data, resolve the selected zone. Sync may replace zone rows (new IDs)
        // while the form still posts the old id — match by stable Dynamics CustRuteroID (zones.code).
        $zoneResolution = $this->resolveCheckoutZone($actingUser, $request);

        if (! $zoneResolution['ok']) {
            return back()->with('error', $zoneResolution['message'] ?? 'No hay dirección de entrega disponible. Actualiza tus datos de rutero e intenta de nuevo.');
        }

        $zone = $zoneResolution['zone'];
        $zoneId = $zone->id;

        // Inventory validation based on zone/bodega
        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $isInventoryEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        $enforceInventoryChecks = $isInventoryEnabled && ! $isDraftClientCheckout;

        // Bodega mapping uses logistics zone number (zones.zone, e.g. "933"), not CustRuteroID (zones.code).
        // zones.code is the stable key for checkout sucursal selection after rutero sync.
        $zoneCode = $zone?->zone ?? null;

        \Log::info('Zone determination after rutero sync', [
            'user_id' => $actingUser->id,
            'zone_id' => $zoneId,
            'zone_code' => $zoneCode,
            'zone_object' => $zone ? [
                'id' => $zone->id,
                'code' => $zone->code,
                'zone' => $zone->zone,
                'route' => $zone->route,
            ] : null,
            'all_zones' => $actingUser->zones->map(function ($z) {
                return ['id' => $z->id, 'code' => $z->code, 'zone' => $z->zone];
            })->toArray(),
        ]);

        $bodega = $enforceInventoryChecks ? ZoneWarehouse::getBodegaForZone($zoneCode) : null;

        if ($enforceInventoryChecks && ! $bodega) {
            \Log::warning('Bodega determination failed for selected zone', [
                'user_id' => $user->id,
                'acting_user_id' => $actingUser->id,
                'user_email' => $user->email,
                'zone_id' => $zoneId,
                'zone_code' => $zoneCode,
                'zone_object' => $zone ? [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'zone' => $zone->zone,
                    'route' => $zone->route,
                ] : null,
                'is_seller' => $user->hasAnyRole(['seller', 'supervisor']),
                'session_user_id' => session()->get('user_id'),
            ]);

            $allMappings = ZoneWarehouse::all()->map(function ($zw) {
                return ['zone_code' => $zw->zone_code, 'bodega_code' => $zw->bodega_code];
            })->toArray();
            $configMappings = config('zone_warehouses.mappings', []);

            \Log::error('Bodega determination failed - no mapping for selected address', [
                'user_id' => $user->id,
                'acting_user_id' => $actingUser->id,
                'user_email' => $user->email,
                'zone_id' => $zoneId,
                'zone_code' => $zoneCode,
                'acting_user_zones' => $actingUser ? $actingUser->zones->map(function ($z) {
                    return ['id' => $z->id, 'code' => $z->code, 'zone' => $z->zone];
                })->toArray() : null,
                'db_mappings_count' => count($allMappings),
                'db_mappings' => $allMappings,
                'config_mappings_count' => count($configMappings),
                'config_mappings_keys' => array_keys($configMappings),
            ]);

            return back()->with(
                'error',
                'No hay cobertura de inventario para la dirección seleccionada. Elige otra sucursal o contacta a soporte.'
            );
        }

        // Get delivery method from request, default to 'tronex'
        $delivery_method = $request->input('delivery_method', 'tronex');
        if ($delivery_method === Order::DELIVERY_METHOD_EXPRESS && ! Setting::isExpress48hEnabled()) {
            Log::info('Express 48h / Coordinadora requested while disabled; using Tronex', [
                'user_id' => $user_id,
                'zone_id' => $zone?->id,
            ]);
            $delivery_method = Order::DELIVERY_METHOD_TRONEX;
        }
        $shippingProvider = Order::SHIPPING_PROVIDER_TRONEX;
        $shippingQuoteAmount = null;

        if ($delivery_method === Order::DELIVERY_METHOD_EXPRESS && $zone?->usesCoordinadoraFor48h()) {
            $shippingProvider = Order::SHIPPING_PROVIDER_COORDINADORA;

            try {
                $quote = app(CoordinadoraQuoteService::class)->quoteFromCart(collect($cart), $zone);
                $shippingQuoteAmount = (float) ($quote['shipping_cost'] ?? 0);
            } catch (\Throwable $e) {
                Log::warning('Coordinadora quote failed during checkout', [
                    'zone_id' => $zone?->id,
                    'user_id' => $user_id,
                    'message' => $e->getMessage(),
                ]);

                return back()->with('error', 'No pudimos cotizar el envío Coordinadora para esta dirección. Verifica el código postal o intenta de nuevo.');
            }
        }

        // Calculate delivery date based on selected method and zone
        $delivery_date = OrderRepository::getDeliveryDateByMethod($delivery_method, $zone);

        // For Tronex orders, check if order should be delayed
        $scheduledTransmissionDate = null;
        $orderStatus = Order::STATUS_PENDING;
        $sellerVisitDate = null;

        // Check if force delivery date is enabled (emergency override - bypasses waiting)
        $forceDeliveryDate = \App\Models\Setting::getByKey('force_delivery_date_enabled') == '1';

        // Get seller visit date for Tronex orders (for logging and delay calculation)
        if ($delivery_method === Order::DELIVERY_METHOD_TRONEX && $zone) {
            $sellerVisitDate = OrderRepository::getTronexSellerVisitDate($zone);
        }

        // Only set waiting status if force delivery is NOT active
        if ($delivery_method === Order::DELIVERY_METHOD_TRONEX && $zone && ! $forceDeliveryDate && $sellerVisitDate) {
            $today = now();
            $isTodaySellerVisitDay = $today->format('Y-m-d') === $sellerVisitDate->format('Y-m-d');

            if (! $isTodaySellerVisitDay) {
                // Order should be delayed until seller visit day
                $orderStatus = Order::STATUS_WAITING;
                $scheduledTransmissionDate = $sellerVisitDate->format('Y-m-d');

                Log::info('Order will be delayed until seller visit day', [
                    'seller_visit_date' => $scheduledTransmissionDate,
                    'today' => $today->format('Y-m-d'),
                    'zone_id' => $zone->id,
                    'route' => $zone->route,
                ]);
            }
        }

        if ($isDraftClientCheckout) {
            $orderStatus = Order::STATUS_DRAFT;
            $scheduledTransmissionDate = null;

            Log::info('Order created as draft for non-cliente user (rutero not yet available)', [
                'acting_user_id' => $actingUser->id,
                'document' => $actingUser->document,
                'client_status' => $actingUser->client_status,
            ]);
        }

        // Log if force delivery date bypassed the waiting status
        if ($forceDeliveryDate && $delivery_method === Order::DELIVERY_METHOD_TRONEX) {
            Log::warning('Force Delivery Date ACTIVE - Order will be processed immediately instead of waiting', [
                'force_delivery_date_enabled' => true,
                'would_have_waited_until' => $sellerVisitDate ? $sellerVisitDate->format('Y-m-d') : 'N/A',
                'zone_id' => $zone ? $zone->id : null,
                'route' => $zone ? $zone->route : null,
            ]);
        }

        // Pre-check inventory and safety stock for each item (only when enabled)
        // For products with variations, inventory is checked at the parent product level
        // All variation items of a product share the same inventory pool
        if ($enforceInventoryChecks) {
            foreach ($cart as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                if (! $product) {
                    return back()->with('error', 'Producto no encontrado en el carrito.');
                }
                // Skip checks if product opted out
                if (! $product->isInventoryManaged()) {
                    continue;
                }
                $variationItemId = isset($cartItem['variation_id']) ? (int) $cartItem['variation_id'] : null;
                $inventory = $product->inventoryForBodega($bodega, $variationItemId);
                $available = (int) ($inventory?->available ?? 0);
                $reserved = (int) ($inventory?->reserved ?? 0);
                $safety = (int) $product->getEffectiveSafetyStock();

                // Get global minimum inventory setting (default 5 if not configured)
                $globalMinInventory = (int) (\App\Models\Setting::getByKey('global_minimum_inventory') ?? 5);

                // Use product safety stock if configured (> 0), otherwise use global minimum
                // This gives precedence to product-level settings while maintaining a global safety net
                $effectiveMinimum = ($safety > 0) ? $safety : $globalMinInventory;

                if ($available <= $effectiveMinimum) {
                    $reason = ($safety > 0) ? 'product safety stock' : 'global minimum inventory';
                    \Log::warning('Order blocked: below minimum threshold', [
                        'product_id' => $product->id,
                        'variation_inventory_row_id' => $inventory?->id,
                        'product_name' => $product->name,
                        'has_variation' => ! is_null($product->variation_id),
                        'variation_item_selected' => $cartItem['variation_id'] ?? null,
                        'available' => $available,
                        'product_safety_stock' => $safety,
                        'global_minimum' => $globalMinInventory,
                        'effective_minimum' => $effectiveMinimum,
                        'reason' => $reason,
                        'bodega' => $bodega,
                    ]);

                    $errorMsg = ($safety > 0)
                        ? "{$product->name} está por debajo del stock de seguridad."
                        : "El producto {$product->name} tiene inventario insuficiente en su zona (mínimo: {$globalMinInventory} unidades).";

                    return back()->with('error', $errorMsg);
                }
                // Check against available (disponible) only - it already accounts for reservations!
                // disponible = físico - reservado (calculated in DB)
                // Do NOT subtract reserved again here - that would be double-counting
                if ($cartItem['quantity'] > $available) {
                    \Log::warning('Order blocked: quantity exceeds available (disponible)', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'requested' => $cartItem['quantity'],
                        'disponible' => $available,
                        'reservado' => $reserved,
                        'bodega' => $bodega,
                    ]);

                    return back()->with('error', "La cantidad solicitada de {$product->name} excede el inventario disponible en su zona.");
                }
            }
        }

        // Check for duplicate orders in the last 3 minutes
        $threeMinutesAgo = now()->subMinutes(3);

        // First, get potential duplicate orders
        $potentialDuplicates = Order::where('user_id', $user_id)
            ->where('zone_id', $zoneId)
            ->where('created_at', '>=', $threeMinutesAgo)
            ->with('products')
            ->get();

        $duplicateOrder = null;

        // Check each potential duplicate
        foreach ($potentialDuplicates as $order) {

            if ($order->products->count() !== count($cart)) {
                continue;
            }

            // Check if all products match
            $isDuplicate = true;
            $orderProducts = $order->products->keyBy('product_id');

            foreach ($cart as $cartItem) {
                $productId = $cartItem['product_id'];
                $quantity = $cartItem['quantity'];
                $variationId = $cartItem['variation_id'] ?? null;

                if (
                    ! isset($orderProducts[$productId]) ||
                    $orderProducts[$productId]->quantity != $quantity ||
                    $orderProducts[$productId]->variation_item_id != $variationId
                ) {
                    $isDuplicate = false;
                    break;
                }
            }

            if ($isDuplicate) {
                $duplicateOrder = $order;
                break;
            }
        }

        if ($duplicateOrder) {
            // Clear cart and redirect to thank you page
            if (app()->environment('production')) {
                session()->forget('cart');
            }
            session()->forget('user_id');

            return redirect()->route('orders.thank-you', $duplicateOrder->id);
        }

        // Check if user has previous orders (only if first order discount is enabled)
        $firstOrderDiscountEnabled = config('app.first_order_discount_enabled', true);
        $has_orders = $firstOrderDiscountEnabled ? Order::where('user_id', $user_id)->exists() : false;

        // Check for applied coupons and calculate proper discounts
        $couponDiscount = 0;
        $couponResult = null;
        $validCoupons = $this->loadValidCouponsFromSession();
        $winningCoupons = []; // coupon_id => coupon_code for coupons that actually applied

        if (! empty($validCoupons)) {
            // Re-validate usage limits for ALL coupons before processing
            $orderUser = User::find($user_id);
            $couponsToApply = [];

            foreach ($validCoupons as $coupon) {
                $coupon->refresh(); // Force reload from database

                if (! $coupon->isValid()) {
                    \Log::warning('Coupon no longer valid during order processing', [
                        'coupon_id' => $coupon->id,
                        'coupon_code' => $coupon->code,
                        'user_id' => $user_id,
                    ]);

                    continue;
                }

                if ($coupon->hasUserExceededLimit($user_id)) {
                    \Log::warning('Coupon user limit exceeded during order processing', [
                        'coupon_id' => $coupon->id,
                        'coupon_code' => $coupon->code,
                        'user_id' => $user_id,
                    ]);
                    $this->clearAllCouponSessions();

                    return back()->with('error', "El cupón '{$coupon->code}' ha alcanzado el límite de uso permitido para tu cuenta. Por favor remuévelo del carrito e intenta nuevamente.");
                }

                $couponsToApply[] = $coupon;
            }

            if (! empty($couponsToApply)) {
                $couponDiscountService = app(\App\Services\CouponDiscountService::class);
                $couponResult = $couponDiscountService->applyMultipleCouponsToProducts(
                    $couponsToApply,
                    $orderUser,
                    collect($cart),
                    $has_orders
                );

                if ($couponResult['success']) {
                    $couponDiscount = $couponResult['total_coupon_discount'];
                    $winningCoupons = $couponResult['winning_coupons'] ?? [];

                    \Log::info('Multiple coupons applied during order processing', [
                        'user_id' => $user_id,
                        'coupons_submitted' => count($couponsToApply),
                        'winning_coupons' => $winningCoupons,
                        'total_coupon_discount' => $couponDiscount,
                    ]);
                } else {
                    \Log::warning('Coupon application failed during order processing', [
                        'user_id' => $user_id,
                        'coupons_count' => count($couponsToApply),
                        'reason' => $couponResult['message'] ?? 'Unknown',
                    ]);
                    $this->clearAllCouponSessions();
                    $couponResult = null;
                }
            }
        }

        // Check if bonifications block discounts BEFORE processing order
        // Rule: If ANY product qualifies for a bonification with allow_discounts=false, block ALL discounts
        $bonificationsBlockDiscounts = false;
        $productQuantitiesForBonificationCheck = [];

        // First, aggregate quantities by product_id (same as bonification logic)
        foreach ($cart as $row) {
            $productId = $row['product_id'];
            $tempProduct = Product::find($productId);
            if ($tempProduct) {
                if (! isset($productQuantitiesForBonificationCheck[$productId])) {
                    $productQuantitiesForBonificationCheck[$productId] = 0;
                }
                $packageQuantity = $tempProduct->package_quantity ?? 1;
                $productQuantitiesForBonificationCheck[$productId] += $row['quantity'] * $packageQuantity;
            }
        }

        // Check each product for bonifications that block discounts
        foreach ($cart as $row) {
            $productId = $row['product_id'];
            $product = Product::with('bonifications')->find($productId);

            if ($product && $product->bonifications->count() > 0) {
                $aggregatedIndividualItems = $productQuantitiesForBonificationCheck[$productId] ?? 0;

                foreach ($product->bonifications as $bonification) {
                    // Check if customer qualifies for this bonification
                    $bonification_quantity = floor($aggregatedIndividualItems / $bonification->buy * $bonification->get);

                    if ($bonification_quantity > 0) {
                        // Customer qualifies for this bonification
                        // If this bonification blocks discounts, set flag
                        if (! $bonification->allow_discounts) {
                            $bonificationsBlockDiscounts = true;
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }

        // If bonifications block discounts, clear all discount-related data
        if ($bonificationsBlockDiscounts) {
            // Clear coupon discount
            $couponDiscount = 0;
            $winningCoupons = [];
            $couponResult = null;
            $modifiedProductsLookup = [];
            $this->clearAllCouponSessions();
            Log::info('Bonifications block discounts - clearing all discounts', [
                'user_id' => $user_id,
                'cart_items' => count($cart),
            ]);
        }

        // Use database transaction to ensure atomicity
        DB::beginTransaction();

        try {
            // Use the zone_id determined after rutero sync (ensures current data)
            // Determine coupon data for order storage
            $orderCouponId = null;
            $orderCouponCode = null;
            if (! empty($winningCoupons)) {
                $winningIds = array_keys($winningCoupons);
                $orderCouponId = $winningIds[0]; // FK points to primary winning coupon
                $orderCouponCode = implode(',', array_values($winningCoupons)); // All winning codes
            }

            $order = Order::create([
                'user_id' => $user_id,
                'total' => $total,
                'discount' => $discount,
                'status_id' => $orderStatus,
                'zone_id' => $zoneId, // Use synced zone_id, not request zone_id
                'zone_snapshot' => [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'route' => $zone->route,
                    'zone' => $zone->zone,
                    'day' => $zone->day,
                    'address' => $zone->address,
                ],
                'seller_id' => $seller_id,
                'delivery_date' => $delivery_date,
                'delivery_method' => $delivery_method,
                'shipping_provider' => $shippingProvider,
                'shipping_quote_amount' => $shippingQuoteAmount,
                'observations' => $observations,
                'coupon_id' => $orderCouponId,
                'coupon_code' => $orderCouponCode,
                'coupon_discount' => $couponDiscount,
                'scheduled_transmission_date' => $scheduledTransmissionDate,
            ]);

            // Create a lookup for modified products from coupon discount service
            $modifiedProductsLookup = [];
            if ($couponResult && $couponResult['success']) {
                foreach ($couponResult['modified_products'] as $modProduct) {
                    $key = $modProduct['product_id'].'_'.($modProduct['variation_id'] ?? 'null');
                    $modifiedProductsLookup[$key] = $modProduct;
                }
            }

            // Calculate vendor totals for proper discount application
            // Important: Calculate totals WITHOUT vendor discounts first, then check if minimum is met
            $vendorTotals = [];
            $productsByVendor = [];
            foreach ($cart as $key => $row) {
                $tempProduct = Product::with('brand.vendor', 'bonifications')->find($row['product_id']);
                if ($tempProduct && $tempProduct->brand && $tempProduct->brand->vendor) {
                    $vendorId = $tempProduct->brand->vendor->id;

                    // Calculate price for this product
                    $lookupKey = $row['product_id'].'_'.($row['variation_id'] ?? 'null');
                    if (isset($modifiedProductsLookup[$lookupKey])) {
                        $productPrice = $modifiedProductsLookup[$lookupKey]['new_unit_price'];
                    } else {
                        // Pass 0 as vendor total to prevent vendor discount from being applied
                        // when calculating the vendor total (prevents circular logic)
                        $priceInfo = $tempProduct->getFinalPriceForUser($has_orders, 0);
                        $productPrice = $priceInfo['price'];
                    }

                    $productTotal = $productPrice * $row['quantity'];

                    if (! isset($vendorTotals[$vendorId])) {
                        $vendorTotals[$vendorId] = 0;
                    }
                    $vendorTotals[$vendorId] += $productTotal;
                }
            }

            // First pass: Group cart items by product_id for bonification aggregation
            // This ensures all variations (same product_id with different variation_id) count together for bonifications
            // Note: product_id in cart is always the parent product when variations are involved
            $productQuantities = [];
            foreach ($cart as $key => $row) {
                $productId = $row['product_id'];
                $tempProduct = Product::find($productId);
                if ($tempProduct) {
                    if (! isset($productQuantities[$productId])) {
                        $productQuantities[$productId] = 0;
                    }

                    // Aggregate quantities across all variations of the same product
                    // If same product appears multiple times with different variation_id, they all count together
                    $packageQuantity = $tempProduct->package_quantity ?? 1;
                    $productQuantities[$productId] += $row['quantity'] * $packageQuantity;
                }
                // Note: If product not found, it will be caught in the main loop below
            }

            $lastCartKeyByProductId = BonificationCheckoutService::lastCartKeyByProductId($cart);

            $retentionBaseArticulos = 0.0;
            $retentionIvaArticulos = 0.0;

            foreach ($cart as $key => $row) {
                $id = $row['product_id'];
                $p = Product::with('brand.vendor', 'bonifications', 'tax')->find($id);

                // Skip if product not found (might have been deleted)
                if (! $p) {
                    Log::warning('Product not found during order processing', [
                        'product_id' => $id,
                        'order_id' => $order->id ?? null,
                        'user_id' => $user_id ?? null,
                    ]);
                    DB::rollBack();

                    return back()->with('error', 'Uno de los productos en tu carrito ya no está disponible.');
                }

                $lookupKey = $id.'_'.($row['variation_id'] ?? 'null');

                // If bonifications block discounts, skip all discount logic
                $orderDiscountType = 'percentage'; // Default
                $flatDiscountAmount = 0;

                if ($bonificationsBlockDiscounts) {
                    // No discounts allowed - use base price only
                    // Price storage depends on calculate_package_price flag
                    $variation = $p->items->where('id', $row['variation_id'])->first();
                    $basePrice = $variation ? $variation->pivot->price : $p->price;

                    if ($p->calculate_package_price) {
                        // Store package price
                        $unitPrice = $basePrice * ($p->package_quantity ?? 1);
                    } else {
                        // Store per-unit price
                        $unitPrice = $basePrice;
                    }
                    $lineDiscountPercent = 0;
                } elseif (isset($modifiedProductsLookup[$lookupKey])) {
                    // Check if this product was modified by coupon discount service
                    $modProduct = $modifiedProductsLookup[$lookupKey];

                    // Use the discount percentage or modified price from coupon service
                    $discountType = $modProduct['applied_discount_type'] ?? 'percentage';

                    if ($discountType === 'fixed_amount') {
                        // For fixed amount discounts, use the new unit price
                        // Store the original price (adjusted for package), and track the flat discount separately
                        $basePrice = $modProduct['base_price'];
                        if ($p->calculate_package_price) {
                            $unitPrice = $basePrice * ($p->package_quantity ?? 1);
                        } else {
                            $unitPrice = $basePrice;
                        }
                        $lineDiscountPercent = 0; // No percentage for fixed amount
                        $orderDiscountType = 'fixed_amount';
                        // Calculate per-unit flat discount for XML
                        $flatDiscountAmount = (float) ($modProduct['fixed_discount_per_unit']
                            ?? $modProduct['unit_price_reduction']
                            ?? 0);
                    } else {
                        // For percentage discounts, use the applied percentage
                        $basePrice = $modProduct['base_price'];
                        if ($p->calculate_package_price) {
                            $unitPrice = $basePrice * ($p->package_quantity ?? 1);
                        } else {
                            $unitPrice = $basePrice;
                        }
                        $lineDiscountPercent = (int) round((float) ($modProduct['effective_discount_percentage']
                            ?? $modProduct['applied_discount_percentage']
                            ?? 0));
                    }
                } else {
                    // Use original product pricing logic with vendor total for proper discount
                    // Product model will check bonification allow_discounts internally
                    $vendorId = $p->brand && $p->brand->vendor ? $p->brand->vendor->id : null;
                    $vendorTotal = $vendorId && isset($vendorTotals[$vendorId]) ? $vendorTotals[$vendorId] : null;

                    $lineFinal = $p->getFinalPriceForUser($has_orders, $vendorTotal);
                    $lineDiscountPercent = (int) ($lineFinal['discount'] ?? 0);
                    // Clamp discount percentage to valid range
                    $lineDiscountPercent = max(0, min(100, $lineDiscountPercent));

                    // Price storage logic depends on calculate_package_price flag:
                    // - If TRUE: Store originalPrice (base * package_qty), SOAP will divide later
                    // - If FALSE: Store base price only, SOAP will NOT divide
                    if ($p->calculate_package_price) {
                        // Store package price: will be divided in SOAP
                        $unitPrice = $lineFinal['originalPrice'] ?? ($p->price * ($p->package_quantity ?? 1));
                    } else {
                        // Store per-unit price: will be used as-is in SOAP
                        $unitPrice = $p->price ?? 0;
                    }

                    // Get variation price if applicable
                    if (isset($row['variation_id']) && $row['variation_id']) {
                        $variation = $p->items->where('id', $row['variation_id'])->first();
                        if ($variation && isset($variation->pivot->price)) {
                            // Apply same logic for variations
                            if ($p->calculate_package_price) {
                                $unitPrice = $variation->pivot->price * ($p->package_quantity ?? 1);
                            } else {
                                $unitPrice = $variation->pivot->price;
                            }
                        }
                    }
                }

                $orderProduct = OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $id,
                    'quantity' => $row['quantity'],
                    'price' => $unitPrice,
                    'discount' => 0,
                    'variation_item_id' => $row['variation_id'] ?? null,
                    'percentage' => $lineDiscountPercent,
                    'package_quantity' => $p->package_quantity ?? 1,
                    'discount_type' => $orderDiscountType,
                    'flat_discount_amount' => $flatDiscountAmount,
                ]);

                // Decrement inventory (only when enabled). Selected variation SKUs can map to
                // their own synced product inventory; otherwise inventory stays on the catalog product.
                if ($isInventoryEnabled && $p->isInventoryManaged()) {
                    $variationItemId = isset($row['variation_id']) ? (int) $row['variation_id'] : null;
                    $inventory = $p->inventoryQueryForBodega($bodega, $variationItemId)?->lockForUpdate()->first();
                    $current = (int) ($inventory?->available ?? 0);
                    $reserved = (int) ($inventory?->reserved ?? 0);
                    $safety = (int) $p->getEffectiveSafetyStock();

                    // Get global minimum inventory setting (default 5 if not configured)
                    $globalMinInventory = (int) (\App\Models\Setting::getByKey('global_minimum_inventory') ?? 5);

                    // Use product safety stock if configured (> 0), otherwise use global minimum
                    $effectiveMinimum = ($safety > 0) ? $safety : $globalMinInventory;

                    // Check against current (disponible) only - it already accounts for reservations!
                    // disponible = físico - reservado (calculated in DB)
                    // Ensure after decrement, available won't go below the effective minimum
                    if ($current <= $effectiveMinimum || ($current - (int) $row['quantity']) < $effectiveMinimum || $row['quantity'] > $current) {
                        $reason = ($safety > 0) ? 'product safety stock' : 'global minimum inventory';
                        \Log::error('Order rollback: inventory insufficient during final check', [
                            'product_id' => $p->id,
                            'variation_inventory_row_id' => $inventory?->id,
                            'product_name' => $p->name,
                            'has_variation' => ! is_null($p->variation_id),
                            'variation_item_selected' => $row['variation_id'] ?? null,
                            'requested' => $row['quantity'],
                            'disponible' => $current,
                            'reservado' => $reserved,
                            'product_safety_stock' => $safety,
                            'global_minimum' => $globalMinInventory,
                            'effective_minimum' => $effectiveMinimum,
                            'reason' => $reason,
                            'bodega' => $bodega,
                        ]);
                        DB::rollBack();

                        return back()->with('error', "Inventario insuficiente para {$p->name} en su zona.");
                    }
                    if ($inventory) {
                        $inventory->update(['available' => $current - (int) $row['quantity']]);
                    } else {
                        // shouldn't happen, but guard
                        \Log::warning('Creating new inventory record during order', [
                            'product_id' => $p->id,
                            'bodega' => $bodega,
                            'quantity_ordered' => $row['quantity'],
                        ]);
                        ProductInventory::create([
                            'product_id' => $p->id,
                            'variation_item_id' => $variationItemId,
                            'source_sku' => BonificationCheckoutService::selectedVariationSku($p, $variationItemId),
                            'bodega_code' => $bodega,
                            'available' => max(0, $current - (int) $row['quantity']),
                            'physical' => 0,
                            'reserved' => $reserved,
                        ]);
                    }
                }

                // Process ALL bonifications that apply to this product (only on the last cart
                // line for this product_id so all paid lines and inventory for that product are
                // committed before the gift is calculated and the aggregate matches stock).
                // IMPORTANT: For products with variations, bonifications are checked at the
                // product level and quantities are aggregated across all variation lines.
                $bonificationsToCheck = $p->bonifications;
                if ($bonificationsToCheck->isNotEmpty() && ($lastCartKeyByProductId[$id] ?? $key) === $key) {
                    $plannedBonifications = [];
                    $giftProductStockPlan = []; // stock key => ['catalog_product', 'inventory', 'requested_total']

                    foreach ($bonificationsToCheck as $bonification) {
                        $aggregatedIndividualItems = $productQuantities[$id] ?? ($row['quantity'] * ($p->package_quantity ?? 1));
                        $bonification_quantity = (int) floor($aggregatedIndividualItems / $bonification->buy * $bonification->get);
                        if ($bonification_quantity <= 0) {
                            continue;
                        }
                        if ($bonification_quantity > $bonification->max) {
                            $bonification_quantity = (int) $bonification->max;
                        }

                        $bonificationProduct = Product::with(['items', 'categories'])->find($bonification->product_id);
                        if (! $bonificationProduct) {
                            Log::warning('Bonification gift product missing at checkout', [
                                'bonification_id' => $bonification->id,
                                'product_id' => $bonification->product_id,
                                'order_id' => $order->id,
                            ]);

                            continue;
                        }

                        $variationItemId = BonificationCheckoutService::resolveGiftVariationItemId(
                            $bonificationProduct,
                            (int) $order->id,
                            $isInventoryEnabled ? $bodega : null,
                            $bonification_quantity
                        );
                        $giftProductId = (int) $bonificationProduct->id;
                        $stockKey = $giftProductId.':'.($variationItemId ?: 'base');
                        $plannedBonifications[] = [
                            'bonification' => $bonification,
                            'gift_product' => $bonificationProduct,
                            'stock_key' => $stockKey,
                            'variation_item_id' => $variationItemId,
                            'quantity' => $bonification_quantity,
                        ];

                        if (! isset($giftProductStockPlan[$stockKey])) {
                            $inventoryRow = null;
                            if ($isInventoryEnabled && $bodega && $bonificationProduct->isInventoryManaged()) {
                                $inventoryRow = $bonificationProduct
                                    ->inventoryQueryForBodega($bodega, $variationItemId)
                                    ?->lockForUpdate()
                                    ->first();
                            }
                            $giftProductStockPlan[$stockKey] = [
                                'catalog_product' => $bonificationProduct,
                                'inventory' => $inventoryRow,
                                'requested_total' => 0,
                            ];
                        }
                        $giftProductStockPlan[$stockKey]['requested_total'] += $bonification_quantity;
                    }

                    foreach ($giftProductStockPlan as $stockKey => $stockPlan) {
                        $catalogProduct = $stockPlan['catalog_product'];
                        $requestedTotal = (int) $stockPlan['requested_total'];
                        if (! $isInventoryEnabled || ! $bodega || ! $catalogProduct->isInventoryManaged()) {
                            continue;
                        }

                        $disponible = (int) ($stockPlan['inventory']?->available ?? 0);
                        $hasEnoughForAll = BonificationCheckoutService::hasEnoughStockForRequestedUnits(
                            $disponible,
                            $catalogProduct,
                            $requestedTotal
                        );
                        if (! $hasEnoughForAll) {
                            Log::warning('Order rollback: insufficient stock for bonification gift', [
                                'order_id' => $order->id,
                                'trigger_product_id' => $id,
                                'gift_product_id' => $catalogProduct->id,
                                'stock_key' => $stockKey,
                                'requested_total' => $requestedTotal,
                                'available' => $disponible,
                                'bodega' => $bodega,
                            ]);
                            DB::rollBack();

                            return back()->with(
                                'error',
                                "Inventario insuficiente para entregar la bonificación de {$catalogProduct->name} en su zona."
                            );
                        }
                    }

                    $firstOrderProductForProduct = OrderProduct::query()
                        ->where('order_id', $order->id)
                        ->where('product_id', $id)
                        ->first();
                    $bonificationOrderProductId = $firstOrderProductForProduct?->id ?? $orderProduct->id;

                    $consumedByGiftProduct = [];
                    foreach ($plannedBonifications as $planned) {
                        $giftProduct = $planned['gift_product'];
                        $giftProductId = (int) $giftProduct->id;
                        $stockKey = (string) $planned['stock_key'];

                        OrderProductBonification::create([
                            'bonification_id' => $planned['bonification']->id,
                            'order_product_id' => $bonificationOrderProductId,
                            'product_id' => $giftProductId,
                            'variation_item_id' => $planned['variation_item_id'],
                            'quantity' => (int) $planned['quantity'],
                            'order_id' => $order->id,
                        ]);

                        if (! isset($consumedByGiftProduct[$stockKey])) {
                            $consumedByGiftProduct[$stockKey] = 0;
                        }
                        $consumedByGiftProduct[$stockKey] += (int) $planned['quantity'];
                    }

                    foreach ($consumedByGiftProduct as $stockKey => $qtyToConsume) {
                        $stockPlan = $giftProductStockPlan[$stockKey] ?? null;
                        $inventoryRow = $stockPlan['inventory'] ?? null;
                        if ($inventoryRow && $qtyToConsume > 0) {
                            $inventoryRow->update(['available' => (int) $inventoryRow->available - $qtyToConsume]);
                        }
                    }
                }

                // Calculate line totals based on whether coupon modified the product
                if (isset($modifiedProductsLookup[$lookupKey])) {
                    $modProduct = $modifiedProductsLookup[$lookupKey];
                    $discountType = $modProduct['applied_discount_type'] ?? 'percentage';

                    if ($discountType === 'fixed_amount') {
                        $lineTotal = (float) ($modProduct['line_total']
                            ?? ($modProduct['new_unit_price'] * $row['quantity'] * ($p->package_quantity ?? 1)));
                        $lineDiscount = (float) ($modProduct['line_savings'] ?? $modProduct['final_discount_amount']);
                    } else {
                        $lineDiscount = (float) ($modProduct['line_savings'] ?? $modProduct['final_discount_amount']);
                        $lineTotal = (float) ($modProduct['line_total']
                            ?? (($modProduct['base_price'] * $row['quantity'] * ($p->package_quantity ?? 1)) - $lineDiscount));
                    }
                } else {
                    // Use original calculation for non-coupon affected products
                    // Pass vendor total to ensure vendor discount minimum is checked
                    $vendorId = $p->brand && $p->brand->vendor ? $p->brand->vendor->id : null;
                    $vendorTotal = $vendorId && isset($vendorTotals[$vendorId]) ? $vendorTotals[$vendorId] : null;
                    $lineFinal = $p->getFinalPriceForUser($has_orders, $vendorTotal);
                    $lineTotal = $lineFinal['price'] * $row['quantity'];
                    $lineDiscount = $lineFinal['totalDiscount'] * $row['quantity'];
                }

                $total += $lineTotal;
                $discount += $lineDiscount;

                $taxPctRetention = (float) (optional($p->tax)->tax ?? 0);
                [$retentionBaseArticulos, $retentionIvaArticulos] = \App\Services\CartRetentionService::accumulateFromTaxInclusiveLine(
                    $lineTotal,
                    $taxPctRetention,
                    $retentionBaseArticulos,
                    $retentionIvaArticulos
                );

                // Increment sales count for best-selling tracking
                $p->incrementSalesCount($row['quantity']);
            }

            // For users with orders, reset traditional discounts but keep coupon discounts
            if ($has_orders) {
                // Recalculate totals to exclude traditional discounts but keep coupon effects
                $total = 0;
                $discount = 0;
                $retentionBaseArticulos = 0.0;
                $retentionIvaArticulos = 0.0;

                foreach ($cart as $key => $row) {
                    $p = Product::with('tax', 'items', 'brand.vendor', 'bonifications')->find($row['product_id']);
                    $lookupKey = $row['product_id'].'_'.($row['variation_id'] ?? 'null');

                    if (isset($modifiedProductsLookup[$lookupKey])) {
                        $modProduct = $modifiedProductsLookup[$lookupKey];
                        $discountType = $modProduct['applied_discount_type'] ?? 'percentage';

                        if ($discountType === 'fixed_amount') {
                            $lineTotal = (float) ($modProduct['line_total']
                                ?? ($modProduct['new_unit_price'] * $row['quantity'] * ($p->package_quantity ?? 1)));
                            $lineDiscount = (float) ($modProduct['line_savings'] ?? $modProduct['final_discount_amount']);
                        } elseif (($modProduct['discount_source'] ?? null) === 'coupon') {
                            // Only apply coupon discount, ignore existing discounts
                            $lineSubtotal = $modProduct['base_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                            $lineDiscount = (float) ($modProduct['coupon_contribution'] ?? 0);
                            $lineTotal = $lineSubtotal - $lineDiscount;
                        } else {
                            // No discount for users with orders if it's existing discount
                            $lineTotal = $modProduct['base_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                            $lineDiscount = 0;
                        }
                    } else {
                        // No coupon, no discount for users with orders
                        $basePrice = $p->price;
                        $variation = $p->items->where('id', $row['variation_id'])->first();
                        if ($variation) {
                            $basePrice = $variation->pivot->price;
                        }
                        $lineTotal = $basePrice * $row['quantity'] * ($p->package_quantity ?? 1);
                        $lineDiscount = 0;
                    }

                    $taxPctRetention = (float) (optional($p->tax)->tax ?? 0);
                    $useInclusiveRetention = isset($modifiedProductsLookup[$lookupKey])
                        && (($modifiedProductsLookup[$lookupKey]['applied_discount_type'] ?? '') === 'fixed_amount');
                    if ($useInclusiveRetention) {
                        [$retentionBaseArticulos, $retentionIvaArticulos] = \App\Services\CartRetentionService::accumulateFromTaxInclusiveLine(
                            $lineTotal,
                            $taxPctRetention,
                            $retentionBaseArticulos,
                            $retentionIvaArticulos
                        );
                    } else {
                        [$retentionBaseArticulos, $retentionIvaArticulos] = \App\Services\CartRetentionService::accumulateFromTaxExclusiveLine(
                            $lineTotal,
                            $taxPctRetention,
                            $retentionBaseArticulos,
                            $retentionIvaArticulos
                        );
                    }

                    $total += $lineTotal;
                    $discount += $lineDiscount;
                }
            }

            $finalTotal = $total;

            $shippingForRetention = (float) ($shippingQuoteAmount ?? 0);
            $retentionCalc = app(\App\Services\CartRetentionService::class)->calculateFromAggregates(
                $actingUser->tax_group ?? null,
                $retentionBaseArticulos,
                $retentionIvaArticulos,
                $shippingForRetention,
                false
            );

            $order->update([
                'total' => $finalTotal,
                'discount' => $discount,
                'coupon_discount' => $couponDiscount,
                'tax_group' => $actingUser->tax_group,
                'retention_fuente' => $retentionCalc['retention_fuente'],
                'retention_iva' => $retentionCalc['retention_iva'],
                'retention_total' => $retentionCalc['retention_total'],
            ]);

            // Record coupon usage for ALL winning coupons
            if (! empty($winningCoupons) && $couponDiscount > 0) {
                $couponService = app(CouponService::class);
                $orderUser = User::find($user_id);

                if (! $orderUser) {
                    throw new \Exception("Usuario no encontrado para registrar uso de cupón. ID: {$user_id}");
                }

                // Calculate per-coupon discount contributions from modified products
                $perCouponDiscount = [];
                if ($couponResult && $couponResult['success']) {
                    foreach ($couponResult['modified_products'] as $modProduct) {
                        $cId = $modProduct['winning_coupon_id'] ?? null;
                        if ($cId && isset($winningCoupons[$cId])) {
                            if (! isset($perCouponDiscount[$cId])) {
                                $perCouponDiscount[$cId] = 0;
                            }
                            $perCouponDiscount[$cId] += (float) ($modProduct['coupon_contribution']
                                ?? $modProduct['actual_discount_amount']
                                ?? $modProduct['final_discount_amount']
                                ?? 0);
                        }
                    }
                }

                foreach ($winningCoupons as $winCouponId => $winCouponCode) {
                    $winCoupon = Coupon::find($winCouponId);
                    if ($winCoupon) {
                        $winCouponDiscount = $perCouponDiscount[$winCouponId] ?? 0;
                        $couponService->recordCouponUsage($winCoupon, $orderUser, $order, $winCouponDiscount);
                    }
                }
            }

            DB::commit();

            //if env production
            if (app()->environment('production')) {
                session()->forget('cart');
            }

            session()->forget('user_id');
            $this->clearAllCouponSessions(); // Clear all coupon data after successful order

            // Dispatch async job to handle XML transmission and email sending
            // This allows the user to get an immediate response while processing happens in the background

            // Determine which queue connection to use
            $queueConnection = config('queue.default');

            // If sync, use database queue to ensure async processing
            if ($queueConnection === 'sync') {
                $queueConnection = 'database';
            }

            // Check if Redis is available, fallback to database if not
            if ($queueConnection === 'redis') {
                try {
                    // Test if Redis is actually available
                    if (! extension_loaded('redis') || ! class_exists('Redis')) {
                        Log::warning('Redis extension not available, falling back to database queue');
                        $queueConnection = 'database';
                    }
                } catch (\Exception $e) {
                    Log::warning('Redis check failed, falling back to database queue: '.$e->getMessage());
                    $queueConnection = 'database';
                }
            }

            // Only dispatch job immediately if order is not waiting or draft
            if ($orderStatus !== Order::STATUS_WAITING && $orderStatus !== Order::STATUS_DRAFT) {
                // Dispatch job on the appropriate connection
                \App\Jobs\ProcessOrderAsync::dispatch($order)->onConnection($queueConnection);
                Log::info("Order {$order->id} created successfully, async processing dispatched on {$queueConnection} queue");
            } else {
                Log::info("Order {$order->id} created with STATUS_WAITING, will be processed on {$scheduledTransmissionDate}");
            }

            // Redirect to thank you page instead of home
            return redirect()->route('orders.thank-you', $order->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $user_id ?? null,
                'has_coupon' => isset($coupon) && $coupon ? true : false,
                'coupon_id' => isset($coupon) && $coupon ? $coupon->id : null,
                'coupon_discount' => $couponDiscount ?? 0,
                'trace' => $e->getTraceAsString(),
            ]);

            // Provide more specific error message when possible
            $errorMessage = 'Error al procesar la orden. ';

            // Check if it's a coupon-related error
            if (isset($coupon) && $coupon) {
                if (str_contains($e->getMessage(), 'coupon') || str_contains($e->getMessage(), 'cupón') ||
                    str_contains($e->getMessage(), 'limit') || str_contains($e->getMessage(), 'limite')) {
                    $errorMessage .= 'Hubo un problema con el cupón aplicado. Por favor remuévelo e intenta nuevamente.';
                    $this->clearAllCouponSessions();
                } else {
                    $errorMessage .= 'Por favor intente nuevamente o contacte al administrador.';
                }
            } else {
                $errorMessage .= 'Por favor intente nuevamente o contacte al administrador.';
            }

            // In development/staging, show the actual error for debugging
            if (config('app.debug')) {
                $errorMessage .= ' (Debug: '.$e->getMessage().')';
            }

            return to_route('cart')->with('error', $errorMessage);
        }

        // return to_route('home')->with('success', 'Es necesario tener un codigo de cliente para procesar la compra, contacta al administrador!');

        // dispatch(new ProcessOrder($order));
        //

    }

    /**
     * Get the user for coupon validation/application.
     * When a seller is acting for a client (session has user_id), use the client for APPLIES_TO_CUSTOMER checks.
     */
    private function getCouponTargetUser(): ?User
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if ($user->hasAnyRole(['seller', 'supervisor'])) {
            $clientId = session()->get('user_id');

            return $clientId ? User::find($clientId) : $user;
        }

        return $user;
    }

    /**
     * Apply a coupon to the cart
     */
    public function applyCoupon(Request $request, CouponService $couponService)
    {
        $request->validate([
            'coupon_code' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Use client when seller is acting for a client (APPLIES_TO_CUSTOMER checks the order's customer)
        $targetUser = $this->getCouponTargetUser();
        if (! $targetUser) {
            return redirect()->route('login');
        }

        $cart = session()->get('cart');
        if (! $cart || empty($cart)) {
            return redirect()->route('cart')->with('error', 'El carrito está vacío.');
        }

        // Validate and clean cart data
        $cart = $this->validateCart($cart);
        if (empty($cart)) {
            session()->forget('cart');

            return redirect()->route('cart')->with('error', 'Tu carrito contenía productos inválidos.');
        }

        $couponCode = trim($request->coupon_code);

        // Get existing applied coupons
        $appliedCoupons = session()->get('applied_coupons', []);
        $appliedCouponCodes = array_column($appliedCoupons, 'coupon_code');

        // Check if coupon is already applied
        if (in_array($couponCode, $appliedCouponCodes)) {
            return redirect()->route('cart')->with('error', 'Este cupón ya está aplicado.');
        }

        // Calculate current cart total (without any existing discounts from promotions)
        $cartProducts = collect($cart);
        $cartTotal = 0;

        foreach ($cartProducts as $item) {
            $product = Product::with('brand.vendor')->find($item['product_id']);
            if ($product) {
                $basePrice = $product->price;
                $variation = $product->items->where('id', $item['variation_id'])->first();
                if ($variation) {
                    $basePrice = $variation->pivot->price;
                }
                $cartTotal += $basePrice * $item['quantity'] * ($product->package_quantity ?? 1);
            }
        }

        // Validate coupon (use targetUser = client when seller acting)
        $validation = $couponService->validateCoupon($couponCode, $targetUser, $cartProducts, $cartTotal);

        if (! $validation['valid']) {
            return redirect()->route('cart')->with('error', $validation['message']);
        }

        $coupon = $validation['coupon'];

        // Recalculate all coupons including the new one (use targetUser for APPLIES_TO_CUSTOMER)
        $allCouponCodes = array_merge($appliedCouponCodes, [$couponCode]);
        $discountCalculation = $couponService->calculateMultipleCouponDiscounts($allCouponCodes, $targetUser, $cartProducts);

        if (! $discountCalculation['success']) {
            return redirect()->route('cart')->with('error', 'Error al aplicar el cupón.');
        }

        // Store all applied coupons in session
        session()->put('applied_coupons', $discountCalculation['applied_coupons']);
        session()->put('coupon_discounts', $discountCalculation['product_discounts']);
        session()->put('total_coupon_discount', $discountCalculation['total_discount']);

        return redirect()->route('cart')->with('success', "Cupón '{$coupon->code}' aplicado exitosamente.");
    }

    /**
     * Remove a specific coupon from the cart
     */
    public function removeCoupon(Request $request)
    {
        $couponCode = $request->input('coupon_code');

        if (! $couponCode) {
            // Remove all coupons if no specific code provided (backward compatibility)
            session()->forget('applied_coupons');
            session()->forget('coupon_discounts');
            session()->forget('total_coupon_discount');

            return redirect()->route('cart')->with('success', 'Cupones removidos exitosamente.');
        }

        $appliedCoupons = session()->get('applied_coupons', []);
        $appliedCoupons = array_filter($appliedCoupons, function ($coupon) use ($couponCode) {
            return $coupon['coupon_code'] !== $couponCode;
        });

        // Recalculate discounts with remaining coupons (use client when seller acting)
        $targetUser = $this->getCouponTargetUser();
        if ($targetUser) {
            $cart = session()->get('cart', []);
            $cartProducts = collect($cart);
            $remainingCodes = array_column($appliedCoupons, 'coupon_code');

            if (! empty($remainingCodes)) {
                $couponService = app(\App\Services\CouponService::class);
                $discountCalculation = $couponService->calculateMultipleCouponDiscounts($remainingCodes, $targetUser, $cartProducts);

                if ($discountCalculation['success']) {
                    session()->put('applied_coupons', $discountCalculation['applied_coupons']);
                    session()->put('coupon_discounts', $discountCalculation['product_discounts']);
                    session()->put('total_coupon_discount', $discountCalculation['total_discount']);
                } else {
                    session()->forget('applied_coupons');
                    session()->forget('coupon_discounts');
                    session()->forget('total_coupon_discount');
                }
            } else {
                session()->forget('applied_coupons');
                session()->forget('coupon_discounts');
                session()->forget('total_coupon_discount');
            }
        } else {
            session()->forget('applied_coupons');
            session()->forget('coupon_discounts');
            session()->forget('total_coupon_discount');
        }

        return redirect()->route('cart')->with('success', 'Cupón removido exitosamente.');
    }

    /**
     * Show the thank you page after order is placed
     */
    public function thankYou(Order $order)
    {
        // Verify the order belongs to the current user or their client (for sellers)
        $user = auth()->user();

        if ($user->hasAnyRole(['seller', 'supervisor'])) {
            // Sellers can view orders they created for clients
            if ($order->seller_id !== $user->id) {
                abort(403, 'No autorizado para ver este pedido.');
            }
        } else {
            // Regular users can only view their own orders
            if ($order->user_id !== $user->id) {
                abort(403, 'No autorizado para ver este pedido.');
            }
        }

        return view('orders.thank-you', compact('order'));
    }

    /**
     * Load valid Coupon model instances from session (supports both multi-coupon and legacy single-coupon)
     *
     * @return Coupon[]
     */
    private function loadValidCouponsFromSession(): array
    {
        $coupons = [];

        // Primary: multi-coupon session key
        $appliedCoupons = session()->get('applied_coupons', []);
        if (! empty($appliedCoupons)) {
            foreach ($appliedCoupons as $entry) {
                $coupon = Coupon::find($entry['coupon_id'] ?? null);
                if ($coupon && $coupon->isValid()) {
                    $coupons[$coupon->id] = $coupon;
                }
            }
        }

        // Backward compatibility: single-coupon session key
        $singleCoupon = session()->get('applied_coupon');
        if ($singleCoupon && empty($coupons)) {
            $coupon = Coupon::find($singleCoupon['coupon_id'] ?? null);
            if ($coupon && $coupon->isValid()) {
                $coupons[$coupon->id] = $coupon;
            }
        }

        // If any coupons are invalid, update session to keep only valid ones
        if (! empty($appliedCoupons) && count($coupons) < count($appliedCoupons)) {
            $validEntries = [];
            foreach ($coupons as $c) {
                $validEntries[] = ['coupon_id' => $c->id, 'coupon_code' => $c->code];
            }
            session()->put('applied_coupons', $validEntries);

            if (empty($validEntries)) {
                $this->clearAllCouponSessions();
            }
        }

        return array_values($coupons);
    }

    /**
     * Clear all coupon-related session data (both multi-coupon and legacy)
     */
    private function clearAllCouponSessions(): void
    {
        session()->forget('applied_coupon');    // Legacy single-coupon
        session()->forget('applied_coupons');   // Multi-coupon list
        session()->forget('coupon_discounts');   // Per-product discount cache
        session()->forget('total_coupon_discount'); // Total discount cache
    }

    /**
     * Build cart bonification preview grouped by parent product_id.
     *
     * Product variations share product_id in cart rows, so quantities are aggregated
     * across all selected variations before evaluating each bonification rule.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCartBonificationPreview(array $cart): array
    {
        $productIds = collect($cart)
            ->map(fn ($row) => (int) ($row['product_id'] ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $quantityProducts = Product::query()
            ->whereIn('id', $productIds->all())
            ->get(['id', 'package_quantity'])
            ->keyBy('id');

        $productQuantities = [];
        foreach ($cart as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $product = $quantityProducts->get($productId);
            if (! $product) {
                continue;
            }

            $packageQuantity = (int) ($product->package_quantity ?? 1);
            $lineQuantity = (int) ($row['quantity'] ?? 0);
            $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + ($lineQuantity * $packageQuantity);
        }

        if (empty($productQuantities)) {
            return [];
        }

        $products = Product::with(['bonifications.product'])
            ->whereIn('id', array_keys($productQuantities))
            ->get()
            ->keyBy('id');

        $preview = [];
        foreach ($productQuantities as $productId => $aggregatedItems) {
            $triggerProduct = $products->get($productId);
            if (! $triggerProduct || $triggerProduct->bonifications->isEmpty()) {
                continue;
            }

            foreach ($triggerProduct->bonifications as $bonification) {
                $buy = (int) ($bonification->buy ?? 0);
                $get = (int) ($bonification->get ?? 0);
                if ($buy <= 0 || $get <= 0) {
                    continue;
                }

                $giftQuantity = (int) floor(($aggregatedItems / $buy) * $get);
                // Keep cart preview behavior aligned with checkout processing.
                $max = (int) ($bonification->max ?? 0);
                if ($giftQuantity > $max) {
                    $giftQuantity = $max;
                }

                if ($giftQuantity <= 0) {
                    continue;
                }

                $preview[] = [
                    'bonification_name' => $bonification->name,
                    'trigger_product_name' => $triggerProduct->name,
                    'gift_product_name' => $bonification->product?->name,
                    'buy' => $buy,
                    'get' => $get,
                    'aggregated_items' => $aggregatedItems,
                    'gift_quantity' => $giftQuantity,
                ];
            }
        }

        return $preview;
    }

    /**
     * Resolve checkout delivery zone for the client ($actingUser).
     *
     * - Posted `zone_id` is authoritative when it belongs to the user (vendedor UI or stale hidden sucursal must not
     *   override the selected <select> value).
     * - Session `zone_id` is only a fallback for single-address users (multi-sucursal: never trust global session, e.g.
     *   after seller switches clients).
     * - When posted id is obsolete after rutero sync, `sucursal_code` + fingerprint still resolve the branch.
     *
     * @return array{ok: true, zone: \App\Models\Zone}|array{ok: false, message: string}
     */
    private function resolveCheckoutZone(User $actingUser, Request $request): array
    {
        $actingUser->load('zones');
        $zoneCount = $actingUser->zones->count();

        $rawRequestZoneId = $this->normalizedCheckoutZoneId($request->input('zone_id'));
        $sessionZoneId = $this->normalizedCheckoutZoneId(session()->get('zone_id'));
        $requestedZoneId = $rawRequestZoneId ?? ($zoneCount <= 1 ? $sessionZoneId : null);

        $rawSucursalCode = $request->input('sucursal_code');
        $trimmedSucursalCode = is_string($rawSucursalCode) ? trim($rawSucursalCode) : '';
        $trimmedSucursalCode = $trimmedSucursalCode !== '' ? $trimmedSucursalCode : null;
        $zoneFingerprint = [
            'code' => $trimmedSucursalCode,
            'route' => $this->normalizeCheckoutZoneText($request->input('sucursal_route')),
            'zone' => $this->normalizeCheckoutZoneText($request->input('sucursal_zone')),
            'day' => $this->normalizeCheckoutZoneText($request->input('sucursal_day')),
            'address' => $this->normalizeCheckoutZoneText($request->input('sucursal_address')),
        ];

        $zone = null;

        if ($requestedZoneId !== null) {
            $candidate = Zone::where('id', $requestedZoneId)
                ->where('user_id', $actingUser->id)
                ->first();
            if ($candidate) {
                if ($this->sucursalRequestHasData($zoneFingerprint) && ! $this->zoneMatchesCheckoutFingerprint($candidate, $zoneFingerprint)) {
                    \Log::warning('Checkout zone_id does not match posted sucursal fields; using posted zone_id (authoritative)', [
                        'acting_user_id' => $actingUser->id,
                        'zone_id' => $candidate->id,
                    ]);
                }
                $zone = $candidate;
            }
        }

        if (! $zone && $trimmedSucursalCode !== null) {
            $candidates = Zone::where('user_id', $actingUser->id)
                ->where('code', $trimmedSucursalCode)
                ->orderBy('id')
                ->get();

            if ($candidates->count() === 1) {
                $zone = $candidates->first();
            } elseif ($candidates->count() > 1) {
                $fingerprintMatches = $candidates->filter(function ($candidate) use ($zoneFingerprint) {
                    return $this->zoneMatchesCheckoutFingerprint($candidate, $zoneFingerprint);
                });

                if ($fingerprintMatches->count() === 1) {
                    $zone = $fingerprintMatches->first();
                }

                \Log::warning('Multiple zones share the same sucursal code for user; fingerprint required to disambiguate', [
                    'acting_user_id' => $actingUser->id,
                    'code' => $trimmedSucursalCode,
                    'zone_ids' => $candidates->pluck('id')->all(),
                    'fingerprint_matches' => $fingerprintMatches->pluck('id')->all(),
                ]);
            }
        }

        if (! $zone && $this->hasCheckoutZoneFingerprint($zoneFingerprint)) {
            $fingerprintMatches = $actingUser->zones->filter(function ($candidate) use ($zoneFingerprint) {
                return $this->zoneMatchesCheckoutFingerprint($candidate, $zoneFingerprint);
            });
            if ($fingerprintMatches->count() === 1) {
                $zone = $fingerprintMatches->first();
            }
        }

        if ($zone) {
            session()->put('zone_id', $zone->id);

            return ['ok' => true, 'zone' => $zone];
        }

        if ($zoneCount === 0) {
            return [
                'ok' => false,
                'message' => 'No hay dirección de entrega disponible. Actualiza tus datos de rutero e intenta de nuevo.',
            ];
        }
        if ($zoneCount === 1) {
            $only = $actingUser->zones->first();
            session()->put('zone_id', $only->id);

            return ['ok' => true, 'zone' => $only];
        }
        \Log::warning('Zone resolution failed for multi-zone user', [
            'acting_user_id' => $actingUser->id,
            'requested_zone_id' => $requestedZoneId,
            'sucursal_code' => $trimmedSucursalCode,
            'available_zone_ids' => $actingUser->zones->pluck('id')->all(),
            'available_zone_codes' => $actingUser->zones->pluck('code')->all(),
        ]);

        return [
            'ok' => false,
            'message' => 'La dirección seleccionada ya no coincide con tus datos actualizados. Recarga la página del carrito y vuelve a elegir la sucursal. Si tienes varias sucursales y esto se repite, contacta a soporte (puede faltar el código de sucursal en tus datos).',
        ];
    }

    /**
     * True when the request sent at least one sucursal / fingerprint field (any of code, route, zone, day, address).
     */
    private function sucursalRequestHasData(array $fingerprint): bool
    {
        foreach (['code', 'route', 'zone', 'day', 'address'] as $field) {
            $v = $fingerprint[$field] ?? null;
            if ($v !== null && $v !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Positive integer zone id from request/session (rejects 0, negative, non-numeric strings).
     */
    private function normalizedCheckoutZoneId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || ! ctype_digit($value)) {
                return null;
            }
            $id = (int) $value;

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Normalize text fields used to preserve selected sucursal identity across sync.
     */
    private function normalizeCheckoutZoneText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', $value) ?? $value, 'UTF-8');
    }

    /**
     * True when request includes at least one fingerprint field beyond code.
     */
    private function hasCheckoutZoneFingerprint(array $fingerprint): bool
    {
        foreach (['route', 'zone', 'day', 'address'] as $field) {
            if (! empty($fingerprint[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare normalized fingerprint fields against a zone.
     */
    private function zoneMatchesCheckoutFingerprint(Zone $zone, array $fingerprint): bool
    {
        foreach (['code', 'route', 'zone', 'day', 'address'] as $field) {
            $expected = $fingerprint[$field] ?? null;
            if ($expected === null || $expected === '') {
                continue;
            }

            $actual = $this->normalizeCheckoutZoneText($zone->{$field} ?? null);
            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }
}
