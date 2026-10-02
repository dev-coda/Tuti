<?php

use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CategoriesApiController;
use App\Http\Controllers\Api\ProductsApiController;
use App\Http\Controllers\Api\ClientesApiController;
use App\Http\Controllers\Api\ProductosApiController;
use App\Http\Controllers\Api\PreciosApiController;
use App\Http\Controllers\Api\PromocionesApiController;
use App\Http\Controllers\Api\InventariosApiController;
use App\Http\Controllers\Api\PedidosApiController;
use App\Jobs\ProcessImage;
use App\Models\Article;
use App\Models\Order;
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
            ->with(['children' => function ($query) {
                $query->where('active', 1);
            }])
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
            $product = Product::with(['brand', 'variation', 'items', 'bonifications', 'tax'])->find($item['product_id']);
            if (! $product) {
                return null;
            }
            $variation = $product->items->where('id', $item['variation_id'])->first();
            $variationItemId = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
            $lineGross = $product->getFinalPriceForUser(false, null, $variationItemId)['price'] * (int) $item['quantity'];

            return [
                'id' => $item['product_id'],
                'name' => $product->name.($variation ? ' - '.$variation->name : ''),
                'price' => $lineGross,
                'quantity' => $item['quantity'],
                'image' => $product->image,
            ];
        })->filter()->values();

        return response()->json([
            'items' => $items,
            'total_items' => $items->sum('quantity')
        ]);
    });
});

Route::get('/cities', [CityController::class, 'index'])->name('cities.index');

Route::get('/products/latest', [ProductsApiController::class, 'latest']);
Route::get('/products/most-sold', [ProductsApiController::class, 'mostSold']);
Route::get('/products/section-title', [ProductsApiController::class, 'getSectionTitle']);

Route::get('/categories/featured', [CategoriesApiController::class, 'featured']);
Route::get('/categories/most-popular', [CategoriesApiController::class, 'mostPopular']);
Route::get('/categories/section-title', [CategoriesApiController::class, 'getSectionTitle']);

// Vacation mode check endpoint (checks date range)
Route::get('/vacation-mode', function () {
    $vacationInfo = \App\Models\Setting::getVacationModeInfo();
    
    return response()->json([
        'enabled' => $vacationInfo['enabled'],
        'active' => $vacationInfo['active'], // True if currently within vacation date range
        'from_date' => $vacationInfo['from_date'],
        'date' => $vacationInfo['to_date'],
        'formatted_date' => $vacationInfo['formatted_date'],
        'message' => $vacationInfo['message'],
    ]);
});

// Delivery date lives on the web routes (session). The api group does not
// start the browser session, so sellers were calculated as guests.

// Authenticated API Routes
Route::middleware('auth:sanctum')->group(function () {

    // Clientes (Customers/Users)
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClientesApiController::class, 'index']);
        Route::get('/{client}', [ClientesApiController::class, 'show']);
    });

    // Productos (Products)
    Route::prefix('productos')->group(function () {
        Route::get('/', [ProductosApiController::class, 'index']);
        Route::get('/{product}', [ProductosApiController::class, 'show']);
    });

    // Precios (Prices)
    Route::prefix('precios')->group(function () {
        Route::get('/', [PreciosApiController::class, 'index']);
        Route::get('/{product}', [PreciosApiController::class, 'show']);
    });

    // Promociones (Promotions/Coupons)
    Route::prefix('promociones')->group(function () {
        Route::get('/', [PromocionesApiController::class, 'index']);
        Route::get('/{coupon}', [PromocionesApiController::class, 'show']);
        Route::post('/validar', [PromocionesApiController::class, 'validateCoupon']);
    });

    // Inventarios (Inventory)
    Route::prefix('inventarios')->group(function () {
        Route::get('/', [InventariosApiController::class, 'index']);
        Route::get('/producto/{product}', [InventariosApiController::class, 'show']);
        Route::get('/bodega/{bodegaCode}', [InventariosApiController::class, 'byBodega']);
    });

    // Pedidos (Orders)
    Route::prefix('pedidos')->group(function () {
        Route::get('/', [PedidosApiController::class, 'index']);
        Route::get('/{order}', [PedidosApiController::class, 'show']);
        Route::get('/cliente/{customer}', [PedidosApiController::class, 'byCustomer']);
    });
});
