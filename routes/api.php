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
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\OrderRepository;
use Carbon\Carbon;

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

Route::get('/products/latest', [ProductsApiController::class, 'latest']);
Route::get('/products/most-sold', [ProductsApiController::class, 'mostSold']);
Route::get('/products/section-title', [ProductsApiController::class, 'getSectionTitle']);

Route::get('/categories/featured', [CategoriesApiController::class, 'featured']);
Route::get('/categories/most-popular', [CategoriesApiController::class, 'mostPopular']);
Route::get('/categories/section-title', [CategoriesApiController::class, 'getSectionTitle']);

// Vacation mode check endpoint
Route::get('/vacation-mode', function () {
    $vacationModeEnabled = \App\Models\Setting::getByKey('vacation_mode_enabled');
    $isVacationMode = ($vacationModeEnabled === '1' || $vacationModeEnabled === 1 || $vacationModeEnabled === true);
    $vacationDate = \App\Models\Setting::getByKey('vacation_mode_date');
    
    if ($isVacationMode && $vacationDate) {
        $formattedDate = \Carbon\Carbon::parse($vacationDate)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $message = "Tuti está de vacaciones. Te esperamos nuevamente {$formattedDate}. ¡Gracias!";
    } else {
        $formattedDate = null;
        $message = null;
    }
    
    return response()->json([
        'enabled' => $isVacationMode,
        'date' => $vacationDate,
        'formatted_date' => $formattedDate,
        'message' => $message,
    ]);
});

// Delivery date calculation endpoint
Route::get('/delivery-date/{method}', function ($method) {
    $zone = null;
    
    // Get zone from session if user is authenticated
    if (auth()->check()) {
        $zoneId = session()->get('zone_id');
        if ($zoneId) {
            $zone = \App\Models\Zone::find($zoneId);
        }
    }
    
    // Also check if zone_id is provided in query string (for zone selection changes)
    if (!$zone && request()->has('zone_id')) {
        $zone = \App\Models\Zone::find(request()->get('zone_id'));
    }
    
    $deliveryDate = OrderRepository::getDeliveryDateByMethod($method, $zone);
    $date = Carbon::parse($deliveryDate);
    
    // Spanish days and months
    $days = [
        "Domingo",
        "Lunes",
        "Martes",
        "Miércoles",
        "Jueves",
        "Viernes",
        "Sábado"
    ];
    
    $months = [
        "Enero",
        "Febrero",
        "Marzo",
        "Abril",
        "Mayo",
        "Junio",
        "Julio",
        "Agosto",
        "Septiembre",
        "Octubre",
        "Noviembre",
        "Diciembre"
    ];
    
    // Format date: Lunes 12 de Julio
    $formattedDate = $days[$date->dayOfWeek] . ' ' . $date->day . ' de ' . $months[$date->month - 1];
    
    return response()->json([
        'date' => $formattedDate,
        'raw_date' => $deliveryDate
    ]);
});

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
