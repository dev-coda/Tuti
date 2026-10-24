<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SpendingGift;
use Illuminate\Http\Request;

class SpendingGiftController extends Controller
{
    public function index(Request $request)
    {
        $spendingGifts = SpendingGift::query()
            ->with('giftProduct')
            ->withCount('products')
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate();

        return view('spending-gifts.index', compact('spendingGifts'));
    }

    public function create()
    {
        $products = Product::query()->orderBy('name')->pluck('name', 'id');

        return view('spending-gifts.create', compact('products'));
    }

    public function store(Request $request)
    {
        $spendingGift = SpendingGift::create($this->validated($request));

        return to_route('spending-gifts.edit', $spendingGift)
            ->with('success', 'Obsequio creado. Ahora asocia los productos que suman para el monto mínimo.');
    }

    public function edit(SpendingGift $spendingGift)
    {
        $attachedIds = $spendingGift->products()->pluck('products.id')->all();
        $products = Product::query()
            ->whereNotIn('id', $attachedIds)
            ->where('id', '!=', $spendingGift->product_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $giftProducts = Product::query()->orderBy('name')->pluck('name', 'id');

        return view('spending-gifts.edit', compact('spendingGift', 'products', 'giftProducts'));
    }

    public function update(Request $request, SpendingGift $spendingGift)
    {
        $spendingGift->update($this->validated($request));

        return to_route('spending-gifts.edit', $spendingGift)->with('success', 'Obsequio actualizado.');
    }

    public function destroy(SpendingGift $spendingGift)
    {
        $spendingGift->delete();

        return to_route('spending-gifts.index')->with('success', 'Obsequio eliminado.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'minimum_amount' => 'required|numeric|min:0.01|max:99999999',
            'product_id' => 'required|exists:products,id',
            'gift_quantity' => 'required|integer|min:1|max:9999',
            'gift_invoice_unit_value' => 'nullable|numeric|min:0.01|max:99999999',
            'gift_proration_scope' => 'nullable|in:cart,trigger',
        ]);

        $data['active'] = $request->boolean('active');
        $data['gift_invoice_unit_value'] = $request->filled('gift_invoice_unit_value')
            ? $request->input('gift_invoice_unit_value')
            : null;
        $data['gift_proration_scope'] = $request->input('gift_proration_scope') === 'trigger' ? 'trigger' : 'cart';

        return $data;
    }
}
