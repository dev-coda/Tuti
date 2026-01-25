<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BonificationController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\KpiController;
use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductCombinationsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductHighlightController;
use App\Http\Controllers\Admin\PromocionesController;
use App\Http\Controllers\Admin\PromocionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\Admin\VariationItemController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\VolumeDiscountController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\FeaturedProductController;
use App\Http\Controllers\Admin\FeaturedCategoryController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\DeliveryCalendarController;
use App\Http\Controllers\Admin\RouteCycleController;
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

    // KPI Dashboard routes
    Route::prefix('kpi')->name('admin.kpi.')->group(function () {
        Route::get('/', [KpiController::class, 'index'])->name('index');
        Route::get('/export', [KpiController::class, 'export'])->name('export');
    });


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
    Route::get('/orderauditexport', [OrderController::class, 'exportAudit']);
    Route::get('/contactexport', [ContactController::class, 'export']);

    Route::resource('users', UserController::class);
    Route::resource('brands', BrandController::class);
    Route::resource('taxes', TaxController::class);
    Route::resource('holidays', HolidayController::class);
    Route::get('holidays-debug', [HolidayController::class, 'debug'])->name('holidays.debug');
    Route::get('holidays-export', [HolidayController::class, 'export'])->name('holidays.export');
    Route::get('holidays-import', [HolidayController::class, 'showImport'])->name('holidays.import');
    Route::post('holidays-import', [HolidayController::class, 'import'])->name('holidays.import.store');
    Route::resource('products', ProductController::class);
    Route::resource('products.combinations', ProductCombinationsController::class)->only(['store', 'update']);
    Route::resource('categories', CategoryController::class);
    Route::get('categories/{category}/highlights', [ProductHighlightController::class, 'index'])->name('categories.highlights.index');
    Route::post('categories/{category}/highlights', [ProductHighlightController::class, 'store'])->name('categories.highlights.store');
    Route::put('categories/{category}/highlights/{highlight}', [ProductHighlightController::class, 'update'])->name('categories.highlights.update');
    Route::delete('categories/{category}/highlights/{highlight}', [ProductHighlightController::class, 'destroy'])->name('categories.highlights.destroy');
    Route::get('categories/{category}/highlights/search', [ProductHighlightController::class, 'search'])->name('categories.highlights.search');
    Route::post('categories/{category}/highlights/reorder', [ProductHighlightController::class, 'reorder'])->name('categories.highlights.reorder');
    Route::resource('labels', LabelController::class);
    Route::resource('tags', TagController::class);
    Route::post('tags/{tag}/toggle', [TagController::class, 'toggle'])->name('tags.toggle');
    Route::resource('vendors', VendorController::class);
    Route::resource('bonifications', BonificationController::class);
    Route::resource('coupons', CouponController::class);
    Route::post('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');

    // Promociones routes
    Route::prefix('promociones')->name('promociones.')->group(function () {
        Route::get('/', [PromocionesController::class, 'index'])->name('index');
        Route::get('/descuento-directo', [PromocionesController::class, 'descuentoDirecto'])->name('descuento-directo');
        Route::get('/descuento-volumen', [PromocionesController::class, 'descuentoVolumen'])->name('descuento-volumen');
        Route::get('/bonificaciones', [PromocionesController::class, 'bonificaciones'])->name('bonificaciones');
        Route::get('/cupones', [PromocionesController::class, 'cupones'])->name('cupones');
        Route::get('/promociones', [PromocionesController::class, 'promociones'])->name('promociones');
        Route::get('/analisis', [PromocionesController::class, 'analisis'])->name('analisis');
        Route::get('/elements', [PromocionesController::class, 'getElements'])->name('elements');
    });

    // Volume Discount routes
    Route::resource('volume-discounts', VolumeDiscountController::class);

    // Promocion routes
    Route::resource('promocion', PromocionController::class);

    Route::resource('variations', VariationController::class);
    Route::resource('variations.items', VariationItemController::class);

    Route::resource('settings', SettingController::class)->except(['show']);
    Route::post('settings/sync-inventory', [SettingController::class, 'syncInventory'])->name('settings.sync-inventory');
    Route::get('settings/inventory-logs', [SettingController::class, 'inventoryLogs'])->name('settings.inventory-logs');
    Route::post('settings/update-vacation-mode', [SettingController::class, 'updateVacationMode'])->name('settings.update-vacation-mode');
    Route::post('settings/update-global-inventory', [SettingController::class, 'updateGlobalInventory'])->name('settings.update-global-inventory');
    Route::post('settings/update-express-48h', [SettingController::class, 'updateExpress48h'])->name('settings.update-express-48h');
    Route::post('settings/update-force-delivery-date', [SettingController::class, 'updateForceDeliveryDate'])->name('settings.update-force-delivery-date');
    Route::post('settings/process-waiting-orders', [SettingController::class, 'processWaitingOrders'])->name('settings.process-waiting-orders');
    Route::get('settings/mailer-config', [SettingController::class, 'mailer'])->name('settings.mailer');
    Route::post('settings/mailer-config', [SettingController::class, 'updateMailer'])->name('settings.mailer.update');

    // Shipping Methods
    Route::get('shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
    Route::get('shipping-methods/{shippingMethod}/edit', [ShippingMethodController::class, 'edit'])->name('shipping-methods.edit');
    Route::put('shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'update'])->name('shipping-methods.update');
    Route::patch('shipping-methods/{shippingMethod}/toggle', [ShippingMethodController::class, 'toggle'])->name('shipping-methods.toggle');

    // Bulk Operations
    Route::prefix('bulk-operations')->name('bulk-operations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\BulkOperationsController::class, 'index'])->name('index');
        Route::post('/sync-clients-data', [\App\Http\Controllers\Admin\BulkOperationsController::class, 'syncClientsData'])->name('sync-clients-data');
        Route::get('/reports/{filename}/download', [\App\Http\Controllers\Admin\BulkOperationsController::class, 'downloadReport'])->name('download-report');
        Route::delete('/reports/{filename}', [\App\Http\Controllers\Admin\BulkOperationsController::class, 'deleteReport'])->name('delete-report');
    });
    Route::get('settings/zone-warehouses', [SettingController::class, 'zoneWarehouses'])->name('settings.zone-warehouses');
    Route::post('settings/zone-warehouses/sync', [SettingController::class, 'syncZoneWarehouses'])->name('settings.zone-warehouses.sync');
    Route::post('settings/zone-warehouses', [SettingController::class, 'storeZoneWarehouse'])->name('settings.zone-warehouses.store');
    Route::delete('settings/zone-warehouses/{zoneWarehouse}', [SettingController::class, 'destroyZoneWarehouse'])->name('settings.zone-warehouses.destroy');
    
    // Delivery Dates Management
    Route::resource('delivery-calendars', DeliveryCalendarController::class);
    Route::get('delivery-calendars-import', [DeliveryCalendarController::class, 'showImport'])->name('delivery-calendars.import');
    Route::post('delivery-calendars-import', [DeliveryCalendarController::class, 'import'])->name('delivery-calendars.import.store');
    Route::get('delivery-calendars-template', [DeliveryCalendarController::class, 'exportTemplate'])->name('delivery-calendars.template');
    
    Route::resource('route-cycles', RouteCycleController::class);
    Route::get('route-cycles-import', [RouteCycleController::class, 'showImport'])->name('route-cycles.import');
    Route::post('route-cycles-import', [RouteCycleController::class, 'import'])->name('route-cycles.import.store');
    Route::get('route-cycles-template', [RouteCycleController::class, 'exportTemplate'])->name('route-cycles.template');
    Route::post('test-email', function (\Illuminate\Http\Request $request) {
        try {
            // Validate email input
            $request->validate([
                'email' => 'required|email'
            ]);

            $email = $request->input('email');

            // Update mail configuration from database
            $mailingService = app(\App\Services\MailingService::class);
            $mailingService->updateMailConfiguration();

            // Send test email
            \Illuminate\Support\Facades\Mail::raw('Este es un correo de prueba desde Tuti. Si recibes este mensaje, la configuración de correo está funcionando correctamente.', function ($message) use ($email) {
                $message->to($email)
                    ->subject('Prueba de Configuración de Correo - Tuti');
            });

            return response()->json(['success' => true, 'message' => 'Correo enviado exitosamente']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Email inválido'], 422);
        } catch (\Symfony\Component\Mailer\Exception\HttpTransportException $e) {
            // Specific handling for Mailgun/HTTP transport errors
            $errorMessage = $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Mailgun HTTP error: ' . $errorMessage, [
                'trace' => $e->getTraceAsString()
            ]);

            // Parse Mailgun error messages
            if (strpos($errorMessage, '401') !== false || strpos($errorMessage, 'Forbidden') !== false || strpos($errorMessage, 'Unauthorized') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de autenticación con Mailgun (Código 401). Por favor verifica que: 1) Tu API Key de Mailgun sea correcta, 2) Tu dominio esté verificado en Mailgun, 3) La API Key tenga permisos de envío. Puedes obtener tu API Key en: https://app.mailgun.com/settings/api_security'
                ], 500);
            } elseif (strpos($errorMessage, '403') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado por Mailgun. Verifica que tu API Key tenga permisos para enviar desde este dominio.'
                ], 500);
            } else {
                return response()->json(['success' => false, 'message' => 'Error de Mailgun: ' . $errorMessage], 500);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Test email error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'class' => get_class($e)
            ]);

            // Check for common error patterns
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Mailgun') !== false && strpos($errorMessage, 'credenciales') !== false) {
                return response()->json(['success' => false, 'message' => $errorMessage], 500);
            }

            return response()->json(['success' => false, 'message' => 'Error al enviar correo: ' . $errorMessage], 500);
        }
    })->name('test.email');
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

    // Content management routes (Settings-based static content)
    Route::prefix('content')->name('admin.content.')->group(function () {
        Route::get('/', [ContentController::class, 'index'])->name('index');
        Route::get('/{key}/edit', [ContentController::class, 'edit'])->name('edit');
        Route::put('/{key}', [ContentController::class, 'update'])->name('update');
        Route::get('/{key}/show', [ContentController::class, 'show'])->name('show');
    });

    // Content Pages CRUD (Dynamic content pages)
    Route::resource('content-pages', ContentPageController::class);

    // Reports
    Route::prefix('reports')->name('admin.reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
        Route::post('/generate', [App\Http\Controllers\Admin\ReportController::class, 'generate'])->name('generate');
        Route::get('/{report}/download', [App\Http\Controllers\Admin\ReportController::class, 'download'])->name('download');
        Route::get('/{report}/status', [App\Http\Controllers\Admin\ReportController::class, 'status'])->name('status');
        Route::delete('/{report}', [App\Http\Controllers\Admin\ReportController::class, 'destroy'])->name('destroy');
        
        // Daily Sales Report
        Route::get('/daily-sales', [App\Http\Controllers\Admin\ReportController::class, 'dailySales'])->name('daily-sales');
        Route::get('/daily-sales/export', [App\Http\Controllers\Admin\ReportController::class, 'exportDailySales'])->name('daily-sales.export');
    });
    Route::resource('sellers', SellerController::class);


    Route::resource('orders', OrderController::class);
    Route::post('/orders/{order}/resend', [OrderController::class, 'resend'])->name('orders.resend');
    Route::post('/orders/{order}/retry-xml-transmission', [OrderController::class, 'retryXmlTransmission'])->name('orders.retry-xml-transmission');
    Route::post('/orders/{order}/retry-confirmation-email', [OrderController::class, 'retryConfirmationEmail'])->name('orders.retry-confirmation-email');
    Route::post('/orders/{order}/retry-status-email', [OrderController::class, 'retryStatusEmail'])->name('orders.retry-status-email');

    // Monthly exports
    Route::post('/orders/export-monthly', [OrderController::class, 'exportMonthly'])->name('orders.export.monthly');
    Route::get('/exports', [OrderController::class, 'getExports'])->name('admin.exports.list');
    Route::get('/exports/{exportFile}/download', [OrderController::class, 'downloadExport'])->name('admin.exports.download');
    Route::get('/exports/{exportFile}/status', [OrderController::class, 'checkExportStatus'])->name('admin.exports.status');
    Route::resource('contacts', ContactController::class);
    Route::get('email-templates', [App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])->name('admin.email-templates.index');
    Route::get('email-templates/create', [App\Http\Controllers\Admin\EmailTemplateController::class, 'create'])->name('admin.email-templates.create');
    Route::post('email-templates', [App\Http\Controllers\Admin\EmailTemplateController::class, 'store'])->name('admin.email-templates.store');
    Route::get('email-templates/{template}', [App\Http\Controllers\Admin\EmailTemplateController::class, 'show'])->name('admin.email-templates.show');
    Route::get('email-templates/{template}/edit', [App\Http\Controllers\Admin\EmailTemplateController::class, 'edit'])->name('admin.email-templates.edit');
    Route::put('email-templates/{template}', [App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])->name('admin.email-templates.update');
    Route::delete('email-templates/{template}', [App\Http\Controllers\Admin\EmailTemplateController::class, 'destroy'])->name('admin.email-templates.destroy');
    Route::get('email-templates/{template}/preview', [App\Http\Controllers\Admin\EmailTemplateController::class, 'preview'])->name('admin.email-templates.preview');




    Route::get('/profile', [VendorController::class, 'index'])->name('profile.update');
    Route::get('/updateproductprices', [ProductController::class, 'updatePrices']);
});
