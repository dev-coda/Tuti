<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {


        $user = auth()->user();


        $orders = Order::with('user')
            ->withCount('products')
            ->withSum('products', 'quantity')
            ->whereBelongsTo($user)
            ->orwhere('seller_id', $user->id)
            ->whereNot('total', '0')
            ->orderByDesc('id')
            ->paginate();
        $context = compact('orders');

        return view('clients.orders.index', $context);
    }

    public function show($id)
    {


        $user = auth()->user();

        $order = Order::query()
            ->with('user')
            ->withCount('products')
            ->whereBelongsTo($user)
            //->orwhere('seller_id', $user->id)
            //->orderByDesc('id')
            ->where('id', $id)
            ->first();

        if (!$order) {
            $order = Order::query()
                ->with('user')
                ->withCount('products')
                ->where('seller_id', $user->id)
                ->where('id', $id)
                ->first();
        }

        if (!$order) {
            $order = Order::query()
                ->with('user')
                ->withCount('products')
                ->where('seller_id', $user->id)
                ->orderByDesc('id')
                ->first();
        }

        if (!$order) {
            return redirect()->route('clients.orders.index');
        }

        $order->load(['user', 'bonifications' => ['product', 'bonification'], 'products' => ['product' => ['variation', 'bonifications.product']]]);
        $context = compact('order');

        return view('clients.orders.show', $context);
    }
}
