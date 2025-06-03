<?php

use App\Http\Controllers\Api\CityController;
use App\Jobs\ProcessImage;
use App\Models\Article;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Category;
use App\Models\Product;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('api')->group(function () {
    Route::get('/categories', function () {
        return Category::active()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();
    });

    Route::get('/cart', function (Request $request) {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return response()->json([
                'items' => [],
                'total_items' => 0
            ]);
        }

        $items = collect($cart)->map(function ($item) {
            $product = Product::with(['brand', 'variation', 'items'])->find($item['product_id']);
            $variation = $product->items->where('id', $item['variation_id'])->first();

            return [
                'id' => $item['product_id'],
                'name' => $product->name . ($variation ? ' - ' . $variation->name : ''),
                'price' => $product->finalPrice['price'] * $item['quantity'],
                'quantity' => $item['quantity'],
                'image' => $product->image
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'total_items' => $items->sum('quantity')
        ]);
    });
});

Route::get('/cities', [CityController::class, 'index'])->name('cities.index');
