<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BonificationController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCombinationsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\Admin\VariationItemController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\FeaturedProductController;
use App\Http\Controllers\Admin\FeaturedCategoryController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth', 'role:seller'])->group(function () {
    Route::post('/setclient', [SellerController::class, 'setclient'])->name('seller.setclient');
    Route::post('/removeclient', [SellerController::class, 'removeclient'])->name('seller.removeclient');
});

Route::middleware(['auth', 'role:admin'])->group(function () {


    Route::get('/dashboard', function () {
        return to_route('products.index');
        return view('dashboard');
    })->name('dashboard');


    // Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::post('/products/{product}/images', [ProductController::class, 'images'])->name('products.images');
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'images_delete'])->name('products.images_delete');
    Route::post('/products/{product}/images/reorder', [ProductController::class, 'reorderImages'])->name('products.images_reorder');

    Route::delete('/products/{product}/add_combined', [ProductController::class, 'add_combined'])->name('products.add_combined');
    Route::delete('/products/{product}/sync_combined', [ProductController::class, 'sync_combined'])->name('products.sync_combined');

    Route::get('/products/{product}/combinations{combination}', [ProductCombinationsController::class, 'remove_combination'])->name('products.remove_combination');

    //Route::post('/users/{user}/code', [UserController::class, 'code'])->name('users.code');
    Route::post('/users/{user}/password', [UserController::class, 'password'])->name('users.password');
    Route::get('/userexport', [UserController::class, 'export']);
    Route::get('/sellerexport', [SellerController::class, 'export']);
    Route::get('/productexport', [ProductController::class, 'export']);
    Route::get('/orderexport', [OrderController::class, 'export']);
    Route::get('/contactexport', [ContactController::class, 'export']);

    Route::resource('users', UserController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('taxes', TaxController::class);
    Route::resource('holidays', HolidayController::class);
    Route::resource('products', ProductController::class);
    Route::resource('products.combinations', ProductCombinationsController::class)->only(['store', 'update']);
    Route::resource('categories', CategoryController::class);
    Route::resource('labels', LabelController::class);
    Route::resource('vendors', VendorController::class);
    Route::resource('bonifications', BonificationController::class);

    Route::resource('variations', VariationController::class);
    Route::resource('variations.items', VariationItemController::class);

    Route::resource('settings', SettingController::class);
    Route::post('settings/sync-inventory', [SettingController::class, 'syncInventory'])->name('settings.sync-inventory');
    Route::resource('banners', BannerController::class);
    Route::resource('featured-products', FeaturedProductController::class)->only(['index', 'store', 'destroy']);
    Route::get('featured-products/search', [FeaturedProductController::class, 'search'])->name('featured-products.search');
    Route::post('featured-products/toggle-most-sold', [FeaturedProductController::class, 'toggleMostSold'])->name('featured-products.toggle-most-sold');
    Route::post('featured-products/update-title', [FeaturedProductController::class, 'updateTitle'])->name('featured-products.update-title');
    Route::resource('featured-categories', FeaturedCategoryController::class)->only(['index', 'store', 'destroy']);
    Route::get('featured-categories/search', [FeaturedCategoryController::class, 'search'])->name('featured-categories.search');
    Route::post('featured-categories/toggle-most-popular', [FeaturedCategoryController::class, 'toggleMostPopular'])->name('featured-categories.toggle-most-popular');
    Route::post('featured-categories/update-title', [FeaturedCategoryController::class, 'updateTitle'])->name('featured-categories.update-title');
    Route::post('featured-categories/{featuredCategory}/update-customization', [FeaturedCategoryController::class, 'updateCustomization'])->name('featured-categories.update-customization');
    Route::delete('featured-categories/{featuredCategory}/remove-custom-image', [FeaturedCategoryController::class, 'removeCustomImage'])->name('featured-categories.remove-custom-image');
    Route::resource('admins', AdminController::class);
    Route::resource('sellers', SellerController::class);


    Route::resource('orders', OrderController::class);
    Route::post('/orders/{order}/resend', [OrderController::class, 'resend'])->name('orders.resend');
    Route::resource('contacts', ContactController::class);




    Route::get('/profile', [VendorController::class, 'index'])->name('profile.update');
    Route::get('/updateproductprices', [ProductController::class, 'updatePrices']);
});
