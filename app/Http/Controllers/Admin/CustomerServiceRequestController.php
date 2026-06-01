<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceRequest;
use Illuminate\Http\Request;

class CustomerServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = CustomerServiceRequest::query()
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('request_type', (string) $request->input('type'));
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

        return view('customer-service-requests.index', compact('requests'));
    }

    public function show(CustomerServiceRequest $customerServiceRequest)
    {
        if (!$customerServiceRequest->read_at) {
            $customerServiceRequest->forceFill(['read_at' => now()])->save();
        }

        return view('customer-service-requests.show', [
            'requestEntry' => $customerServiceRequest,
        ]);
    }
}
