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

        $loaded = [];
        foreach ($cart as $item) {
            $product = Product::with(['brand.vendor', 'variation', 'items', 'bonifications', 'tax'])->find($item['product_id']);
            if (! $product) {
                continue;
            }
            $loaded[] = [$item, $product];
        }

        // Totals before brand/vendor discounts, so a purchase minimum (e.g. 20.000 COP) is checked on the real subtotal.
        $vendorTotals = [];
        foreach ($loaded as [$item, $product]) {
            $vendorId = $product->brand?->vendor?->id;
            if (! $vendorId) {
                continue;
            }
            $variationItemId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $preDiscount = $product->getFinalPriceForUser(false, 0, $variationItemId)['price'];
            $vendorTotals[$vendorId] = ($vendorTotals[$vendorId] ?? 0) + ($preDiscount * (int) $item['quantity']);
        }

        $items = collect($loaded)->map(function ($pair) use ($vendorTotals) {
            [$item, $product] = $pair;
            $variation = $product->items->where('id', $item['variation_id'])->first();
            $variationItemId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $vendorId = $product->brand?->vendor?->id;
            $vendorTotal = $vendorId ? ($vendorTotals[$vendorId] ?? 0) : 0;
            $lineGross = $product->getFinalPriceForUser(false, $vendorTotal, $variationItemId)['price'] * (int) $item['quantity'];

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
