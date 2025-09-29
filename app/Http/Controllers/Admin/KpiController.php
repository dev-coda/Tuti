<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Category;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiController extends Controller
{
    /**
     * Show the KPI dashboard
     */
    public function index(Request $request)
    {
        // Get date range from request or default to last 12 months to show historical data
        $startDate = $request->get('start_date', now()->subYear()->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        // Convert string dates to Carbon instances if needed
        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate)->startOfDay();
        }
        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        // Get main KPIs
        $kpis = $this->getMainKpis($startDate, $endDate);

        // Get sales trends
        $salesTrends = $this->getSalesTrends($startDate, $endDate);

        // Get product statistics
        $productStats = $this->getProductStats($startDate, $endDate);

        // Get category statistics
        $categoryStats = $this->getCategoryStats($startDate, $endDate);

        // Get zone statistics
        $zoneStats = $this->getZoneStats($startDate, $endDate);

        return view('admin.kpi.index', compact(
            'startDate',
            'endDate',
            'kpis',
            'salesTrends',
            'productStats',
            'categoryStats',
            'zoneStats'
        ));
    }

    /**
     * Get main KPIs
     */
    private function getMainKpis($startDate, $endDate)
    {
        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED]);

        $totalOrders = $orders->count();
        $grossSales = $orders->sum('total');
        $totalDiscount = $orders->sum('discount');
        $netSales = $grossSales - $totalDiscount;

        // Get total units sold
        $totalUnits = OrderProduct::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED]);
        })->sum('quantity');

        // Calculate average ticket
        $averageTicket = $totalOrders > 0 ? $netSales / $totalOrders : 0;

        // Get unique customers
        $uniqueCustomers = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->distinct('user_id')
            ->count('user_id');

        return [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'total_discount' => $totalDiscount,
            'total_orders' => $totalOrders,
            'total_units' => $totalUnits,
            'average_ticket' => $averageTicket,
            'unique_customers' => $uniqueCustomers,
        ];
    }

    /**
     * Get sales trends data
     */
    private function getSalesTrends($startDate, $endDate)
    {
        $trends = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as orders_count'),
            DB::raw('SUM(total) as gross_sales'),
            DB::raw('SUM(total - discount) as net_sales'),
            DB::raw('SUM(discount) as total_discount')
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $trends;
    }

    /**
     * Get product statistics
     */
    private function getProductStats($startDate, $endDate)
    {
        $productStats = OrderProduct::select(
            'products.id',
            'products.name',
            'products.sku',
            DB::raw('SUM(order_products.quantity) as units_sold'),
            DB::raw('SUM(order_products.price * order_products.quantity) as gross_revenue'),
            DB::raw('SUM((order_products.price - order_products.discount) * order_products.quantity) as net_revenue'),
            DB::raw('COUNT(DISTINCT order_products.order_id) as orders_count'),
            DB::raw('AVG(order_products.price) as avg_price')
        )
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereIn('orders.status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('units_sold', 'desc')
            ->limit(20)
            ->get();

        return $productStats;
    }

    /**
     * Get category statistics
     */
    private function getCategoryStats($startDate, $endDate)
    {
        $categoryStats = OrderProduct::select(
            'categories.id',
            'categories.name',
            DB::raw('SUM(order_products.quantity) as units_sold'),
            DB::raw('SUM(order_products.price * order_products.quantity) as gross_revenue'),
            DB::raw('SUM((order_products.price - order_products.discount) * order_products.quantity) as net_revenue'),
            DB::raw('COUNT(DISTINCT order_products.order_id) as orders_count'),
            DB::raw('COUNT(DISTINCT order_products.product_id) as products_count')
        )
            ->join('products', 'order_products.product_id', '=', 'products.id')
            ->join('orders', 'order_products.order_id', '=', 'orders.id')
            ->join('category_product', 'products.id', '=', 'category_product.product_id')
            ->join('categories', 'category_product.category_id', '=', 'categories.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereIn('orders.status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('units_sold', 'desc')
            ->limit(15)
            ->get();

        return $categoryStats;
    }

    /**
     * Get zone statistics
     */
    private function getZoneStats($startDate, $endDate)
    {
        $zoneStats = Order::select(
            'zones.id',
            'zones.zone',
            'zones.route',
            DB::raw('COUNT(*) as orders_count'),
            DB::raw('SUM(total) as gross_sales'),
            DB::raw('SUM(total - discount) as net_sales'),
            DB::raw('SUM(discount) as total_discount'),
            DB::raw('AVG(total) as avg_order_value'),
            DB::raw('COUNT(DISTINCT orders.user_id) as unique_customers')
        )
            ->join('zones', 'orders.zone_id', '=', 'zones.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereIn('orders.status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->groupBy('zones.id', 'zones.zone', 'zones.route')
            ->orderBy('orders_count', 'desc')
            ->limit(15)
            ->get();

        return $zoneStats;
    }

    /**
     * Export KPI data to CSV
     */
    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->subYear()->startOfDay());
        $endDate = $request->get('end_date', now()->endOfDay());

        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate)->startOfDay();
        }
        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        $kpis = $this->getMainKpis($startDate, $endDate);
        $productStats = $this->getProductStats($startDate, $endDate);
        $categoryStats = $this->getCategoryStats($startDate, $endDate);
        $zoneStats = $this->getZoneStats($startDate, $endDate);

        $filename = 'kpi_report_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($kpis, $productStats, $categoryStats, $zoneStats) {
            $file = fopen('php://output', 'w');

            // Main KPIs
            fputcsv($file, ['MAIN KPIs']);
            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['Gross Sales', '$' . number_format($kpis['gross_sales'], 2)]);
            fputcsv($file, ['Net Sales', '$' . number_format($kpis['net_sales'], 2)]);
            fputcsv($file, ['Total Discount', '$' . number_format($kpis['total_discount'], 2)]);
            fputcsv($file, ['Total Orders', $kpis['total_orders']]);
            fputcsv($file, ['Total Units Sold', $kpis['total_units']]);
            fputcsv($file, ['Average Ticket', '$' . number_format($kpis['average_ticket'], 2)]);
            fputcsv($file, ['Unique Customers', $kpis['unique_customers']]);
            fputcsv($file, []);

            // Product Stats
            fputcsv($file, ['TOP PRODUCTS']);
            fputcsv($file, ['Product Name', 'SKU', 'Units Sold', 'Gross Revenue', 'Net Revenue', 'Orders Count', 'Avg Price']);
            foreach ($productStats as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->sku,
                    $product->units_sold,
                    '$' . number_format($product->gross_revenue, 2),
                    '$' . number_format($product->net_revenue, 2),
                    $product->orders_count,
                    '$' . number_format($product->avg_price, 2)
                ]);
            }
            fputcsv($file, []);

            // Category Stats
            fputcsv($file, ['TOP CATEGORIES']);
            fputcsv($file, ['Category Name', 'Units Sold', 'Gross Revenue', 'Net Revenue', 'Orders Count', 'Products Count']);
            foreach ($categoryStats as $category) {
                fputcsv($file, [
                    $category->name,
                    $category->units_sold,
                    '$' . number_format($category->gross_revenue, 2),
                    '$' . number_format($category->net_revenue, 2),
                    $category->orders_count,
                    $category->products_count
                ]);
            }
            fputcsv($file, []);

            // Zone Stats
            fputcsv($file, ['TOP ZONES']);
            fputcsv($file, ['Zone', 'Route', 'Orders Count', 'Gross Sales', 'Net Sales', 'Total Discount', 'Avg Order Value', 'Unique Customers']);
            foreach ($zoneStats as $zone) {
                fputcsv($file, [
                    $zone->zone,
                    $zone->route,
                    $zone->orders_count,
                    '$' . number_format($zone->gross_sales, 2),
                    '$' . number_format($zone->net_sales, 2),
                    '$' . number_format($zone->total_discount, 2),
                    '$' . number_format($zone->avg_order_value, 2),
                    $zone->unique_customers
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
