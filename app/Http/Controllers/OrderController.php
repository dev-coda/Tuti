<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $orders = Order::query()
            ->with(['user'])
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

        return view('clients.orders.index', compact('orders', 'statuses'));
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

        $order->load(['user', 'bonifications' => ['product', 'bonification'], 'products' => ['product' => ['variation', 'bonifications.product']]]);
        $context = compact('order');

        return view('clients.orders.show', $context);
    }

    public function reorder(Request $request, Order $order)
    {
        $user = auth()->user();
        if (!($order->user_id === $user->id || $order->seller_id === $user->id)) {
            abort(403);
        }

        $order->load('products');
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

        session()->put('cart', $cart);
        return back()->with('success', 'Productos de la orden agregados al carrito.');
    }
}
