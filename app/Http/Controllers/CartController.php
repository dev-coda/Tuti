<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrder;
use App\Mail\NewOrderEmail;
use App\Mail\OrderEmail;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Setting;
use App\Repositories\OrderRepository;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CartController extends Controller
{
    public function cart(){
       
        // session()->forget('cart');
        // back();
        

        $cart = session()->get('cart');
        
        if(!$cart){
            return redirect()->route('home');
        }
        
        $user = auth()->user();
        $zones = $user->zones->pluck('address', 'id')->toArray();

        $set_user = false;
        $client = null;
        if($user->hasRole('seller')){
            $user_id = session()->get('user_id');
            $set_user = true;
            if($user_id){
                $client = User::with('zones')->find($user_id);
                $zones = $client->zones->pluck('address', 'id')->toArray();
                $set_user = false;
            }
        }

        $products = [];
        $total_cart = 0;
        
        foreach($cart as $item){
            
            $product = Product::with('brand', 'variation')->find($item['product_id']);
            
            $product->item = $product->items->where('id', $item['variation_id'])->first();
                
            $product->quantity = $item['quantity'];
            $product->vendor_id = $product->brand->vendor->id;
            $products[] = $product;
            $total_cart += $product->quantity * $product->finalPrice['price'];
        }
        $products = collect($products);

        

        //compra minima por vendor
        $byVendors = collect($products)->groupBy('vendor_id');
        $alertVendors = [];
        foreach ($byVendors as $key => $vendor) {
            $total = $vendor->sum(function ($product) {
                return $product->quantity * $product->finalPrice['price'];
            });
            
            $v = Vendor::find($key);
                
            if($total < $v->minimum_purchase){
                $v->current = $total;
                $alertVendors[] = $v;
            }
        }

        $min_amount = Setting::where('key', 'min_amount')->value('value');
        $alertTotal = [];

        if($total_cart < $min_amount){
            $alertTotal[] = true;
        }
        
        $context = compact('products', 'alertVendors', 'zones', 'set_user', 'client', 'alertTotal', 'min_amount', 'total_cart'); 
        
        return view('pages.cart', $context);


      
    }






    #TODO crear plugin de agregar al carrito
    public function add(Request $request, Product $product){

        
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $request->validate([
            'variation_id'=> 'nullable|numeric',
            'quantity' => 'required|numeric',
        ]);

        $product_id = $product->id;
        $variation_id = $request->variation_id;

        $cart = session()->get('cart');
        
           
        if(!$cart){
            $cart[] = [
                "product_id" => $product->id,
                "quantity" => $request->quantity,
                "variation_id" => $request->variation_id,
            ];
            session()->put('cart', $cart);
            return redirect()->back()->with('success', 'Producto agregado al carrito exitosamente!');
        }

        $found_index = null;

        foreach ($cart as $index => $product) {
            if ($product["product_id"] == $product_id && $product["variation_id"] == $variation_id) {
                $found_index = $index;
                break;
            }
        }

        if($found_index === null){
            $cart[] = [
                "product_id" => $product_id,
                "quantity" => $request->quantity,
                "variation_id" => $request->variation_id,
            ];
            session()->put('cart', $cart);
            return redirect()->back()->with('success', 'Producto agregado al carrito exitosamente!');
        }


        $cart[$found_index]['quantity'] = $request->quantity;


        session()->put('cart', $cart);
        return redirect()->back()->with('success', 'Producto agregado al carrito exitosamente!');
    }


    public function remove(Request $request, $key){
        

        $cart = session()->get('cart');

    
        if(isset($cart[$key])) {
            unset($cart[$key]);
            $cart = array_values($cart);

            session()->put('cart', $cart);
        }
        

        return redirect()->back()->with('success', 'Producto eliminado del carrito exitosamente!');
    }


    public function update(Request $request){

        // dd($request->all());

        $cart = session()->get('cart');

        
        
        $items = $request->items;

        

        foreach($items as $key => $item){
            $cart[$key]['quantity'] = $item;
        }

        session()->put('cart', $cart);
        return redirect()->back()->with('success', 'Carrito actualizado exitosamente!');

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


    public function processOrder(Request $request){

     //   dd($request->all());
        $cart = session()->get('cart');
        $observations = $request->observations;
    
        $total = 0;
        $discount = 0;

        $user = auth()->user();
       
        $seller_id = null;
        $user_id = $user->id;
        
        if($user->hasRole('seller')){
            $seller_id = $user->id;
            $user_id = session()->get('user_id');
        }

        $delivery_date = OrderRepository::getBusinessDay(); 
        

        $order = Order::create([
            'user_id' => $user_id,
            'total' => $total,
            'discount' => $discount,
            'zone_id' => $request->zone_id,
            'seller_id' => $seller_id,
            'delivery_date' => $delivery_date,
            'observations' => $observations,
        ]);


        foreach ($cart as $key => $product) {
            
            $id = $product['product_id'];

            $p = Product::find($id);
            
            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $id,
                'quantity' => $product['quantity'],
                'price' => $p->finalPrice['originalPrice'],
                'discount' => $p->finalPrice['totalDiscount'],
                'variation_item_id' => $product['variation_id'] ?? null,
                'percentage' => $p->finalPrice['discount'] ?? 0,
            ]);


            $bonification = $p->bonifications->first();
            if($bonification){
                //  floor($product->pivot->quantity / $product->bonifications->first()->buy)
                $bonification_quantity = floor($product['quantity'] / $bonification->buy * $bonification->get);
                if($bonification_quantity > $bonification->max){
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


            $total = $total + ($p->finalPrice['price'] * $product['quantity']);
            $discount = $discount + ($p->finalPrice['totalDiscount'] * $product['quantity']);      

        }


        $order->update([
            'total' => $total,
            'discount' => $discount,
        ]);

        //if env production
        if(app()->environment('production')){
            session()->forget('cart');
        }

        session()->forget('user_id');

    
       
        try {
            OrderRepository::presalesOrder($order);
        } catch (\Throwable $th) {
            info($th->getMessage());
            return to_route('home')->with('error', 'Error al procesar la compra!');
        }



        $email = $order->user->email;
        try{
            Mail::to($email)->send(new OrderEmail($order));
        }catch(\Exception $e){
            info($e->getMessage());
        }
        
        return to_route('home')->with('success', 'Compra procesada con exito!');
    
        // return to_route('home')->with('success', 'Es necesario tener un codigo de cliente para procesar la compra, contacta al administrador!');

        // dispatch(new ProcessOrder($order));
        // 
        

    }

}
