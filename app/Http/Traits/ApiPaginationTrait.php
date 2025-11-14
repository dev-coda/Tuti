<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;

trait ApiPaginationTrait
{
    /**
     * Apply pagination, limit/offset, and sorting to a query
     * 
     * @param Builder $query
     * @param array $sortableFields
     * @param string $defaultSortBy
     * @param string $defaultSortDirection
     * @param int $defaultPerPage
     * @param int $maxPerPage
     * @return mixed
     */
    protected function applyPaginationAndSorting(
        Builder $query,
        array $sortableFields = [],
        string $defaultSortBy = 'id',
        string $defaultSortDirection = 'desc',
        int $defaultPerPage = 15,
        int $maxPerPage = 100
    ) {
        $request = request();
        
        // Apply sorting
        $sortBy = $request->get('sort_by', $request->get('order_by', $defaultSortBy));
        $sortDirection = $request->get('sort_direction', $request->get('order', $defaultSortDirection));
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : $defaultSortDirection;
        
        // Only apply sorting if field is in allowed list
        if (empty($sortableFields) || in_array($sortBy, $sortableFields)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            // Fall back to default sort
            $query->orderBy($defaultSortBy, $defaultSortDirection);
        }
        
        // Check if user wants non-paginated results with limit/offset
        if ($request->has('limit') || $request->has('offset')) {
            return $this->applyLimitOffset($query);
        }
        
        // Apply standard pagination
        $perPage = min((int) $request->get('per_page', $defaultPerPage), $maxPerPage);
        
        return $query->paginate($perPage);
    }
    
    /**
     * Apply limit and offset to query (non-paginated)
     * 
     * @param Builder $query
     * @return \Illuminate\Support\Collection
     */
    protected function applyLimitOffset(Builder $query)
    {
        $request = request();
        
        // Apply offset if specified
        if ($request->has('offset')) {
            $offset = max(0, (int) $request->get('offset'));
            $query->skip($offset);
        }
        
        // Apply limit if specified (with max safety limit)
        if ($request->has('limit')) {
            $limit = min((int) $request->get('limit'), 1000); // Max 1000 items
            $query->take($limit);
        }
        
        return $query->get();
    }
    
    /**
     * Format paginated response
     * 
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param callable|null $transformer
     * @return array
     */
    protected function formatPaginatedResponse($paginator, ?callable $transformer = null): array
    {
        $data = $paginator->items();
        
        if ($transformer) {
            $data = array_map($transformer, $data);
        }
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        ];
    }
    
    /**
     * Format non-paginated response (with limit/offset)
     * 
     * @param \Illuminate\Support\Collection $collection
     * @param callable|null $transformer
     * @return array
     */
    protected function formatCollectionResponse($collection, ?callable $transformer = null): array
    {
        $data = $collection->toArray();
        
        if ($transformer) {
            $data = array_map($transformer, $data);
        }
        
        return [
            'data' => $data,
            'meta' => [
                'count' => $collection->count(),
                'limit' => request()->get('limit'),
                'offset' => request()->get('offset', 0),
            ]
        ];
    }
    
    /**
     * Return JSON response with appropriate format
     * 
     * @param mixed $result
     * @param callable|null $transformer
     * @return JsonResponse
     */
    protected function jsonResponse($result, ?callable $transformer = null): JsonResponse
    {
        // Check if it's a paginator
        if (method_exists($result, 'items')) {
            return response()->json($this->formatPaginatedResponse($result, $transformer));
        }
        
        // It's a collection (limit/offset)
        return response()->json($this->formatCollectionResponse($result, $transformer));
    }
}

