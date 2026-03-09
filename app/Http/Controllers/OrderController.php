<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Repositories\UserRepository;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $orders = Order::query()
            ->with(['user', 'products.product.images'])
            ->withCount('products')
            ->withSum('products', 'quantity')
            ->where(function ($query) use ($user) {
                $query->whereBelongsTo($user)
                    ->orWhere('seller_id', $user->id);
            })
            ->whereNot('total', '0')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->q;
                $query->whereHas('user', function ($sub) use ($q) {
                    $sub->where('name', 'ilike', "%$q%");
                });
            })
            ->when($request->filled('order_id'), function ($query) use ($request) {
                $idq = trim((string) $request->order_id);
                // Cast id to text for ILIKE partial search (PostgreSQL)
                $query->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$idq}%"]);
            })
            ->when($request->filled('from_date') && $request->filled('to_date'), function ($query) use ($request) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->from_date)->startOfDay(),
                    Carbon::parse($request->to_date)->endOfDay(),
                ]);
            })
            ->when($request->filled('status_id') || $request->status_id === '0', function ($query) use ($request) {
                if ($request->status_id !== '') {
                    $query->where('status_id', (int) $request->status_id);
                }
            })
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();

        $statuses = [
            '' => 'Todos',
            0 => 'Pendiente',
            1 => 'Procesado',
            2 => 'Error',
            3 => 'Error WS',
        ];

        $accountUser = $user->load(['zones', 'city']);
        $isSeller = $user->hasRole('seller');

        return view('clients.orders.index', compact('orders', 'statuses', 'accountUser', 'isSeller'));
    }

    /**
     * API endpoint: seller mini-dashboard data (JSON).
     * Accepts optional ?from_date & ?to_date (defaults to today).
     */
    public function sellerDashboard(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->hasRole('seller')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $from = $request->filled('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : Carbon::today();

        $to = $request->filled('to_date')
            ? Carbon::parse($request->to_date)->endOfDay()
            : Carbon::today()->endOfDay();

        // ── Row 1: aggregate KPIs ──────────────────────────────
        $ordersQuery = Order::where('seller_id', $user->id)
            ->whereNot('total', '0')
            ->whereBetween('created_at', [$from, $to]);

        $totalPedidos   = (clone $ordersQuery)->count();
        $ventasTotales  = (clone $ordersQuery)->sum('total');
        $ticketPromedio = $totalPedidos > 0 ? round($ventasTotales / $totalPedidos, 2) : 0;

        // ── Row 2: sales per configured categories ─────────────
        $configuredIds = json_decode(Setting::getByKey('seller_dashboard_categories') ?? '[]', true);

        // Fall back to top-5 active root categories when nothing is configured
        if (empty($configuredIds)) {
            $configuredIds = Category::active()
                ->whereNull('parent_id')
                ->orderBy('name')
                ->limit(5)
                ->pluck('id')
                ->toArray();
        }

        // Get sales for the configured categories
        $categorySales = collect();
        if (!empty($configuredIds)) {
            $salesData = DB::table('orders')
                ->join('order_products', 'orders.id', '=', 'order_products.order_id')
                ->join('category_product', 'order_products.product_id', '=', 'category_product.product_id')
                ->join('categories', 'category_product.category_id', '=', 'categories.id')
                ->where('orders.seller_id', $user->id)
                ->where('orders.total', '!=', 0)
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereIn('categories.id', $configuredIds)
                ->select(
                    'categories.id',
                    'categories.name',
                    DB::raw('COALESCE(SUM(order_products.price * order_products.quantity), 0) as total_sales')
                )
                ->groupBy('categories.id', 'categories.name')
                ->get()
                ->keyBy('id');

            // Build final list preserving configured order, showing $0 for categories with no sales
            $categorySales = collect($configuredIds)->map(function ($id) use ($salesData) {
                if ($salesData->has($id)) {
                    return $salesData->get($id);
                }
                $cat = Category::find($id);
                return (object) [
                    'id'          => $id,
                    'name'        => $cat->name ?? 'Categoría',
                    'total_sales' => 0,
                ];
            });
        }

        return response()->json([
            'total_pedidos'    => $totalPedidos,
            'ventas_totales'   => round($ventasTotales, 2),
            'ticket_promedio'  => $ticketPromedio,
            'category_sales'   => $categorySales->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name,
                'total' => round((float) $c->total_sales, 2),
            ])->values(),
            'from_date' => $from->toDateString(),
            'to_date'   => $to->toDateString(),
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();

        $order = Order::query()
            ->with('user')
            ->withCount('products')
            ->whereBelongsTo($user)
            ->where('id', $id)
            ->first();

        if (!$order) {
            $order = Order::query()
                ->with('user')
                ->withCount('products')
                ->where('seller_id', $user->id)
                ->where('id', $id)
                ->first();
        }

        if (!$order) {
            $order = Order::query()
                ->with('user')
                ->withCount('products')
                ->where('seller_id', $user->id)
                ->orderByDesc('id')
                ->first();
        }

        if (!$order) {
            return redirect()->route('clients.orders.index');
        }

        $order->load(['user', 'bonifications' => ['product', 'bonification'], 'products' => ['product' => ['variation', 'bonifications.product', 'tax']]]);
        $context = compact('order');

        return view('clients.orders.show', $context);
    }

    public function reorder(Request $request, Order $order)
    {
        $user = auth()->user();
        if (!($order->user_id === $user->id || $order->seller_id === $user->id)) {
            abort(403);
        }

        $order->load(['products', 'user.zones']);
        $cart = session()->get('cart', []);

        foreach ($order->products as $orderProduct) {
            $product = $orderProduct->product; // already has relation in show; for safety fetch via relation
            if (!$product || !$product->active) {
                continue;
            }

            $variationId = $orderProduct->variation_item_id;
            $quantity = (int) $orderProduct->quantity;

            $foundIndex = null;
            foreach ($cart as $index => $line) {
                if ((int)$line['product_id'] === (int)$product->id && (int)($line['variation_id'] ?? 0) === (int)($variationId ?? 0)) {
                    $foundIndex = $index;
                    break;
                }
            }

            if ($foundIndex === null) {
                $cart[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'variation_id' => $variationId,
                ];
            } else {
                $cart[$foundIndex]['quantity'] = $cart[$foundIndex]['quantity'] + $quantity;
            }
        }

        // Persist cart
        session()->put('cart', $cart);

        // Preserve client and zone from the original order for confirmation step
        // If current user is a seller, set the acting client for the cart
        if ($user->hasRole('seller')) {
            session()->put('user_id', $order->user_id);

            // Ensure client zones are available (run SOAP-backed sync if necessary)
            $client = $order->user;
            $client->loadMissing('zones');
            $needsZoneSync = $client->zones->count() === 0;

            // Also sync if original zone has no mapped bodega
            $selectedZoneId = $order->zone_id ?: session()->get('zone_id');
            $selectedZone = $selectedZoneId ? Zone::find($selectedZoneId) : null;
            $hasMappedBodega = $selectedZone && $selectedZone->code && ZoneWarehouse::where('zone_code', $selectedZone->code)->exists();
            if ($needsZoneSync || !$hasMappedBodega) {
                try {
                    $data = UserRepository::getCustomRuteroId($client->document);
                    if ($data && isset($data['routes'])) {
                        $existingZones = $client->zones()->get();
                        $newRoutes = $data['routes'];
                        foreach ($newRoutes as $index => $route) {
                            $zoneToUpdate = $existingZones[$index] ?? null;
                            if ($zoneToUpdate) {
                                $zoneToUpdate->update([
                                    'route' => $route['route'],
                                    'zone' => $route['zone'],
                                    'day' => $route['day'],
                                    'address' => $route['address'],
                                    'code' => $route['code'],
                                ]);
                            } else {
                                $client->zones()->create([
                                    'route' => $route['route'],
                                    'zone' => $route['zone'],
                                    'day' => $route['day'],
                                    'address' => $route['address'],
                                    'code' => $route['code'],
                                ]);
                            }
                        }
                        $client->refresh();
                    }
                } catch (\Throwable $th) {
                    // If sync fails, proceed with whatever data exists
                }

                // Re-evaluate selected zone; if invalid, pick first zone with mapped bodega
                $selectedZone = $selectedZoneId ? Zone::find($selectedZoneId) : null;
                $hasMappedBodega = $selectedZone && $selectedZone->code && ZoneWarehouse::where('zone_code', $selectedZone->code)->exists();
                if (!$hasMappedBodega) {
                    $zoneIdWithBodega = null;
                    foreach ($client->zones as $z) {
                        if ($z->code && ZoneWarehouse::where('zone_code', $z->code)->exists()) {
                            $zoneIdWithBodega = $z->id;
                            break;
                        }
                    }
                    if ($zoneIdWithBodega) {
                        session()->put('zone_id', $zoneIdWithBodega);
                    }
                }
            }
        } else {
            // Non-sellers: ensure zone_id persists from the original order if present
            if (!empty($order->zone_id)) {
                session()->put('zone_id', (int) $order->zone_id);
            }
        }

        // Go to order confirmation screen (cart)
        return redirect()->route('cart')->with('success', 'Productos de la orden agregados al carrito.');
    }
}
