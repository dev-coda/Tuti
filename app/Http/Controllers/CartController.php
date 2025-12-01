<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrder;
use App\Mail\NewOrderEmail;
use App\Mail\OrderEmail;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Setting;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Services\CouponService;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
        if (!is_array($cart)) {
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
            if (!is_array($item) || !isset($item['product_id']) || !isset($item['quantity'])) {
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
            if (!is_numeric($item['product_id']) || $item['product_id'] <= 0) {
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
            if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
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
            if (!empty($validCart)) {
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

        if (!$cart) {
            return redirect()->route('home');
        }

        // Validate and clean cart data
        $cart = $this->validateCart($cart);

        if (empty($cart)) {
            session()->forget('cart');
            return redirect()->route('home')->with('error', 'Tu carrito estaba vacío o contenía productos inválidos.');
        }

        $user = auth()->user();
        $zones = $user->zones->pluck('address', 'id')->toArray();

        $set_user = false;
        $client = null;
        if ($user->hasRole('seller')) {
            $user_id = session()->get('user_id');
            $set_user = true;
            if ($user_id) {
                $client = User::with('zones')->find($user_id);
                $zones = $client->zones->pluck('address', 'id')->toArray();
                $set_user = false;
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

        // Check for applied coupon and calculate proper discounts FIRST
        $appliedCoupon = session()->get('applied_coupon');
        $couponDiscount = 0;
        $couponMessage = null;
        $couponResult = null;

        if ($appliedCoupon) {
            $coupon = Coupon::find($appliedCoupon['coupon_id']);
            if ($coupon && $coupon->isValid()) {
                // Use new CouponDiscountService for proper discount calculation
                $couponDiscountService = app(\App\Services\CouponDiscountService::class);
                $couponResult = $couponDiscountService->applyCouponDiscountToProducts(
                    $coupon,
                    $targetUser,
                    collect($cart),
                    $has_orders
                );

                if ($couponResult['success']) {
                    $couponDiscount = $couponResult['total_coupon_discount'];
                    $couponMessage = "Cupón '{$coupon->code}' aplicado";
                } else {
                    // Coupon application failed
                    session()->forget('applied_coupon');
                    $appliedCoupon = null;
                }
            } else {
                // Coupon is no longer valid, remove it
                session()->forget('applied_coupon');
                $appliedCoupon = null;
            }
        }

        // Create products array with potential coupon-modified pricing
        $modifiedProductsLookup = [];
        if ($couponResult && $couponResult['success']) {
            foreach ($couponResult['modified_products'] as $modProduct) {
                $key = $modProduct['product_id'] . '_' . ($modProduct['variation_id'] ?? 'null');
                $modifiedProductsLookup[$key] = $modProduct;
            }
        }

        foreach ($cart as $item) {
            $product = Product::with('brand.vendor', 'variation')->find($item['product_id']);

            // Skip if product not found (might have been deleted)
            if (!$product) {
                Log::warning('Product not found in cart', [
                    'product_id' => $item['product_id'],
                    'user_id' => auth()->id(),
                ]);
                continue;
            }

            // Skip if product has no brand or vendor
            if (!$product->brand || !$product->brand->vendor) {
                Log::warning('Product missing brand or vendor in cart', [
                    'product_id' => $product->id,
                    'has_brand' => !is_null($product->brand),
                    'user_id' => auth()->id(),
                ]);
                continue;
            }

            $product->item = $product->items->where('id', $item['variation_id'])->first();
            $product->quantity = $item['quantity'];
            $product->vendor_id = $product->brand->vendor->id;

            // Check if this product was modified by coupon
            $lookupKey = $item['product_id'] . '_' . ($item['variation_id'] ?? 'null');
            if (isset($modifiedProductsLookup[$lookupKey])) {
                // Use coupon-modified pricing
                $finalPrice = $product->getFinalPriceWithCoupon($has_orders, $modifiedProductsLookup[$lookupKey]);
            } else {
                // Use regular pricing without vendor discount (pass 0 to prevent vendor discount)
                // This will be recalculated later with proper vendor totals
                $finalPrice = $product->getFinalPriceForUser($has_orders, 0);
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
                    'discount_percentage' => $v->discount
                ];
            }
        }

        // Recalculate products with vendor totals for proper discount application
        $products = collect($products)->map(function ($product) use ($vendorTotals, $has_orders, $modifiedProductsLookup) {
            $vendorTotal = $vendorTotals[$product->vendor_id] ?? 0;

            // Check if this product was modified by coupon - if so, keep the coupon pricing
            $lookupKey = $product->id . '_' . ($product->item->id ?? 'null');
            if (isset($modifiedProductsLookup[$lookupKey])) {
                // Keep the coupon-modified pricing - don't recalculate
                return $product;
            }

            // Recalculate with vendor total for discount qualification (non-coupon products only)
            $finalPrice = $product->getFinalPriceForUser($has_orders, $vendorTotal);
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

        // Coupon calculation is already done above

        $context = compact('products', 'alertVendors', 'vendorDiscountAlerts', 'zones', 'set_user', 'client', 'alertTotal', 'min_amount', 'total_cart', 'has_orders', 'appliedCoupon', 'couponDiscount', 'couponMessage');

        return view('pages.cart', $context);
    }






    #TODO crear plugin de agregar al carrito
    public function add(Request $request, Product $product)
    {


        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'variation_id' => 'nullable|numeric',
            'quantity' => 'required|numeric',
        ]);

        // Enforce safety stock only when inventory management is enabled
        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $isInventoryEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        if ($isInventoryEnabled && $product->isInventoryManaged()) {
            $safety = $product->getEffectiveSafetyStock();
            // Determine user bodega from zone mapping
            $zone = $user->zones()->orderBy('id')->first();
            // Use zone field only (actual zone number like "933")
            // Note: code field contains CustRuteroID and should NOT be used for zone determination
            $zoneCode = $zone?->zone ?? $user->zone;
            $bodega = ZoneWarehouse::getBodegaForZone($zoneCode);

            // For products with variations, inventory is always stored at the parent product level
            // All variation items share the same inventory pool
            $available = $bodega ? ($product->inventories()->where('bodega_code', $bodega)->value('available') ?? 0) : 0;

            if ($available <= $safety) {
                \Log::info('Product blocked due to safety stock', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'has_variation' => !is_null($product->variation_id),
                    'variation_id_selected' => $request->variation_id,
                    'available' => $available,
                    'safety' => $safety,
                    'bodega' => $bodega
                ]);
                return back()->with('error', 'Este producto no está disponible por debajo del stock de seguridad.');
            }
        }

        $product_id = $product->id;
        $variation_id = $request->variation_id;

        $cart = session()->get('cart');


        if (!$cart) {
            $cart[] = [
                "product_id" => $product->id,
                "quantity" => $request->quantity,
                "variation_id" => $request->variation_id,
            ];
            session()->put('cart', $cart);
            return redirect()->back()
                ->with('success', 'Producto agregado al carrito exitosamente!')
                ->with('cart_updated', true);
        }

        $found_index = null;

        foreach ($cart as $index => $row) {
            if ($row["product_id"] == $product_id && $row["variation_id"] == $variation_id) {
                $found_index = $index;
                break;
            }
        }

        if ($found_index === null) {
            $cart[] = [
                "product_id" => $product_id,
                "quantity" => $request->quantity,
                "variation_id" => $request->variation_id,
            ];
            session()->put('cart', $cart);
            return redirect()->back()
                ->with('success', 'Producto agregado al carrito exitosamente!')
                ->with('cart_updated', true);
        }


        $cart[$found_index]['quantity'] = $request->quantity;


        session()->put('cart', $cart);
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

        // dd($request->all());

        $cart = session()->get('cart');



        $items = $request->items;



        foreach ($items as $key => $item) {
            $cart[$key]['quantity'] = $item;
        }

        session()->put('cart', $cart);
        return redirect()->back()
            ->with('success', 'Carrito actualizado exitosamente!')
            ->with('cart_updated', true);

        // $request->validate([
        //     'product_id' => 'required|numeric',
        //     'quantity' => 'required|numeric',
        // ]);

        // $cart = session()->get('cart');

        // if(isset($cart[$request->product_id])) {
        //     $cart[$request->product_id]['quantity'] = $request->quantity;
        //     session()->put('cart', $cart);
        // }

        // return redirect()->back()->with('success', 'Producto actualizado exitosamente!');
    }


    public function processOrder(Request $request)
    {

        //   dd($request->all());
        $cart = session()->get('cart');

        if (!$cart || empty($cart)) {
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

        if ($user->hasRole('seller')) {
            $seller_id = $user->id;
            $user_id = session()->get('user_id');
            $actingUser = User::find($user_id) ?: $user;
        }

        // Sync rutero data before processing order to ensure we have current data
        // This handles cases where zone data might have changed in external service
        if ($actingUser && $actingUser->document) {
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

        $delivery_date = OrderRepository::getBusinessDay();

        // After syncing rutero data, re-determine zone_id if needed
        // The sync might have updated zones, so we need to ensure zone_id is still valid
        $zoneId = $request->zone_id ?? session()->get('zone_id');
        
        // Reload zones to ensure we have fresh data after sync
        $actingUser->load('zones');
        
        if ($zoneId) {
            // Verify zone still exists and belongs to acting user
            $zone = Zone::where('id', $zoneId)
                ->where('user_id', $actingUser->id)
                ->first();
            
            // If zone doesn't exist or doesn't belong to user, try to find a valid zone
            if (!$zone && $actingUser->zones->count() > 0) {
                $zoneId = $actingUser->zones->first()->id;
                session()->put('zone_id', $zoneId);
                $zone = Zone::find($zoneId);
            }
        } else {
            // If no zone_id, get first zone from synced zones
            if ($actingUser->zones->count() > 0) {
                $zoneId = $actingUser->zones->first()->id;
                session()->put('zone_id', $zoneId);
                $zone = Zone::find($zoneId);
            } else {
                $zone = null;
            }
        }

        // Inventory validation based on zone/bodega
        $inventoryEnabled = Setting::getByKey('inventory_enabled');
        $isInventoryEnabled = ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true);
        
        // Use zone field only (actual zone number like "933")
        // Note: code field contains CustRuteroID and should NOT be used for zone determination
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
            'all_zones' => $actingUser->zones->map(function($z) {
                return ['id' => $z->id, 'code' => $z->code, 'zone' => $z->zone];
            })->toArray(),
        ]);
        
        $bodega = $isInventoryEnabled ? ZoneWarehouse::getBodegaForZone($zoneCode) : null;
        
        if ($isInventoryEnabled && !$bodega) {
            // Log detailed debugging information
            \Log::warning('Bodega determination failed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'zone_id' => $zoneId,
                'zone_code' => $zoneCode,
                'zone_object' => $zone ? [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'zone' => $zone->zone,
                    'route' => $zone->route,
                ] : null,
                'is_seller' => $user->hasRole('seller'),
                'session_user_id' => session()->get('user_id'),
            ]);
            
            // Attempt fallback: choose the first zone of the acting user that has a mapped bodega
            // Reload zones to ensure we have the latest data after sync
            $actingUser->refresh();
            $actingUser->load('zones');
            
            $fallbackZoneId = null;
            if ($actingUser && $actingUser->zones->count() > 0) {
                \Log::info('Attempting fallback zone selection', [
                    'user_id' => $actingUser->id,
                    'zones_count' => $actingUser->zones->count(),
                    'available_zones' => $actingUser->zones->map(function($z) {
                        // Use zone field only (actual zone number like "933")
                        // Note: code field contains CustRuteroID and should NOT be used for zone determination
                        $zoneCode = $z->zone;
                        $bodega = $zoneCode ? ZoneWarehouse::getBodegaForZone($zoneCode) : null;
                        return [
                            'id' => $z->id,
                            'code' => $z->code, // CustRuteroID (not used for bodega mapping)
                            'zone' => $z->zone, // Actual zone number (used for bodega mapping)
                            'has_bodega' => !is_null($bodega),
                            'bodega' => $bodega,
                        ];
                    })->toArray(),
                ]);
                
                foreach ($actingUser->zones as $candidateZone) {
                    // Use zone field only (actual zone number like "933")
                    // Note: code field contains CustRuteroID and should NOT be used for zone determination
                    $candidateCode = $candidateZone?->zone;
                    if (!$candidateCode) {
                        \Log::debug('Skipping zone without zone field', [
                            'zone_id' => $candidateZone->id,
                            'zone_field' => $candidateZone->zone,
                        ]);
                        continue;
                    }
                    
                    // Check if this zone has a bodega mapping
                    $hasMapping = ZoneWarehouse::getBodegaForZone($candidateCode) !== null;
                    
                    if ($hasMapping) {
                        $fallbackZoneId = $candidateZone->id;
                        \Log::info('Found fallback zone with bodega mapping', [
                            'zone_id' => $fallbackZoneId,
                            'zone_code' => $candidateCode,
                            'bodega' => ZoneWarehouse::getBodegaForZone($candidateCode),
                        ]);
                        break;
                    }
                }
            }
            if ($fallbackZoneId) {
                $zoneId = $fallbackZoneId;
                session()->put('zone_id', $zoneId);
                $zone = Zone::find($zoneId);
                // Use zone field only (actual zone number like "933")
                // Note: code field contains CustRuteroID and should NOT be used for zone determination
                $zoneCode = $zone?->zone;
                $bodega = ZoneWarehouse::getBodegaForZone($zoneCode);
                
                \Log::info('Using fallback zone', [
                    'zone_id' => $zoneId,
                    'zone_code' => $zoneCode,
                    'bodega' => $bodega,
                ]);
            }
            if (!$bodega) {
                // Log final failure with all available mappings for debugging
                $allMappings = ZoneWarehouse::all()->map(function($zw) {
                    return ['zone_code' => $zw->zone_code, 'bodega_code' => $zw->bodega_code];
                })->toArray();
                $configMappings = config('zone_warehouses.mappings', []);
                
                \Log::error('Bodega determination failed - no mapping found', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'zone_id' => $zoneId,
                    'zone_code' => $zoneCode,
                    'acting_user_zones' => $actingUser ? $actingUser->zones->map(function($z) {
                        return ['id' => $z->id, 'code' => $z->code, 'zone' => $z->zone];
                    })->toArray() : null,
                    'db_mappings_count' => count($allMappings),
                    'db_mappings' => $allMappings,
                    'config_mappings_count' => count($configMappings),
                    'config_mappings_keys' => array_keys($configMappings),
                ]);
                
                return back()->with('error', 'No se pudo determinar la bodega para su zona.');
            }
        }

        // Pre-check inventory and safety stock for each item (only when enabled)
        // For products with variations, inventory is checked at the parent product level
        // All variation items of a product share the same inventory pool
        if ($isInventoryEnabled) {
            foreach ($cart as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                if (!$product) {
                    return back()->with('error', 'Producto no encontrado en el carrito.');
                }
                // Skip checks if product opted out
                if (!$product->isInventoryManaged()) {
                    continue;
                }
                // Always check inventory at the parent product level (not at variation level)
                // The cartItem['product_id'] is the parent product, even when cartItem['variation_id'] is set
                $inventory = ProductInventory::where('product_id', $product->id)->where('bodega_code', $bodega)->first();
                $available = (int) ($inventory?->available ?? 0);
                $reserved = (int) ($inventory?->reserved ?? 0);
                $safety = (int) $product->getEffectiveSafetyStock();

                if ($available <= $safety) {
                    \Log::warning('Order blocked: product below safety stock', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'has_variation' => !is_null($product->variation_id),
                        'variation_item_selected' => $cartItem['variation_id'] ?? null,
                        'available' => $available,
                        'safety' => $safety,
                        'bodega' => $bodega
                    ]);
                    return back()->with('error', "{$product->name} está por debajo del stock de seguridad.");
                }
                if ($available <= 5) {
                    \Log::warning('Order blocked: low inventory', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'available' => $available,
                        'bodega' => $bodega
                    ]);
                    return back()->with('error', "El producto {$product->name} tiene inventario insuficiente en su zona.");
                }
                if ($cartItem['quantity'] > ($available - max($reserved, 0))) {
                    \Log::warning('Order blocked: quantity exceeds available', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'requested' => $cartItem['quantity'],
                        'available' => $available,
                        'reserved' => $reserved,
                        'bodega' => $bodega
                    ]);
                    return back()->with('error', "La cantidad solicitada de {$product->name} excede el inventario disponible en su zona.");
                }
            }
        }

        // Check for duplicate orders in the last 3 minutes
        $threeMinutesAgo = now()->subMinutes(3);

        // First, get potential duplicate orders
        $potentialDuplicates = Order::where('user_id', $user_id)
            ->where('zone_id', $request->zone_id)
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
                    !isset($orderProducts[$productId]) ||
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
            // Clear cart and redirect with success message
            if (app()->environment('production')) {
                session()->forget('cart');
            }
            session()->forget('user_id');

            return to_route('home')->with('success', 'Su pedido ya fue procesado exitosamente!');
        }

        // Check if user has previous orders (only if first order discount is enabled)
        $firstOrderDiscountEnabled = config('app.first_order_discount_enabled', true);
        $has_orders = $firstOrderDiscountEnabled ? Order::where('user_id', $user_id)->exists() : false;

        // Check for applied coupon and calculate proper discounts
        $appliedCoupon = session()->get('applied_coupon');
        $couponDiscount = 0;
        $coupon = null;
        $couponResult = null;

        if ($appliedCoupon) {
            $coupon = Coupon::find($appliedCoupon['coupon_id']);
            if ($coupon && $coupon->isValid()) {
                // Use new CouponDiscountService for proper discount calculation
                $couponDiscountService = app(\App\Services\CouponDiscountService::class);
                $couponResult = $couponDiscountService->applyCouponDiscountToProducts(
                    $coupon,
                    User::find($user_id),
                    collect($cart),
                    $has_orders
                );

                if ($couponResult['success']) {
                    $couponDiscount = $couponResult['total_coupon_discount'];
                } else {
                    // Coupon application failed
                    session()->forget('applied_coupon');
                    $appliedCoupon = null;
                }
            } else {
                // Coupon is no longer valid
                session()->forget('applied_coupon');
                $appliedCoupon = null;
            }
        }

        // Use database transaction to ensure atomicity
        DB::beginTransaction();

        try {
            // Use the zone_id determined after rutero sync (ensures current data)
            $order = Order::create([
                'user_id' => $user_id,
                'total' => $total,
                'discount' => $discount,
                'zone_id' => $zoneId, // Use synced zone_id, not request zone_id
                'seller_id' => $seller_id,
                'delivery_date' => $delivery_date,
                'observations' => $observations,
                'coupon_id' => $coupon ? $coupon->id : null,
                'coupon_code' => $coupon ? $coupon->code : null,
                'coupon_discount' => $couponDiscount,
            ]);

            // Create a lookup for modified products from coupon discount service
            $modifiedProductsLookup = [];
            if ($couponResult && $couponResult['success']) {
                foreach ($couponResult['modified_products'] as $modProduct) {
                    $key = $modProduct['product_id'] . '_' . ($modProduct['variation_id'] ?? 'null');
                    $modifiedProductsLookup[$key] = $modProduct;
                }
            }

            // Calculate vendor totals for proper discount application
            // Important: Calculate totals WITHOUT vendor discounts first, then check if minimum is met
            $vendorTotals = [];
            $productsByVendor = [];
            foreach ($cart as $key => $row) {
                $tempProduct = Product::with('brand.vendor')->find($row['product_id']);
                if ($tempProduct && $tempProduct->brand && $tempProduct->brand->vendor) {
                    $vendorId = $tempProduct->brand->vendor->id;

                    // Calculate price for this product
                    $lookupKey = $row['product_id'] . '_' . ($row['variation_id'] ?? 'null');
                    if (isset($modifiedProductsLookup[$lookupKey])) {
                        $productPrice = $modifiedProductsLookup[$lookupKey]['new_unit_price'];
                    } else {
                        // Pass 0 as vendor total to prevent vendor discount from being applied
                        // when calculating the vendor total (prevents circular logic)
                        $priceInfo = $tempProduct->getFinalPriceForUser($has_orders, 0);
                        $productPrice = $priceInfo['price'];
                    }

                    $productTotal = $productPrice * $row['quantity'];

                    if (!isset($vendorTotals[$vendorId])) {
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
                    if (!isset($productQuantities[$productId])) {
                        $productQuantities[$productId] = 0;
                    }
                    
                    // Aggregate quantities across all variations of the same product
                    // If same product appears multiple times with different variation_id, they all count together
                    $packageQuantity = $tempProduct->package_quantity ?? 1;
                    $productQuantities[$productId] += $row['quantity'] * $packageQuantity;
                }
                // Note: If product not found, it will be caught in the main loop below
            }

            foreach ($cart as $key => $row) {
                $id = $row['product_id'];
                $p = Product::with('brand.vendor', 'bonifications')->find($id);
                
                // Skip if product not found (might have been deleted)
                if (!$p) {
                    Log::warning('Product not found during order processing', [
                        'product_id' => $id,
                        'order_id' => $order->id ?? null,
                        'user_id' => $user_id ?? null,
                    ]);
                    DB::rollBack();
                    return back()->with('error', 'Uno de los productos en tu carrito ya no está disponible.');
                }
                
                $lookupKey = $id . '_' . ($row['variation_id'] ?? 'null');

                // Check if this product was modified by coupon discount service
                if (isset($modifiedProductsLookup[$lookupKey])) {
                    $modProduct = $modifiedProductsLookup[$lookupKey];

                    // Use the discount percentage or modified price from coupon service
                    if ($modProduct['applied_discount_type'] === 'fixed_amount') {
                        // For fixed amount discounts, use the new unit price
                        $unitPrice = $modProduct['new_unit_price'];
                        $lineDiscountPercent = 0; // Don't use percentage field for fixed amount
                    } else {
                        // For percentage discounts, use the applied percentage
                        $unitPrice = $modProduct['base_price'];
                        $lineDiscountPercent = (int) ($modProduct['applied_discount_percentage'] ?? 0);
                    }
                } else {
                    // Use original product pricing logic with vendor total for proper discount
                    $vendorId = $p->brand && $p->brand->vendor ? $p->brand->vendor->id : null;
                    $vendorTotal = $vendorId && isset($vendorTotals[$vendorId]) ? $vendorTotals[$vendorId] : null;

                    $lineFinal = $p->getFinalPriceForUser($has_orders, $vendorTotal);
                    $lineDiscountPercent = (int) ($lineFinal['discount'] ?? 0);
                    $unitPrice = $p->finalPrice['originalPrice'];
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
                ]);

                // Decrement inventory (only when enabled)
                // For products with variations, inventory is decremented from the parent product
                // All variation items share the same inventory pool
                if ($isInventoryEnabled && $p->isInventoryManaged()) {
                    // Always lock and update inventory at parent product level
                    // $p->id is the parent product ID, even when $row['variation_id'] is set
                    $inventory = ProductInventory::lockForUpdate()->where('product_id', $p->id)->where('bodega_code', $bodega)->first();
                    $current = (int) ($inventory?->available ?? 0);
                    $reserved = (int) ($inventory?->reserved ?? 0);
                    $safety = (int) $p->getEffectiveSafetyStock();

                    // Ensure after decrement, available won't go below safety
                    if ($current <= 5 || ($current - (int)$row['quantity']) < $safety || $row['quantity'] > ($current - max($reserved, 0))) {
                        \Log::error('Order rollback: inventory insufficient during final check', [
                            'product_id' => $p->id,
                            'product_name' => $p->name,
                            'has_variation' => !is_null($p->variation_id),
                            'variation_item_selected' => $row['variation_id'] ?? null,
                            'requested' => $row['quantity'],
                            'available' => $current,
                            'reserved' => $reserved,
                            'safety' => $safety,
                            'bodega' => $bodega
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
                            'quantity_ordered' => $row['quantity']
                        ]);
                        ProductInventory::create([
                            'product_id' => $p->id,
                            'bodega_code' => $bodega,
                            'available' => max(0, $current - (int) $row['quantity']),
                            'physical' => 0,
                            'reserved' => $reserved,
                        ]);
                    }
                }

                // Process ALL bonifications that apply to this product
                // IMPORTANT: For products with variations, bonifications are checked at the product level
                // and quantities are aggregated across all variations (same product_id, different variation_id)
                // This ensures that buying 5 Small + 5 Large counts as 10 total for bonification purposes
                
                // Get bonifications for this product (product_id in cart is always the parent when variations are involved)
                $bonificationsToCheck = $p->bonifications;
                
                // Process bonifications using aggregated quantities from all variations of this product
                foreach ($bonificationsToCheck as $bonification) {
                    // Use aggregated quantity from all cart items with this product_id
                    // This ensures variations count together for bonifications
                    $aggregatedIndividualItems = $productQuantities[$id] ?? ($row['quantity'] * ($p->package_quantity ?? 1));
                    
                    // Calculate bonification based on aggregated individual items
                    // Example: If customer buys 5 Small + 5 Large (variations) of a product with package_quantity=6
                    // Total = (5+5) * 6 = 60 individual items
                    // If bonification is "buy 10 get 1 free", they get floor(60/10) * 1 = 6 free items
                    $bonification_quantity = floor($aggregatedIndividualItems / $bonification->buy * $bonification->get);

                    // Skip this bonification if the customer doesn't qualify (quantity = 0)
                    if ($bonification_quantity <= 0) {
                        continue;
                    }

                    // Apply maximum limit
                    if ($bonification_quantity > $bonification->max) {
                        $bonification_quantity = $bonification->max;
                    }

                    // Only create bonification record once per bonification (not per variation)
                    // Check if we've already created this bonification for this product in this order
                    $existingBonification = OrderProductBonification::where('order_id', $order->id)
                        ->where('bonification_id', $bonification->id)
                        ->where('product_id', $bonification->product_id)
                        ->first();
                    
                    if (!$existingBonification) {
                        // Create bonification record linked to the first order product of this product
                        // Find the first order product for this product_id
                        $firstOrderProductForProduct = OrderProduct::where('order_id', $order->id)
                            ->where('product_id', $id)
                            ->first();
                        
                        // Use the first order product found, or current one if none found yet
                        $bonificationOrderProductId = $firstOrderProductForProduct ? $firstOrderProductForProduct->id : $orderProduct->id;
                        
                        // Determine variation_item_id for the bonification product
                        // If the bonification product exists in the parent order, use the same variation
                        // Otherwise, use the first variation for the variable product
                        $variationItemId = null;
                        $bonificationProduct = \App\Models\Product::find($bonification->product_id);
                        
                        if ($bonificationProduct && $bonificationProduct->variation_id) {
                            // Check if this product exists in the parent order
                            $existingOrderProduct = OrderProduct::where('order_id', $order->id)
                                ->where('product_id', $bonification->product_id)
                                ->whereNotNull('variation_item_id')
                                ->first();
                            
                            if ($existingOrderProduct) {
                                // Use the same variation from the parent order
                                $variationItemId = $existingOrderProduct->variation_item_id;
                            } else {
                                // Get the first variation item for this product
                                $firstVariationItem = $bonificationProduct->items()
                                    ->wherePivot('enabled', true)
                                    ->orderBy('id')
                                    ->first();
                                
                                if ($firstVariationItem) {
                                    $variationItemId = $firstVariationItem->id;
                                }
                            }
                        }
                        
                        OrderProductBonification::create([
                            'bonification_id' => $bonification->id,
                            'order_product_id' => $bonificationOrderProductId,
                            'product_id' => $bonification->product_id,
                            'variation_item_id' => $variationItemId,
                            'quantity' => $bonification_quantity,
                            'order_id' => $order->id,
                        ]);
                    }
                }


                // Calculate line totals based on whether coupon modified the product
                if (isset($modifiedProductsLookup[$lookupKey])) {
                    $modProduct = $modifiedProductsLookup[$lookupKey];

                    if ($modProduct['applied_discount_type'] === 'fixed_amount') {
                        // For fixed amount: price is already reduced, so total is simply price * quantity * package
                        $lineTotal = $modProduct['new_unit_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                        $lineDiscount = $modProduct['final_discount_amount'];
                    } else {
                        // For percentage: calculate based on base price and percentage
                        $lineSubtotal = $modProduct['base_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                        $lineDiscount = $modProduct['final_discount_amount'];
                        $lineTotal = $lineSubtotal - $lineDiscount;
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

                // Increment sales count for best-selling tracking
                $p->incrementSalesCount($row['quantity']);
            }

            // For users with orders, reset traditional discounts but keep coupon discounts
            if ($has_orders) {
                // Recalculate totals to exclude traditional discounts but keep coupon effects
                $total = 0;
                $discount = 0;

                foreach ($cart as $key => $row) {
                    $p = Product::find($row['product_id']);
                    $lookupKey = $row['product_id'] . '_' . ($row['variation_id'] ?? 'null');

                    if (isset($modifiedProductsLookup[$lookupKey])) {
                        $modProduct = $modifiedProductsLookup[$lookupKey];

                        if ($modProduct['applied_discount_type'] === 'fixed_amount') {
                            $lineTotal = $modProduct['new_unit_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                            $lineDiscount = $modProduct['final_discount_amount'];
                        } else if ($modProduct['applied_discount_type'] === 'coupon') {
                            // Only apply coupon discount, ignore existing discounts
                            $lineSubtotal = $modProduct['base_price'] * $row['quantity'] * ($p->package_quantity ?? 1);
                            $lineDiscount = $modProduct['coupon_contribution'];
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

                    $total += $lineTotal;
                    $discount += $lineDiscount;
                }
            }

            $finalTotal = $total;

            $order->update([
                'total' => $finalTotal,
                'discount' => $discount,
                'coupon_discount' => $couponDiscount,
            ]);

            // Record coupon usage if coupon was applied
            if ($coupon && $couponDiscount > 0) {
                $couponService = app(CouponService::class);
                $couponService->recordCouponUsage($coupon, User::find($user_id), $order, $couponDiscount);
            }

            DB::commit();

            //if env production
            if (app()->environment('production')) {
                session()->forget('cart');
            }

            session()->forget('user_id');
            session()->forget('applied_coupon'); // Clear applied coupon after successful order

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
                    if (!extension_loaded('redis') || !class_exists('Redis')) {
                        Log::warning("Redis extension not available, falling back to database queue");
                        $queueConnection = 'database';
                    }
                } catch (\Exception $e) {
                    Log::warning("Redis check failed, falling back to database queue: " . $e->getMessage());
                    $queueConnection = 'database';
                }
            }

            // Dispatch job on the appropriate connection
            \App\Jobs\ProcessOrderAsync::dispatch($order)->onConnection($queueConnection);

            Log::info("Order {$order->id} created successfully, async processing dispatched on {$queueConnection} queue");

            return to_route('home')->with('success', 'Compra procesada con exito!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: ' . $e->getMessage());
            return to_route('cart')->with('error', 'Error al procesar la orden. Por favor intente nuevamente.');
        }

        // return to_route('home')->with('success', 'Es necesario tener un codigo de cliente para procesar la compra, contacta al administrador!');

        // dispatch(new ProcessOrder($order));
        // 


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
        if (!$user) {
            return redirect()->route('login');
        }

        $cart = session()->get('cart');
        if (!$cart || empty($cart)) {
            return redirect()->route('cart')->with('error', 'El carrito está vacío.');
        }

        // Validate and clean cart data
        $cart = $this->validateCart($cart);
        if (empty($cart)) {
            session()->forget('cart');
            return redirect()->route('cart')->with('error', 'Tu carrito contenía productos inválidos.');
        }

        // Check if there's already a coupon applied
        if (session()->has('applied_coupon')) {
            return redirect()->route('cart')->with('error', 'Ya tienes un cupón aplicado. Remuévelo primero para aplicar otro.');
        }

        $couponCode = trim($request->coupon_code);

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

        // Validate coupon
        $validation = $couponService->validateCoupon($couponCode, $user, $cartProducts, $cartTotal);

        if (!$validation['valid']) {
            return redirect()->route('cart')->with('error', $validation['message']);
        }

        $coupon = $validation['coupon'];

        // Apply coupon to cart
        $application = $couponService->applyCouponToCart($coupon, $user, $cartProducts);

        if (!$application['success']) {
            return redirect()->route('cart')->with('error', $application['message']);
        }

        // Store coupon in session
        session()->put('applied_coupon', [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'discount_amount' => $application['discount_amount'],
            'type' => $coupon->type,
            'value' => $coupon->value,
        ]);

        return redirect()->route('cart')->with('success', "Cupón '{$coupon->code}' aplicado exitosamente. Descuento: $" . number_format($application['discount_amount'], 2));
    }

    /**
     * Remove the applied coupon from the cart
     */
    public function removeCoupon()
    {
        session()->forget('applied_coupon');

        return redirect()->route('cart')->with('success', 'Cupón removido exitosamente.');
    }
}
