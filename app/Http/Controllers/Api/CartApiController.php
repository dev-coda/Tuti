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
            $product = Product::with(['brand.vendor', 'variation', 'items', 'bonifications', 'tax'])->find($item['product_id']);
            if (!$product) {
                return null;
            }

            $variation = $product->items->where('id', $item['variation_id'])->first();
            $variationItemId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $lineGross = $product->getFinalPriceForUser(false, null, $variationItemId)['price'] * (int) $item['quantity'];

            return [
                'id' => $item['product_id'],
                'name' => $product->name.($variation ? ' - '.$variation->name : ''),
                'price' => round($lineGross),
                'quantity' => $item['quantity'],
                'image' => asset('storage/'.$product->image),
            ];
        })->filter()->values();

        return response()->json([
            'items' => $items,
            'total_items' => $items->sum('quantity')
        ]);
    }
}
