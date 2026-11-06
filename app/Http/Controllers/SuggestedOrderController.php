<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SuggestedOrderService;
use Illuminate\Http\Request;

class SuggestedOrderController extends Controller
{
    public function store(Request $request, User $user, SuggestedOrderService $suggestions)
    {
        $seller = $request->user();
        abort_unless($seller && $seller->isStaffSeller(), 403);
        abort_unless($seller->canServeClient($user), 403);

        if (! $suggestions->config()['enabled']) {
            return back()->with('error', 'El pedido sugerido está desactivado.');
        }

        $suggestion = $suggestions->build($user);
        if ($suggestion['lines'] === []) {
            return back()->with('error', 'No hay productos suficientes para armar un pedido sugerido de este cliente.');
        }

        session()->put('user_id', $user->id);
        session()->forget('zone_id');
        session()->forget('applied_coupon');
        session()->forget('applied_coupons');
        session()->put('cart', $suggestion['lines']);
        session()->put('suggested_order', $suggestion['meta']);

        $name = $suggestion['meta']['client_name'] ?: 'el cliente';

        return redirect()
            ->route('cart')
            ->with('success', 'Pedido sugerido cargado para '.$name.'.');
    }
}
