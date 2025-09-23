<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Vendor;
use App\Models\Bonification;
use App\Models\Coupon;
use App\Models\Promocion;
use App\Models\VolumeDiscount;
use Illuminate\Http\Request;

class PromocionesController extends Controller
{
    /**
     * Show the main promotions dashboard
     */
    public function index()
    {
        $brands = Brand::with('vendor')->get();
        $vendors = Vendor::all();
        $bonifications = Bonification::withCount('products')->get();
        $coupons = Coupon::withCount('usages')->get();
        $promociones = Promocion::withCount('usages')->get();

        return view('promociones.index', compact(
            'brands',
            'vendors',
            'bonifications',
            'coupons',
            'promociones'
        ));
    }

    /**
     * Show Descuento Directo section
     */
    public function descuentoDirecto()
    {
        $brands = Brand::with('vendor')->get();
        $vendors = Vendor::all();

        return view('promociones.descuento-directo', compact('brands', 'vendors'));
    }

    /**
     * Show Descuento por Volumen section
     */
    public function descuentoVolumen(Request $request)
    {
        $volumeDiscounts = VolumeDiscount::query()
            ->withCount('products')
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('promociones.descuento-volumen', compact('volumeDiscounts'));
    }

    /**
     * Show Bonificaciones section (moved from main menu)
     */
    public function bonificaciones(Request $request)
    {
        $bonifications = Bonification::query()
            ->with('product')
            ->withCount('products')
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate();

        return view('promociones.bonificaciones', compact('bonifications'));
    }

    /**
     * Show Cupones section (moved from main menu)
     */
    public function cupones(Request $request)
    {
        $coupons = Coupon::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('promociones.cupones', compact('coupons'));
    }

    /**
     * Show Promociones section
     */
    public function promociones(Request $request)
    {
        $promociones = Promocion::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return view('promociones.promociones', compact('promociones'));
    }

    /**
     * Show Análisis section
     */
    public function analisis(Request $request)
    {
        // Get date range from request or default to last 30 days
        $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        // Convert string dates to Carbon instances if needed
        if (is_string($startDate)) {
            $startDate = \Carbon\Carbon::parse($startDate)->startOfDay();
        }
        if (is_string($endDate)) {
            $endDate = \Carbon\Carbon::parse($endDate)->endOfDay();
        }

        // Get discount applications analytics
        $discountApplications = \App\Models\DiscountApplication::getAnalytics($startDate, $endDate);
        $totalDiscountsByType = \App\Models\DiscountApplication::getTotalDiscountsByType($startDate, $endDate);
        $topPerformingDiscounts = \App\Models\DiscountApplication::getTopPerformingDiscounts(10, $startDate, $endDate);

        // Get coupon analytics
        $couponPerformance = \App\Models\CouponUsageAnalytic::getCouponPerformance($startDate, $endDate);
        $couponUsageTrends = \App\Models\CouponUsageAnalytic::getUsageTrends($startDate, $endDate, 'day');

        // Get bonification analytics
        $bonificationPerformance = \App\Models\BonificationAnalytic::getBonificationPerformance($startDate, $endDate);
        $popularBonusProducts = \App\Models\BonificationAnalytic::getPopularBonusProducts(10, $startDate, $endDate);

        // Calculate summary statistics
        $totalDiscountAmount = $discountApplications->sum('discount_amount');
        $totalOrders = $discountApplications->pluck('order_id')->unique()->count();
        $totalUsers = $discountApplications->pluck('user_id')->unique()->count();
        $averageDiscountPerOrder = $totalOrders > 0 ? $totalDiscountAmount / $totalOrders : 0;

        // Get recent discount applications for the log
        $recentApplications = \App\Models\DiscountApplication::with(['order', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('promociones.analisis', compact(
            'startDate',
            'endDate',
            'discountApplications',
            'totalDiscountsByType',
            'topPerformingDiscounts',
            'couponPerformance',
            'couponUsageTrends',
            'bonificationPerformance',
            'popularBonusProducts',
            'totalDiscountAmount',
            'totalOrders',
            'totalUsers',
            'averageDiscountPerOrder',
            'recentApplications'
        ));
    }

    /**
     * Get available elements for volume discounts and promotions
     */
    public function getElements(Request $request)
    {
        $type = $request->get('type');
        $search = $request->get('search', '');

        switch ($type) {
            case 'products':
                $elements = \App\Models\Product::where('active', true)
                    ->where('name', 'like', "%{$search}%")
                    ->select('id', 'name', 'sku')
                    ->limit(50)
                    ->get()
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'description' => "SKU: {$product->sku}"
                        ];
                    });
                break;

            case 'categories':
                $elements = \App\Models\Category::where('name', 'like', "%{$search}%")
                    ->select('id', 'name', 'description')
                    ->limit(50)
                    ->get()
                    ->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'description' => $category->description ?: 'Sin descripción'
                        ];
                    });
                break;

            case 'brands':
                $elements = \App\Models\Brand::where('active', true)
                    ->where('name', 'like', "%{$search}%")
                    ->select('id', 'name', 'description')
                    ->limit(50)
                    ->get()
                    ->map(function ($brand) {
                        return [
                            'id' => $brand->id,
                            'name' => $brand->name,
                            'description' => $brand->description ?: 'Sin descripción'
                        ];
                    });
                break;

            case 'vendors':
                $elements = \App\Models\Vendor::where('active', true)
                    ->where('name', 'like', "%{$search}%")
                    ->select('id', 'name', 'vendor_type')
                    ->limit(50)
                    ->get()
                    ->map(function ($vendor) {
                        return [
                            'id' => $vendor->id,
                            'name' => $vendor->name,
                            'description' => "Tipo: {$vendor->vendor_type}"
                        ];
                    });
                break;

            default:
                $elements = collect();
        }

        return response()->json($elements);
    }
}
