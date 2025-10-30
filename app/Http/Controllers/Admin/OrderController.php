<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrdersExport;
use App\Exports\OrdersMonthlyExport;
use App\Http\Controllers\Controller;
use App\Models\ExportFile;
use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use App\Models\Brand;
use App\Models\Vendor;

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

            // Filter orders that include at least one product of a given brand
            ->when($request->filled('brand_id') && $request->brand_id !== '', function ($query) use ($request) {
                $brandId = (int) $request->brand_id;
                $query->whereHas('products.product', function ($sub) use ($brandId) {
                    $sub->where('brand_id', $brandId);
                });
            })
            // Filter orders that include at least one product of a given vendor
            ->when($request->filled('vendor_id') && $request->vendor_id !== '', function ($query) use ($request) {
                $vendorId = (int) $request->vendor_id;
                $query->whereHas('products.product.brand', function ($sub) use ($vendorId) {
                    $sub->where('vendor_id', $vendorId);
                });
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

        $brands = Brand::orderBy('name')->pluck('name', 'id')->prepend('Todas las marcas', '');
        $vendors = Vendor::orderBy('name')->pluck('name', 'id')->prepend('Todos los vendors', '');

        return view('orders.index', compact('orders', 'sellers', 'zones', 'brands', 'vendors'));
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
        $brand_id = $request->input('brand_id');
        $vendor_id = $request->input('vendor_id');

        if (!$to_date) {
            $to_date = Carbon::now();
        }

        if ($from_date && $to_date) {
            $from_date = Carbon::parse($from_date)->startOfDay();
            $to_date = Carbon::parse($to_date)->endOfDay();
            return Excel::download(new OrdersExport($from_date->toDateString(), $to_date->toDateString(), $brand_id, $vendor_id), 'orders.xlsx');
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

    /**
     * Retry XML transmission for an order
     */
    public function retryXmlTransmission(Order $order)
    {
        $result = OrderRepository::retryXmlTransmission($order);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }

    /**
     * Retry sending order confirmation email
     */
    public function retryConfirmationEmail(Order $order)
    {
        $result = $order->retryConfirmationEmail();

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }

    /**
     * Retry sending order status email
     */
    public function retryStatusEmail(Order $order)
    {
        $result = $order->retryStatusEmail();

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }

    /**
     * Create a monthly export (async)
     */
    public function exportMonthly(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = $validated['year'];
        $month = $validated['month'];
        $monthName = Carbon::create($year, $month)->locale('es')->translatedFormat('F Y');

        // Create export file record
        $exportFile = ExportFile::create([
            'user_id' => auth()->id(),
            'type' => 'orders_monthly',
            'filename' => "pedidos_{$year}_{$month}_" . time() . '.xlsx',
            'file_path' => "exports/orders/{$year}/{$month}/pedidos_{$year}_{$month}_" . time() . '.xlsx',
            'status' => ExportFile::STATUS_PENDING,
            'params' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $monthName,
            ],
        ]);

        // Queue the export
        try {
            (new OrdersMonthlyExport($year, $month, auth()->id()))
                ->queue($exportFile->file_path)
                ->chain([
                    function () use ($exportFile) {
                        // Count total records
                        $year = $exportFile->params['year'];
                        $month = $exportFile->params['month'];
                        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

                        $totalRecords = Order::whereBetween('created_at', [$startDate, $endDate])->count();

                        $exportFile->markAsCompleted($totalRecords);
                    }
                ]);

            $exportFile->markAsProcessing();

            return response()->json([
                'success' => true,
                'message' => "Exportación iniciada para {$monthName}. Recibirás una notificación cuando esté lista.",
                'export_id' => $exportFile->id,
            ]);
        } catch (\Exception $e) {
            $exportFile->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar la exportación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's export files
     */
    public function getExports(Request $request)
    {
        $exports = ExportFile::forUser(auth()->id())
            ->where('type', 'orders_monthly')
            ->recent(90) // Last 90 days
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($export) {
                return [
                    'id' => $export->id,
                    'filename' => $export->filename,
                    'status' => $export->status,
                    'month_name' => $export->params['month_name'] ?? '',
                    'total_records' => $export->total_records,
                    'file_size' => $export->file_size,
                    'created_at' => $export->created_at->format('Y-m-d H:i'),
                    'completed_at' => $export->completed_at?->format('Y-m-d H:i'),
                    'download_url' => $export->download_url,
                    'is_completed' => $export->isCompleted(),
                    'is_processing' => $export->isProcessing(),
                    'has_failed' => $export->hasFailed(),
                    'error_message' => $export->error_message,
                ];
            });

        return response()->json($exports);
    }

    /**
     * Download an export file
     */
    public function downloadExport(ExportFile $exportFile)
    {
        // Verify user owns this export
        if ($exportFile->user_id !== auth()->id()) {
            abort(403, 'No autorizado');
        }

        // Check if export is completed
        if (!$exportFile->isCompleted()) {
            return redirect()->back()->with('error', 'La exportación aún no está lista.');
        }

        // Check if file exists
        if (!Storage::disk('local')->exists($exportFile->file_path)) {
            return redirect()->back()->with('error', 'El archivo de exportación no se encontró.');
        }

        return Storage::disk('local')->download($exportFile->file_path, $exportFile->filename);
    }

    /**
     * Check export status (for polling)
     */
    public function checkExportStatus(ExportFile $exportFile)
    {
        // Verify user owns this export
        if ($exportFile->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json([
            'status' => $exportFile->status,
            'is_completed' => $exportFile->isCompleted(),
            'is_processing' => $exportFile->isProcessing(),
            'has_failed' => $exportFile->hasFailed(),
            'total_records' => $exportFile->total_records,
            'file_size' => $exportFile->file_size,
            'error_message' => $exportFile->error_message,
            'completed_at' => $exportFile->completed_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
