<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
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
        $isSeller = $user->hasAnyRole(['seller', 'supervisor']);
        $sellerDashToday = Carbon::now($this->sellerReportTimezone())->format('Y-m-d');

        $recentFilters = $this->extractOrderFilters($request);
        $todayFilters = $this->extractOrderFilters($request, 'today_');

        $orders = $this->buildOrdersQuery($user, $recentFilters, $request)
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();

        if ($isSeller) {
            $todayFilters = $this->normalizeDailyFilters($todayFilters, $sellerDashToday);
        }

        $dailyOrders = $isSeller
            ? $this->buildOrdersQuery($user, $todayFilters, $request)
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'today_page')
                ->withQueryString()
            : null;

        $statuses = [
            '' => 'Todos',
            0 => 'Pendiente',
            1 => 'Procesado',
            2 => 'Error',
            3 => 'Error WS',
        ];

        $accountUser = $user->load(['zones', 'city']);

        return view('clients.orders.index', compact(
            'orders',
            'dailyOrders',
            'statuses',
            'accountUser',
            'isSeller',
            'sellerDashToday',
            'recentFilters',
            'todayFilters'
        ));
    }

    /**
     * @param  array{q:string,order_id:string,from_date:string,to_date:string,status_id:string}  $filters
     */
    private function buildOrdersQuery(User $user, array $filters, Request $request)
    {
        return Order::query()
            ->with(['user', 'products.product.images'])
            ->withCount('products')
            ->withSum('products', 'quantity')
            ->where(function ($query) use ($user) {
                $query->whereBelongsTo($user)
                    ->orWhere('seller_id', $user->id);
            })
            ->whereNot('total', '0')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->whereHas('user', function ($sub) use ($filters) {
                    $sub->where('name', 'ilike', '%' . $filters['q'] . '%');
                });
            })
            ->when($filters['order_id'] !== '', function ($query) use ($filters) {
                $query->whereRaw('CAST(id AS TEXT) ILIKE ?', ['%' . $filters['order_id'] . '%']);
            })
            ->when($filters['from_date'] !== '' && $filters['to_date'] !== '', function ($query) use ($filters, $request) {
                $bounds = $this->utcBoundsFromOrderFilterDates($filters['from_date'], $filters['to_date']);
                if ($bounds) {
                    $query->whereBetween('created_at', [$bounds[0], $bounds[1]]);
                } else {
                    \Log::warning('clients.orders.index: date filter ignored (invalid or unparsable Y-m-d)', [
                        'from_date' => $filters['from_date'],
                        'to_date' => $filters['to_date'],
                        'user_id' => $request->user()?->id,
                    ]);
                }
            })
            ->when($filters['status_id'] !== '', function ($query) use ($filters) {
                $query->where('status_id', (int) $filters['status_id']);
            });
    }

    /**
     * @return array{q:string,order_id:string,from_date:string,to_date:string,status_id:string}
     */
    private function extractOrderFilters(Request $request, string $prefix = ''): array
    {
        return [
            'q' => trim((string) $request->input($prefix . 'q', '')),
            'order_id' => trim((string) $request->input($prefix . 'order_id', '')),
            'from_date' => trim((string) $request->input($prefix . 'from_date', '')),
            'to_date' => trim((string) $request->input($prefix . 'to_date', '')),
            'status_id' => trim((string) $request->input($prefix . 'status_id', '')),
        ];
    }

    /**
     * @param  array{q:string,order_id:string,from_date:string,to_date:string,status_id:string}  $filters
     * @return array{q:string,order_id:string,from_date:string,to_date:string,status_id:string}
     */
    private function normalizeDailyFilters(array $filters, string $today): array
    {
        if ($filters['from_date'] === '' && $filters['to_date'] === '') {
            $filters['from_date'] = $today;
            $filters['to_date'] = $today;
            return $filters;
        }

        if ($filters['from_date'] === '' && $filters['to_date'] !== '') {
            $filters['from_date'] = $filters['to_date'];
        }

        if ($filters['to_date'] === '' && $filters['from_date'] !== '') {
            $filters['to_date'] = $filters['from_date'];
        }

        return $filters;
    }

    /**
     * API endpoint: seller mini-dashboard data (JSON).
     * Accepts optional ?from_date & ?to_date (defaults to today).
     */
    public function sellerDashboard(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->hasAnyRole(['seller', 'supervisor'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        [$fromUtc, $toUtc, $canonicalFrom, $canonicalTo] = $this->resolveSellerDashboardUtcRange($request);

        // ── Row 1: aggregate KPIs ──────────────────────────────
        $ordersQuery = Order::where('seller_id', $user->id)
            ->whereNot('total', '0')
            ->whereBetween('created_at', [$fromUtc, $toUtc]);

        $totalPedidos   = (clone $ordersQuery)->count();
        $ventasTotales  = (clone $ordersQuery)->sum('total');
        $ticketPromedio = $totalPedidos > 0 ? round($ventasTotales / $totalPedidos, 2) : 0;

        // ── Row 2: six fixed sales buckets ───────────────────────
        $baseConditions = fn ($q) => $q
            ->where('orders.seller_id', $user->id)
            ->where('orders.total', '!=', 0)
            ->whereBetween('orders.created_at', [$fromUtc, $toUtc]);

        // Helper: sales total + unit quantity for products in given category IDs.
        // product_id IN (subquery on category_product) counts each order line once; the id list includes
        // descendant categories so pivot rows on child categories match without duplicating lines.
        $salesByCategories = function (array $categoryIds) use ($baseConditions) {
            if (empty($categoryIds)) return ['total' => 0, 'quantity' => 0];
            $row = DB::table('orders')
                ->join('order_products', 'orders.id', '=', 'order_products.order_id')
                ->where(fn ($q) => $baseConditions($q))
                ->whereIn('order_products.product_id', function ($sub) use ($categoryIds) {
                    $sub->select('product_id')
                        ->from('category_product')
                        ->whereIn('category_id', $categoryIds)
                        ->distinct();
                })
                ->selectRaw('COALESCE(SUM(order_products.price * order_products.quantity), 0) as total, COALESCE(SUM(order_products.quantity), 0) as quantity')
                ->first();
            return ['total' => (float) $row->total, 'quantity' => (int) $row->quantity];
        };

        // Helper: sales total + unit quantity for products with given brand IDs
        $salesByBrands = function (array $brandIds) use ($baseConditions) {
            if (empty($brandIds)) return ['total' => 0, 'quantity' => 0];
            $row = DB::table('orders')
                ->join('order_products', 'orders.id', '=', 'order_products.order_id')
                ->join('products', 'order_products.product_id', '=', 'products.id')
                ->where(fn ($q) => $baseConditions($q))
                ->whereIn('products.brand_id', $brandIds)
                ->selectRaw('COALESCE(SUM(order_products.price * order_products.quantity), 0) as total, COALESCE(SUM(order_products.quantity), 0) as quantity')
                ->first();
            return ['total' => (float) $row->total, 'quantity' => (int) $row->quantity];
        };

        // Helper: sales total + unit quantity for products whose brand belongs to given vendor IDs
        $salesByVendors = function (array $vendorIds) use ($baseConditions) {
            if (empty($vendorIds)) return ['total' => 0, 'quantity' => 0];
            $row = DB::table('orders')
                ->join('order_products', 'orders.id', '=', 'order_products.order_id')
                ->join('products', 'order_products.product_id', '=', 'products.id')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->where(fn ($q) => $baseConditions($q))
                ->whereIn('brands.vendor_id', $vendorIds)
                ->selectRaw('COALESCE(SUM(order_products.price * order_products.quantity), 0) as total, COALESCE(SUM(order_products.quantity), 0) as quantity')
                ->first();
            return ['total' => (float) $row->total, 'quantity' => (int) $row->quantity];
        };

        // Resolve IDs by name (case-insensitive) so it works across environments
        $catIdsByName = fn (array $names) => Category::whereRaw(
            'LOWER(name) IN (' . implode(',', array_fill(0, count($names), '?')) . ')',
            array_map('strtolower', $names)
        )->pluck('id')->toArray();

        $brandIdsByName = fn (array $names) => Brand::whereRaw(
            'LOWER(name) IN (' . implode(',', array_fill(0, count($names), '?')) . ')',
            array_map('strtolower', $names)
        )->pluck('id')->toArray();

        $vendorIdsByName = fn (array $names) => Vendor::whereRaw(
            'LOWER(name) IN (' . implode(',', array_fill(0, count($names), '?')) . ')',
            array_map('strtolower', $names)
        )->pluck('id')->toArray();

        // 1–4: category roots (name match) + all descendant categories so products
        // attached only to child categories still count (pivot uses leaf/junction ids).
        $alcalinaIds = $this->categoryIdsWithDescendants($catIdsByName(['Alcalinas Tronex', 'Alcalinas GP']));
        $manganesoIds = $this->categoryIdsWithDescendants($catIdsByName(['Manganeso Tronex', 'Manganeso GP']));
        $encendedoresIds = $this->categoryIdsWithDescendants($catIdsByName(['Encendedores']));
        $bombillosIds = $this->categoryIdsWithDescendants($catIdsByName(['Bombillos']));

        // 5. Otros — brands GP, Mtek, General Electric (+ GE alias), ROCKET
        $otrosBrandIds = $brandIdsByName(['GP', 'Gp', 'Mtek', 'General Electric', 'GE', 'ROCKET']);

        // 6. Terceros — vendors Eterna, Prebel, Yass, Produsa, Dromatic, Sense, Knight
        $tercerosVendorIds = $vendorIdsByName([
            'Eterna', 'Prebel', 'Yass', 'Produsa', 'Dromatic', 'Sense', 'Knight',
        ]);

        $alcalinaSales      = $salesByCategories($alcalinaIds);
        $manganesoSales     = $salesByCategories($manganesoIds);
        $encendedoresSales  = $salesByCategories($encendedoresIds);
        $bombillosSales     = $salesByCategories($bombillosIds);
        $otrosSales         = $salesByBrands($otrosBrandIds);
        $tercerosSales      = $salesByVendors($tercerosVendorIds);

        $buckets = [
            ['label' => 'Alcalina',      'total' => round($alcalinaSales['total'], 2),      'quantity' => $alcalinaSales['quantity']],
            ['label' => 'Manganeso',     'total' => round($manganesoSales['total'], 2),     'quantity' => $manganesoSales['quantity']],
            ['label' => 'Encendedores',  'total' => round($encendedoresSales['total'], 2),  'quantity' => $encendedoresSales['quantity']],
            ['label' => 'Bombillos',     'total' => round($bombillosSales['total'], 2),     'quantity' => $bombillosSales['quantity']],
            ['label' => 'Otros',         'total' => round($otrosSales['total'], 2),         'quantity' => $otrosSales['quantity']],
            ['label' => 'Terceros',      'total' => round($tercerosSales['total'], 2),      'quantity' => $tercerosSales['quantity']],
        ];

        return response()->json([
            'total_pedidos'    => $totalPedidos,
            'ventas_totales'   => round($ventasTotales, 2),
            'ticket_promedio'  => $ticketPromedio,
            'sales_buckets'    => $buckets,
            'from_date' => $canonicalFrom,
            'to_date'   => $canonicalTo,
        ]);
    }

    /**
     * Calendar-day range in seller timezone, as UTC bounds for DB timestamps.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon, 2: string, 3: string}
     */
    private function resolveSellerDashboardUtcRange(Request $request): array
    {
        $tz = $this->sellerReportTimezone();

        if (!$request->filled('from_date') || !$request->filled('to_date')) {
            $local = Carbon::now($tz);
            $fromUtc = $local->copy()->startOfDay()->utc();
            $toUtc = $local->copy()->endOfDay()->utc();
            $d = $local->format('Y-m-d');

            return [$fromUtc, $toUtc, $d, $d];
        }

        $fromStr = (string) $request->from_date;
        $toStr = (string) $request->to_date;
        if (strcmp($fromStr, $toStr) > 0) {
            [$fromStr, $toStr] = [$toStr, $fromStr];
        }

        try {
            $fromUtc = Carbon::createFromFormat('Y-m-d', $fromStr, $tz)->startOfDay()->utc();
            $toUtc = Carbon::createFromFormat('Y-m-d', $toStr, $tz)->endOfDay()->utc();
        } catch (\Throwable) {
            $local = Carbon::now($tz);
            $fromUtc = $local->copy()->startOfDay()->utc();
            $toUtc = $local->copy()->endOfDay()->utc();
            $d = $local->format('Y-m-d');

            return [$fromUtc, $toUtc, $d, $d];
        }

        return [$fromUtc, $toUtc, $fromStr, $toStr];
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    private function utcBoundsFromOrderFilterDates(string $fromDate, string $toDate): ?array
    {
        $tz = $this->sellerReportTimezone();
        if (strcmp($fromDate, $toDate) > 0) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        try {
            $fromUtc = Carbon::createFromFormat('Y-m-d', $fromDate, $tz)->startOfDay()->utc();
            $toUtc = Carbon::createFromFormat('Y-m-d', $toDate, $tz)->endOfDay()->utc();

            return [$fromUtc, $toUtc];
        } catch (\Throwable) {
            return null;
        }
    }

    private function sellerReportTimezone(): string
    {
        return (string) config('app.seller_dashboard_timezone', 'America/Bogota');
    }

    /**
     * Include every descendant category id so pivot rows on child categories match.
     *
     * @param  array<int>  $rootIds
     * @return array<int>
     */
    private function categoryIdsWithDescendants(array $rootIds): array
    {
        $ids = collect($rootIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $frontier = $ids->all();
        while ($frontier !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();
            $frontier = [];
            foreach ($children as $cid) {
                if (!$ids->contains($cid)) {
                    $ids->push($cid);
                    $frontier[] = $cid;
                }
            }
        }

        return $ids->unique()->values()->all();
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

        $order->load([
            'user.city',
            'zone',
            'bonifications' => ['product', 'bonification'],
            'products' => ['product' => ['variation', 'bonifications.product', 'tax']],
        ]);
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
        if ($user->hasAnyRole(['seller', 'supervisor'])) {
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
