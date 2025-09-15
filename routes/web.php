<?php


use App\Http\Controllers\CartController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Seller\PageController as SellerPageController;
use App\Http\Controllers\Shopper\PageController as ShopperPageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Admin\AdminController;



use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/busqueda/{order?}/{category_id?}/{brand_id?}', [PageController::class, 'search'])->name('search');

// Content pages
Route::get('/terminos-y-condiciones', [ContentController::class, 'terms'])->name('content.terms');
Route::get('/politicas-de-privacidad', [ContentController::class, 'privacy'])->name('content.privacy');
Route::get('/preguntas-frecuentes', [ContentController::class, 'faq'])->name('content.faq');

Route::get('/categoria-producto/{slug}', [PageController::class, 'category'])->name('category');
Route::get('/categoria-producto/{slug}/{slug2?}', [PageController::class, 'category'])->name('category2');
Route::get('/categoria-producto/{slug}/{slug2?}/{order?}/{category_id?}/{brand_id?}', [PageController::class, 'category'])->name('category3');

Route::get('/producto/{slug}', [PageController::class, 'product'])->name('product');

Route::get('/etiqueta-producto/{slug}', [PageController::class, 'label'])->name('label');

Route::get('/terms', [PageController::class, 'terms'])->name('terms');


Route::get('/proveedores', [PageController::class, 'brands'])->name('brands');
Route::get('/proveedores/{brand}', [PageController::class, 'brand'])->name('brand');


Route::post('/carrito/agregrar/{product}', [CartController::class, 'add'])->name('cart.add');
Route::get('/cart/remove/{key}', [CartController::class, 'remove'])->name('cart.remove');
Route::patch('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::get('/carrito', [CartController::class, 'cart'])->name('cart');

// Coupon routes
Route::post('/carrito/cupon/aplicar', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
Route::post('/carrito/cupon/remover', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/formulario', [PageController::class, 'form'])->name('form');
Route::post('/formulario', [PageController::class, 'form_post'])->name('form_post');

Route::post('/carrito', [CartController::class, 'processOrder'])->name('cart.process');



Route::middleware(['auth'])->group(function () {
    Route::get('/ordenes', [OrderController::class, 'index'])->name('clients.orders.index');
    Route::get('/ordenes/{order}', [OrderController::class, 'show'])->name('clients.orders.show');
    Route::post('/ordenes/{order}/reorder', [OrderController::class, 'reorder'])->name('clients.orders.reorder');
});

Route::name('sellers.')->prefix('vendedor')->group(function () {
    Route::get('/', [SellerPageController::class, 'home'])->name('home');
    Route::get('/product', [SellerPageController::class, 'product'])->name('product');
    Route::get('/cart', [SellerPageController::class, 'cart'])->name('cart');
    Route::get('/orders', [SellerPageController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}', [SellerPageController::class, 'order'])->name('order');
    Route::get('/faq', [SellerPageController::class, 'faq'])->name('faq');
    Route::get('/contact', [SellerPageController::class, 'contact'])->name('contact');
});


Route::name('shoppers.')->prefix('tendero')->group(function () {
    Route::get('/', [ShopperPageController::class, 'home'])->name('home');
    Route::get('/products', [ShopperPageController::class, 'products'])->name('products');
    Route::get('/cart', [ShopperPageController::class, 'cart'])->name('cart');

    Route::get('/orders', [ShopperPageController::class, 'orders'])->name('orders');
    Route::get('/orders/{id}', [ShopperPageController::class, 'order'])->name('order');


    Route::get('/contact', [ShopperPageController::class, 'contact'])->name('contact');
    Route::get('/reports', [ShopperPageController::class, 'reports'])->name('reports');
});

Route::middleware(['auth'])->group(function () {});

Route::delete('/admins/{id}', [AdminController::class, 'destroy'])->name('admins.destroy');

// Cart API route
Route::get('/api/cart', [CartApiController::class, 'index'])->name('api.cart');

// Temporary debug route for variation investigation
Route::get('/debug-variations/{id}', function ($id) {
    $product = App\Models\Product::with(['variation', 'items.variation'])->find($id);

    if (!$product) {
        return "Product not found";
    }

    return [
        'product_id' => $product->id,
        'product_name' => $product->name,
        'has_variation' => $product->variation ? true : false,
        'variation' => $product->variation ? [
            'id' => $product->variation->id,
            'name' => $product->variation->name
        ] : null,
        'variation_id' => $product->variation_id,
        'items_count' => $product->items->count(),
        'items' => $product->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'variation_name' => $item->variation ? $item->variation->name : null,
                'pivot' => [
                    'price' => $item->pivot->price,
                    'enabled' => $item->pivot->enabled,
                    'sku' => $item->pivot->sku
                ]
            ];
        })
    ];
});

// Temporary fix route for product variation items
Route::get('/fix-product-variations/{id}', function ($id) {
    $product = App\Models\Product::find($id);

    if (!$product) {
        return "Product not found";
    }

    if (!$product->variation_id) {
        return "Product has no variation assigned";
    }

    if ($product->items->count() > 0) {
        return "Product already has variation items";
    }

    $variationItems = App\Models\VariationItem::where('variation_id', $product->variation_id)->get();

    foreach ($variationItems as $item) {
        $product->items()->attach($item->id, [
            'price' => $product->price,
            'sku' => $product->sku . '-' . $item->name,
            'enabled' => true
        ]);
    }

    return [
        'message' => 'Variation items attached successfully',
        'product' => $product->name,
        'items_attached' => $variationItems->count()
    ];
});





require __DIR__ . '/auth.php';
