<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternalFvMockController extends Controller
{
    public function store(Request $request)
    {
        $token = (string) config('services.fv_mock.token');
        if ($token !== '' && $request->header('X-FV-MOCK-TOKEN') !== $token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $data = $request->validate([
            'order_id' => 'required|integer',
            'customer.id' => 'nullable',
            'customer.name' => 'nullable|string',
            'delivery.zone_id' => 'nullable',
            'items' => 'required|array|min:1',
        ]);

        return response()->json([
            'success' => true,
            'fv_number' => 'FV-' . $data['order_id'] . '-' . Str::upper(Str::random(6)),
            'received_at' => now()->toDateTimeString(),
            'received_items' => count($data['items']),
        ]);
    }
}
