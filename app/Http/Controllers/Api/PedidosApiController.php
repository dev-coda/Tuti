<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PedidosApiController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'user:id,name,email,document',
            'zone:id,name',
            'seller:id,name,email',
            'coupon:id,code,name',
            'products.product:id,name,sku',
            'products.variationItem:id,name'
        ]);

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->has('seller_id')) {
            $query->where('seller_id', $request->get('seller_id'));
        }

        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->get('zone_id'));
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->get('status_id'));
        }

        if ($request->has('coupon_id')) {
            $query->where('coupon_id', $request->get('coupon_id'));
        }

        // Date filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        if ($request->has('delivery_date_from')) {
            $query->whereDate('delivery_date', '>=', $request->get('delivery_date_from'));
        }

        if ($request->has('delivery_date_to')) {
            $query->whereDate('delivery_date', '<=', $request->get('delivery_date_to'));
        }

        // Amount filters
        if ($request->has('min_total')) {
            $query->where('total', '>=', $request->get('min_total'));
        }

        if ($request->has('max_total')) {
            $query->where('total', '<=', $request->get('max_total'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('document', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['id', 'total', 'created_at', 'delivery_date', 'status_id'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $orders = $query->paginate($perPage);

        // Transform data
        $orders->transform(function ($order) {
            return [
                'id' => $order->id,
                'user' => $order->user,
                'zone' => $order->zone,
                'seller' => $order->seller,
                'total' => $order->total,
                'discount' => $order->discount,
                'status_id' => $order->status_id,
                'status_name' => $this->getStatusName($order->status_id),
                'delivery_date' => $order->delivery_date,
                'observations' => $order->observations,
                'coupon' => $order->coupon,
                'coupon_code' => $order->coupon_code,
                'coupon_discount' => $order->coupon_discount,
                'products_count' => $order->products->count(),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return response()->json([
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load([
            'user:id,name,email,document,phone,city_id',
            'user.city:id,name',
            'zone:id,name',
            'seller:id,name,email',
            'coupon:id,code,name,type,value',
            'products.product:id,name,sku,price',
            'products.variationItem:id,name',
            'bonifications.product:id,name,sku'
        ]);

        $orderData = [
            'id' => $order->id,
            'user' => $order->user,
            'zone' => $order->zone,
            'seller' => $order->seller,
            'total' => $order->total,
            'discount' => $order->discount,
            'status_id' => $order->status_id,
            'status_name' => $this->getStatusName($order->status_id),
            'delivery_date' => $order->delivery_date,
            'observations' => $order->observations,
            'coupon' => $order->coupon,
            'coupon_code' => $order->coupon_code,
            'coupon_discount' => $order->coupon_discount,
            'request' => $order->request,
            'response' => $order->response,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        // Transform order products
        $orderData['products'] = $order->products->map(function ($orderProduct) {
            return [
                'id' => $orderProduct->id,
                'product' => $orderProduct->product,
                'variation_item' => $orderProduct->variationItem,
                'quantity' => $orderProduct->quantity,
                'price' => $orderProduct->price,
                'discount' => $orderProduct->discount,
                'is_bonification' => $orderProduct->is_bonification,
                'percentage' => $orderProduct->percentage,
                'package_quantity' => $orderProduct->package_quantity,
                'subtotal' => $orderProduct->quantity * $orderProduct->price,
            ];
        });

        // Transform bonifications
        $orderData['bonifications'] = $order->bonifications->map(function ($bonification) {
            return [
                'id' => $bonification->id,
                'product' => $bonification->product,
                'quantity' => $bonification->quantity,
            ];
        });

        // Calculate totals
        $orderData['calculations'] = [
            'subtotal' => $order->products->sum(function ($product) {
                return $product->quantity * $product->price;
            }),
            'total_discount' => $order->products->sum('discount') + $order->coupon_discount,
            'products_count' => $order->products->count(),
            'bonifications_count' => $order->bonifications->count(),
        ];

        return response()->json(['data' => $orderData]);
    }

    /**
     * Get orders by customer.
     */
    public function byCustomer(Request $request, User $customer): JsonResponse
    {
        $query = $customer->orders()->with([
            'zone:id,name',
            'seller:id,name,email',
            'coupon:id,code,name',
            'products.product:id,name,sku'
        ]);

        // Apply filters
        if ($request->has('status_id')) {
            $query->where('status_id', $request->get('status_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortBy, ['id', 'total', 'created_at', 'delivery_date'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $orders = $query->paginate($perPage);

        $orders->transform(function ($order) {
            return [
                'id' => $order->id,
                'total' => $order->total,
                'discount' => $order->discount,
                'status_id' => $order->status_id,
                'status_name' => $this->getStatusName($order->status_id),
                'delivery_date' => $order->delivery_date,
                'zone' => $order->zone,
                'seller' => $order->seller,
                'coupon' => $order->coupon,
                'products_count' => $order->products->count(),
                'created_at' => $order->created_at,
            ];
        });

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'document' => $customer->document,
            ],
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * Get status name from status ID.
     */
    private function getStatusName(int $statusId): string
    {
        return match ($statusId) {
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_PROCESED => 'Procesado',
            Order::STATUS_ERROR => 'Error',
            Order::STATUS_ERROR_WEBSERVICE => 'Error Webservice',
            default => 'Desconocido'
        };
    }
}
