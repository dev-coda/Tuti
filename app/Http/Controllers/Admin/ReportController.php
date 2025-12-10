<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Jobs\GenerateUserEmailReport;
use App\Reports\UserEmailReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Available report types
     */
    private function getAvailableReports(): array
    {
        return [
            Report::TYPE_USER_EMAIL => [
                'name' => 'Reporte de Correos Electrónicos de Usuarios',
                'description' => 'Estadísticas sobre correos electrónicos de usuarios registrados',
                'has_filters' => false,
                'type' => 'async',
            ],
            'users_export' => [
                'name' => 'Exportar Usuarios',
                'description' => 'Exporta todos los usuarios registrados en formato Excel',
                'has_filters' => false,
                'type' => 'direct',
                'url' => '/admin/userexport',
            ],
            'sellers_export' => [
                'name' => 'Exportar Vendedores',
                'description' => 'Exporta todos los vendedores en formato Excel',
                'has_filters' => false,
                'type' => 'direct',
                'url' => '/admin/sellerexport',
            ],
            'products_export' => [
                'name' => 'Exportar Productos',
                'description' => 'Exporta todos los productos en formato Excel',
                'has_filters' => false,
                'type' => 'direct',
                'url' => '/admin/productexport',
            ],
            'contacts_export' => [
                'name' => 'Exportar Interesados',
                'description' => 'Exporta todos los contactos/interesados en formato Excel',
                'has_filters' => false,
                'type' => 'direct',
                'url' => '/admin/contactexport',
            ],
            'orders_export' => [
                'name' => 'Exportar Pedidos',
                'description' => 'Exporta pedidos filtrados por rango de fechas, marca y/o proveedor en formato Excel',
                'has_filters' => true,
                'type' => 'direct',
                'url' => '/admin/orderexport',
                'filters' => ['from_date', 'to_date', 'brand_id', 'vendor_id'],
            ],
            'holidays_export' => [
                'name' => 'Exportar Festivos',
                'description' => 'Exporta todos los festivos y sábados configurados en formato CSV',
                'has_filters' => false,
                'type' => 'direct',
                'url' => '/admin/holidays-export',
            ],
            'kpi_export' => [
                'name' => 'Exportar KPIs',
                'description' => 'Exporta métricas de KPIs por rango de fechas en formato CSV',
                'has_filters' => true,
                'type' => 'direct',
                'url' => '/admin/kpi/export',
                'filters' => ['start_date', 'end_date'],
            ],
        ];
    }

    /**
     * Display the reports index page
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $availableReports = $this->getAvailableReports();
        
        // Get user's reports
        $reports = Report::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Get brands and vendors for orders export filter
        $brands = \App\Models\Brand::orderBy('name')->pluck('name', 'id')->prepend('Todas las marcas', '');
        $vendors = \App\Models\Vendor::orderBy('name')->pluck('name', 'id')->prepend('Todos los proveedores', '');

        return view('admin.reports.index', [
            'availableReports' => $availableReports,
            'reports' => $reports,
            'selectedReportType' => $request->get('report_type'),
            'brands' => $brands,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Generate a new report or redirect to direct export
     */
    public function generate(Request $request)
    {
        $reportType = $request->get('report_type');
        $availableReports = $this->getAvailableReports();

        if (!isset($availableReports[$reportType])) {
            return back()->with('error', 'Tipo de reporte no válido.');
        }

        $reportConfig = $availableReports[$reportType];

        // Handle direct exports (immediate download)
        if ($reportConfig['type'] === 'direct' && isset($reportConfig['url'])) {
            // Build query string for filters
            $queryParams = [];
            if (isset($reportConfig['filters'])) {
                foreach ($reportConfig['filters'] as $filter) {
                    if ($request->has($filter) && $request->get($filter) !== '') {
                        $queryParams[$filter] = $request->get($filter);
                    }
                }
            }
            
            $url = url($reportConfig['url']);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }
            return redirect($url);
        }

        // Handle async reports (User Email Report)
        if ($reportType === Report::TYPE_USER_EMAIL) {
            $request->validate([
                'report_type' => 'required|string|in:' . Report::TYPE_USER_EMAIL,
            ]);

            $user = auth()->user();

            // Get report generator to get name
            $reportGenerator = new UserEmailReport();

            // Create report record
            $report = Report::create([
                'user_id' => $user->id,
                'type' => $reportType,
                'name' => $reportGenerator->getName(),
                'status' => Report::STATUS_PENDING,
                'filters' => $request->except(['report_type', '_token']),
                'expires_at' => now()->addWeek(),
            ]);

            // Dispatch job to generate report
            GenerateUserEmailReport::dispatch($report);

            return back()->with('success', 'El reporte se está generando. Podrás descargarlo cuando esté listo.');
        }

        return back()->with('error', 'Tipo de reporte no válido.');
    }

    /**
     * Download a report
     */
    public function download(Report $report)
    {
        $user = auth()->user();

        // Verify report belongs to user
        if ($report->user_id !== $user->id) {
            abort(403, 'No tienes permiso para descargar este reporte.');
        }

        // Check if report is ready
        if (!$report->isReady()) {
            return back()->with('error', 'El reporte aún no está listo para descargar.');
        }

        // Check if report has expired
        if ($report->isExpired()) {
            return back()->with('error', 'Este reporte ha expirado y ya no está disponible.');
        }

        // Check if file exists
        if (!Storage::disk('local')->exists($report->file_path)) {
            Log::error("Report file not found", [
                'report_id' => $report->id,
                'file_path' => $report->file_path,
            ]);
            return back()->with('error', 'El archivo del reporte no se encontró.');
        }

        return Storage::disk('local')->download($report->file_path, $report->filename);
    }

    /**
     * Check report status (AJAX)
     */
    public function status(Report $report)
    {
        $user = auth()->user();

        // Verify report belongs to user
        if ($report->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'status' => $report->status,
            'is_ready' => $report->isReady(),
            'is_expired' => $report->isExpired(),
            'completed_at' => $report->completed_at?->format('Y-m-d H:i:s'),
            'expires_at' => $report->expires_at?->format('Y-m-d H:i:s'),
            'error_message' => $report->error_message,
        ]);
    }

    /**
     * Delete a report
     */
    public function destroy(Report $report)
    {
        $user = auth()->user();

        // Verify report belongs to user
        if ($report->user_id !== $user->id) {
            abort(403, 'No tienes permiso para eliminar este reporte.');
        }

        // Delete file if exists
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        $report->delete();

        return back()->with('success', 'Reporte eliminado correctamente.');
    }
}
