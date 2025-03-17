<?php


use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Seller\PageController as SellerPageController;
use App\Http\Controllers\Shopper\PageController as ShopperPageController;


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

Route::get('/formulario', [PageController::class, 'form'])->name('form');
Route::post('/formulario', [PageController::class, 'form_post'])->name('form_post');

Route::post('/carrito', [CartController::class, 'processOrder'])->name('cart.process');



Route::middleware(['auth' ])->group(function () {
    Route::get('/ordenes', [OrderController::class, 'index'])->name('clients.orders.index');
    Route::get('/ordenes/{order}', [OrderController::class, 'show'])->name('clients.orders.show');
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


require __DIR__.'/auth.php';
