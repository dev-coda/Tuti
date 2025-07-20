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
use Illuminate\Support\Facades\Http;
use App\Models\Setting;

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

            ->when($request->zone, function ($query) use ($request) {
                if ($request->zone !== '') {
                    $query->whereHas('user', function ($subQuery) use ($request) {
                        $subQuery->where('zone', $request->zone);
                    });
                }
            })

            ->when($request->filled('from_date') && $request->filled('to_date'), function ($query) use ($request) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->from_date)->startOfDay(),
                    Carbon::parse($request->to_date)->endOfDay(),
                ]);
            })

            ->with(['user', 'seller'])
            ->withCount('products')
            ->withSum('products', 'quantity')
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();

        $sellers = User::whereHas('roles', function ($query) {
            $query->where('name', 'seller');
        })->pluck('name', 'id')->prepend('Vendedores', '');

        $zones = User::whereHas('orders')
            ->select('zone')
            ->distinct()
            ->whereNotNull('zone')
            ->orderBy('zone', 'asc')
            ->pluck('zone');

        return view('orders.index', compact('orders', 'sellers', 'zones'));
    }

    public function edit(Order $order)
    {


        $order->load([
            'user',
            'bonifications' => ['product', 'bonification'],
            'products' => [
                'product' => [
                    'variation',
                    'bonifications.product'
                ]
            ]
        ]);
        $context = compact('order');
        return view('orders.edit', $context);
    }

    public function export(Request $request)
    {
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        if (!$to_date) {
            $to_date = Carbon::now();
        }

        if ($from_date && $to_date) {
            $from_date = Carbon::parse($from_date)->startOfDay();
            $to_date = Carbon::parse($to_date)->endOfDay();
            return Excel::download(new OrdersExport($from_date->toDateString(), $to_date->toDateString()), 'orders.xlsx');
        } else {
            return redirect()->back()->withErrors(['error' => 'Por favor ingresa un rango de fechas.']);
        }
    }

    public function resend(Order $order)
    {
        // Check if the order has webservice error status
        if ($order->status_id !== Order::STATUS_ERROR_WEBSERVICE) {
            return redirect()->back()->with('error', 'Esta orden no puede ser reenviada.');
        }

        try {
            // Forcefully refresh the Microsoft token before resending
            $this->refreshMicrosoftToken();

            // Resend the order using the same method from OrderRepository
            OrderRepository::presalesOrder($order);

            return redirect()->back()->with('success', 'Orden reenviada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al reenviar la orden: ' . $e->getMessage());
        }
    }

    private function refreshMicrosoftToken()
    {
        $client_id = config('microsoft.client_id');
        $client_secret = config('microsoft.client_secret');
        $resource = config('microsoft.resource');
        $url = config('microsoft.url_token');

        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'resource' => $resource,
        ];

        $response = Http::asForm()->post($url, $data);

        if (!$response->successful()) {
            throw new \Exception('No se pudo actualizar el token de autenticación');
        }

        $json = $response->json();
        $token = $json['access_token'] ?? null;

        if (!$token) {
            throw new \Exception('Token de autenticación no válido');
        }

        Setting::where('key', 'microsoft_token')->update(['value' => $token]);
    }
}
