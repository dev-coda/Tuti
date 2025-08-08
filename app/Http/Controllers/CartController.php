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
use App\Repositories\OrderRepository;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    public function cart()
    {

        // session()->forget('cart');
        // back();


        $cart = session()->get('cart');

        if (!$cart) {
            return redirect()->route('home');
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

        foreach ($cart as $item) {

            $product = Product::with('brand.vendor', 'variation')->find($item['product_id']);

            $product->item = $product->items->where('id', $item['variation_id'])->first();

            $product->quantity = $item['quantity'];
            $product->vendor_id = $product->brand->vendor->id;

            // Calculate price with user order status
            $finalPrice = $product->getFinalPriceForUser($has_orders);
            $product->calculatedFinalPrice = $finalPrice;

            $products[] = $product;
            $total_cart += $product->quantity * $finalPrice['price'];
        }
        $products = collect($products);



        //compra minima por vendor
        $byVendors = collect($products)->groupBy('vendor_id');
        $alertVendors = [];
        foreach ($byVendors as $key => $vendor) {
            $total = $vendor->sum(function ($product) {
                return $product->quantity * $product->calculatedFinalPrice['price'];
            });

            $v = Vendor::find($key);

            if ($total < $v->minimum_purchase) {
                $v->current = $total;
                $alertVendors[] = $v;
            }
        }

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

        $context = compact('products', 'alertVendors', 'zones', 'set_user', 'client', 'alertTotal', 'min_amount', 'total_cart', 'has_orders');

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

        // Enforce safety stock: prevent adding if below effective safety stock
        $safety = $product->getEffectiveSafetyStock();
        // Determine user bodega from first zone mapping
        $zone = $user->zones()->orderBy('id')->first();
        $zoneCode = $zone?->code ?? $user->zone;
        $bodega = $zoneCode ? ZoneWarehouse::where('zone_code', $zoneCode)->value('bodega_code') : null;
        $available = $bodega ? ($product->inventories()->where('bodega_code', $bodega)->value('available') ?? 0) : 0;
        if ($available <= $safety) {
            return back()->with('error', 'Este producto no está disponible por debajo del stock de seguridad.');
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

        $observations = $request->observations;

        $total = 0;
        $discount = 0;

        $user = auth()->user();

        $seller_id = null;
        $user_id = $user->id;

        if ($user->hasRole('seller')) {
            $seller_id = $user->id;
            $user_id = session()->get('user_id');
        }

        $delivery_date = OrderRepository::getBusinessDay();

        // Inventory validation based on zone/bodega
        $zoneId = $request->zone_id;
        $zone = Zone::find($zoneId);
        $zoneCode = $zone?->code ?? null;
        $bodega = $zoneCode ? ZoneWarehouse::where('zone_code', $zoneCode)->first()?->bodega_code : null;
        if (!$bodega) {
            return back()->with('error', 'No se pudo determinar la bodega para su zona.');
        }

        // Pre-check inventory and safety stock for each item
        foreach ($cart as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            if (!$product) {
                return back()->with('error', 'Producto no encontrado en el carrito.');
            }
            $inventory = ProductInventory::where('product_id', $product->id)->where('bodega_code', $bodega)->first();
            $available = (int) ($inventory?->available ?? 0);
            $reserved = (int) ($inventory?->reserved ?? 0);
            $safety = (int) $product->getEffectiveSafetyStock();

            if ($available <= $safety) {
                return back()->with('error', "{$product->name} está por debajo del stock de seguridad.");
            }
            if ($available <= 5) {
                return back()->with('error', "El producto {$product->name} tiene inventario insuficiente en su zona.");
            }
            if ($cartItem['quantity'] > ($available - max($reserved, 0))) {
                return back()->with('error', "La cantidad solicitada de {$product->name} excede el inventario disponible en su zona.");
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

        // Use database transaction to ensure atomicity
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user_id,
                'total' => $total,
                'discount' => $discount,
                'zone_id' => $request->zone_id,
                'seller_id' => $seller_id,
                'delivery_date' => $delivery_date,
                'observations' => $observations,
            ]);

            foreach ($cart as $key => $row) {
                $id = $row['product_id'];
                $p = Product::find($id);

                $orderProduct = OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $id,
                    'quantity' => $row['quantity'],
                    'price' => $p->finalPrice['originalPrice'],
                    'discount' => 0,
                    'variation_item_id' => $row['variation_id'] ?? null,
                    'percentage' => 0,
                    'package_quantity' => $p->package_quantity ?? 1,
                ]);

                // Decrement inventory for bodega with safety stock enforcement
                $inventory = ProductInventory::lockForUpdate()->where('product_id', $p->id)->where('bodega_code', $bodega)->first();
                $current = (int) ($inventory?->available ?? 0);
                $reserved = (int) ($inventory?->reserved ?? 0);
                $safety = (int) $p->getEffectiveSafetyStock();

                // Ensure after decrement, available won't go below safety
                if ($current <= 5 || ($current - (int)$row['quantity']) < $safety || $row['quantity'] > ($current - max($reserved, 0))) {
                    DB::rollBack();
                    return back()->with('error', "Inventario insuficiente para {$p->name} en su zona.");
                }
                if ($inventory) {
                    $inventory->update(['available' => $current - (int) $row['quantity']]);
                } else {
                    // shouldn't happen, but guard
                    ProductInventory::create([
                        'product_id' => $p->id,
                        'bodega_code' => $bodega,
                        'available' => max(0, $current - (int) $row['quantity']),
                        'physical' => 0,
                        'reserved' => $reserved,
                    ]);
                }

                $bonification = $p->bonifications->first();
                if ($bonification) {
                    //  floor($product->pivot->quantity / $product->bonifications->first()->buy)
                    $bonification_quantity = floor($row['quantity'] / $bonification->buy * $bonification->get);
                    if ($bonification_quantity > $bonification->max) {
                        $bonification_quantity = $bonification->max;
                    }

                    OrderProductBonification::create([
                        'bonification_id' => $bonification->id,
                        'order_product_id' => $orderProduct->id,
                        'product_id' => $bonification->product_id,
                        'quantity' => $bonification_quantity,
                        'order_id' => $order->id,
                    ]);
                }


                $total = $total + ($p->finalPrice['price'] * $row['quantity']);
                $discount = $discount + ($p->finalPrice['totalDiscount'] * $row['quantity']);
            }

            $discount = $has_orders ? 0 : $discount;

            $order->update([
                'total' => $total,
                'discount' => $discount,
            ]);

            DB::commit();

            //if env production
            if (app()->environment('production')) {
                session()->forget('cart');
            }

            session()->forget('user_id');



            try {
                OrderRepository::presalesOrder($order);
            } catch (\Throwable $th) {
                Log::error('Error in presalesOrder: ' . $th->getMessage());
                info($th->getMessage());
                // Don't return error here as the order is already created
            }

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
}
