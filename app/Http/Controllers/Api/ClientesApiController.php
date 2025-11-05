<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiPaginationTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClientesApiController extends Controller
{
    use ApiPaginationTrait;

    /**
     * Display a listing of clients.
     * 
     * Query Parameters:
     * - search: Search by name, email, or document
     * - city_id: Filter by city ID
     * - zone: Filter by zone
     * - state_id: Filter by state ID
     * - role: Filter by role name (e.g., 'customer', 'seller')
     * - sort_by/order_by: Sort field (name, email, created_at, etc.)
     * - sort_direction/order: Sort direction (asc, desc)
     * - per_page: Items per page (default: 15, max: 100)
     * - limit: Maximum number of items to return (for non-paginated)
     * - offset: Number of items to skip (for non-paginated)
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
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->has('city_id')) {
            $query->where('city_id', $request->get('city_id'));
        }

        if ($request->has('state_id')) {
            $query->whereHas('city', function ($q) use ($request) {
                $q->where('state_id', $request->get('state_id'));
            });
        }

        if ($request->has('zone')) {
            $query->where('zone', $request->get('zone'));
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->get('role'));
            });
        }

        if ($request->has('has_whatsapp')) {
            $query->where('has_whatsapp', filter_var($request->get('has_whatsapp'), FILTER_VALIDATE_BOOLEAN));
        }

        // Apply sorting and pagination/limit-offset
        $result = $this->applyPaginationAndSorting(
            $query,
            ['id', 'name', 'email', 'document', 'created_at', 'updated_at'], // Sortable fields
            'name', // Default sort field
            'asc', // Default direction
            15, // Default per page
            100 // Max per page
        );

        return $this->jsonResponse($result);
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
