<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class CartApiController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return response()->json([
                'items' => [],
                'total_items' => 0
            ]);
        }

        $items = collect($cart)->map(function ($item) {
            $product = Product::with(['brand', 'variation', 'items'])->find($item['product_id']);
            if (!$product) return null;

            $variation = $product->items->where('id', $item['variation_id'])->first();

            return [
                'id' => $item['product_id'],
                'name' => $product->name . ($variation ? ' - ' . $variation->name : ''),
                'price' => round($product->finalPrice['price'] * $item['quantity']),
                'quantity' => $item['quantity'],
                'image' => asset('storage/' . $product->image)
            ];
        })->filter()->values();

        return response()->json([
            'items' => $items,
            'total_items' => $items->sum('quantity')
        ]);
    }
}
