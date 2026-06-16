<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientDataUpdateRequest;
use Illuminate\Http\Request;

class ClientDataUpdateRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = ClientDataUpdateRequest::query()
            ->with(['client:id,name,document', 'seller:id,name', 'zone:id,address'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = (string) $request->input('q');
                $query->where(function ($sub) use ($term) {
                    $sub->where('document', 'like', "%{$term}%")
                        ->orWhere('name', 'ilike', "%{$term}%")
                        ->orWhere('business_name', 'ilike', "%{$term}%");
                });
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', (string) $request->input('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', (string) $request->input('date_to'));
            })
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();

        return view('client-data-update-requests.index', compact('requests'));
    }

    public function show(ClientDataUpdateRequest $clientDataUpdateRequest)
    {
        if (!$clientDataUpdateRequest->read_at) {
            $clientDataUpdateRequest->forceFill(['read_at' => now()])->save();
        }

        $clientDataUpdateRequest->load(['client.city', 'seller', 'zone']);

        return view('client-data-update-requests.show', [
            'updateRequest' => $clientDataUpdateRequest,
        ]);
    }
}
