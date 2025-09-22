<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientesApiController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['city', 'city.state', 'roles'])
            ->where('status_id', User::ACTIVE);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            });
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->get('city_id'));
        }

        if ($request->has('zone')) {
            $query->where('zone', $request->get('zone'));
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Max 100 items per page
        $clients = $query->paginate($perPage);

        return response()->json([
            'data' => $clients->items(),
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ]
        ]);
    }

    /**
     * Display the specified client.
     */
    public function show(Request $request, User $client): JsonResponse
    {
        $client->load(['city', 'city.state', 'roles', 'orders']);

        return response()->json([
            'data' => $client
        ]);
    }
}
