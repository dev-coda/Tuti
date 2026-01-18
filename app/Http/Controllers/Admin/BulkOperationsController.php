<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BulkSyncClientsData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkOperationsController extends Controller
{
    /**
     * Display bulk operations page
     */
    public function index()
    {
        // Get list of available reports
        $reports = [];
        $files = Storage::disk('local')->files('reports');

        foreach ($files as $file) {
            if (str_ends_with($file, '.csv')) {
                $filename = basename($file);
                $reports[] = [
                    'name' => $filename,
                    'path' => $file,
                    'size' => Storage::disk('local')->size($file),
                    'modified' => Storage::disk('local')->lastModified($file),
                ];
            }
        }

        // Sort by modified date (newest first)
        usort($reports, function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        return view('admin.bulk-operations.index', compact('reports'));
    }

    /**
     * Start bulk client data sync
     */
    public function syncClientsData(Request $request)
    {
        try {
            // Get all users with role 'client' who have a document number
            $userIds = User::whereHas('roles', function ($query) {
                $query->where('name', 'client');
            })
                ->whereNotNull('document')
                ->where('document', '!=', '')
                ->pluck('id')
                ->toArray();

            if (empty($userIds)) {
                return back()->with('error', 'No se encontraron clientes para sincronizar.');
            }

            // Generate unique session ID for this bulk operation
            $sessionId = date('YmdHis') . '-' . Str::random(8);

            // Dispatch the bulk sync job
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync') {
                $queueConnection = 'database';
            }

            BulkSyncClientsData::dispatch($userIds, $sessionId)
                ->onConnection($queueConnection)
                ->onQueue('default');

            \Log::info('Bulk client sync initiated', [
                'session_id' => $sessionId,
                'total_clients' => count($userIds),
                'user' => auth()->user()->email ?? 'Unknown',
            ]);

            return back()->with('success', "Sincronización iniciada para " . count($userIds) . " clientes. ID de sesión: {$sessionId}. El reporte estará disponible cuando finalice.");
        } catch (\Throwable $e) {
            \Log::error('Error initiating bulk client sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Error al iniciar la sincronización: ' . $e->getMessage());
        }
    }

    /**
     * Download a report file
     */
    public function downloadReport($filename)
    {
        $path = "reports/{$filename}";

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Reporte no encontrado');
        }

        return Storage::disk('local')->download($path, $filename);
    }

    /**
     * Delete a report file
     */
    public function deleteReport(Request $request, $filename)
    {
        $path = "reports/{$filename}";

        if (!Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Reporte no encontrado');
        }

        Storage::disk('local')->delete($path);

        \Log::info('Bulk report deleted', [
            'filename' => $filename,
            'user' => auth()->user()->email ?? 'Unknown',
        ]);

        return back()->with('success', 'Reporte eliminado exitosamente');
    }
}
