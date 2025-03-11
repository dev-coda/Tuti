<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    

    public function index(Request $request){

        $orders = Order::query()
            ->when($request->seller_id, function($query, $seller_id){
                $query->where('seller_id', $seller_id);
            })
            ->when($request->q, function ($query, $q) {
                $query->whereRelation('user', 'name', 'ilike', "%$q%");       
            })
            ->with(['user', 'seller'])
            ->withCount('products')
            ->orderByDesc('id')
            ->paginate();

        $sellers = User::query()->whereRelation('roles', 'name', 'seller')->get()->pluck('name', 'id');
        $sellers = $sellers->prepend('Vendedores', '');
        
        $context = compact('orders', 'sellers');

        return view('orders.index', $context );
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
