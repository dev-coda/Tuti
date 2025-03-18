<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class OrderController extends Controller
{
    

    public function index(Request $request)
    {
        $orders = Order::query()
        ->when($request->seller_id, function ($query, $seller_id) {
            $query->where('seller_id', $seller_id);
        })

        ->when($request->q, function ($query, $q) {
            $query->whereHas('user', function ($subQuery) use ($q) {
                $subQuery->where('name', 'ilike', "%$q%");
            })->orWhere('id', 'ilike', "%$q%");
        })

        ->when($request->zone, function ($query, $zone) {
            $query->whereHas('user', function ($subQuery) use ($zone) {
                $subQuery->where('zone', $zone);
            });
        })

        ->when($request->filled('from_date') && $request->filled('to_date'), function ($query) use ($request) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        })

        ->with(['user', 'seller'])
        ->withCount('products')
        ->orderByDesc('id')
        ->paginate()
        ->withQueryString();

        $sellers = User::whereHas('roles', function ($query) {
        $query->where('name', 'seller');
        })->pluck('name', 'id')->prepend('Vendedores', '');

        $foo = "cualquier cosa";

        return view('orders.index', compact('orders', 'sellers', 'foo'));
    }

    public function edit(Order $order){

     
        $order->load([
            'user',
            'bonifications' => ['product', 'bonification'],
            'products'=>[
                'product'=>[
                    'variation', 
                    'bonifications.product'
                    ]   
                ]
            ]);        
        $context = compact('order');
        return view('orders.edit', $context);
    }

    public function export()
    {
        return Excel::download(new OrdersExport, 'pedidos.xlsx');
    }

}
