<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Services\MicrosoftTokenService;
use App\Services\Shipping\FvDynamicsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Health and log panel for the FV (Dynamics 365 CreateSalesOrder)
 * integration used by Coordinadora 48h orders. See docs/fv.pdf.
 */
class FvIntegrationController extends Controller
{
    private const HEALTH_CHECK_SETTING_KEY = 'fv_last_health_check';

    public function index(Request $request, FvDynamicsService $fvService)
    {
        $endpoint = null;
        $endpointError = null;
        try {
            $endpoint = $fvService->resolveEndpoint();
        } catch (\RuntimeException $e) {
            $endpointError = $e->getMessage();
        }

        $health = [
            'endpoint' => $endpoint,
            'endpoint_error' => $endpointError,
            'soap_action' => (string) config('services.fv.soap_action'),
            'company' => (string) config('services.fv.company'),
            'origen_venta' => (string) config('services.fv.origen_venta'),
            'location_invoice' => (string) config('services.fv.location_invoice'),
            'num_sequence_group' => (string) config('services.fv.num_sequence_group'),
            'default_warehouse' => (string) config('services.fv.default_warehouse'),
            'token_present' => filled(Setting::getByKey('microsoft_token')),
            'last_check' => $this->lastHealthCheck(),
        ];

        $baseQuery = Order::query()
            ->where('delivery_method', Order::DELIVERY_METHOD_EXPRESS)
            ->where('shipping_provider', Order::SHIPPING_PROVIDER_COORDINADORA);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'with_fv' => (clone $baseQuery)->whereNotNull('fv_number')->count(),
            'errors' => (clone $baseQuery)->whereIn('status_id', [Order::STATUS_ERROR, Order::STATUS_ERROR_WEBSERVICE])->count(),
            'pending' => (clone $baseQuery)->whereIn('status_id', [Order::STATUS_PENDING, Order::STATUS_WAITING, Order::STATUS_DRAFT])->count(),
        ];

        $lastFvOrder = (clone $baseQuery)->whereNotNull('fv_number')->orderByDesc('id')->first();

        $orders = (clone $baseQuery)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)->orWhere('fv_number', 'like', "%{$search}%");
                });
            })
            ->when($request->input('status') === 'processed', fn ($q) => $q->where('status_id', Order::STATUS_PROCESSED))
            ->when($request->input('status') === 'error', fn ($q) => $q->whereIn('status_id', [Order::STATUS_ERROR, Order::STATUS_ERROR_WEBSERVICE]))
            ->when($request->input('status') === 'pending', fn ($q) => $q->whereIn('status_id', [Order::STATUS_PENDING, Order::STATUS_WAITING, Order::STATUS_DRAFT]))
            ->when($request->input('status') === 'no_fv', fn ($q) => $q->whereNull('fv_number'))
            ->with('user')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('settings.fv-integration', [
            'health' => $health,
            'stats' => $stats,
            'lastFvOrder' => $lastFvOrder,
            'orders' => $orders,
            'fileLogs' => $this->recentFvLogsFromFile(),
        ]);
    }

    /**
     * Connectivity probe: validates the Microsoft token and requests the
     * webservice WSDL (no sales order is created).
     */
    public function testConnection(FvDynamicsService $fvService)
    {
        $result = [
            'checked_at' => now()->toDateTimeString(),
            'ok' => false,
            'token_ok' => false,
            'endpoint' => null,
            'http_status' => null,
            'latency_ms' => null,
            'error' => null,
        ];

        try {
            $endpoint = $fvService->resolveEndpoint();
            $result['endpoint'] = $endpoint;

            $token = MicrosoftTokenService::currentOrRefresh();
            $result['token_ok'] = true;

            $start = microtime(true);
            $response = Http::withToken($token)
                ->timeout(15)
                ->connectTimeout(5)
                ->withOptions(['verify' => false, 'http_errors' => false])
                ->get($endpoint . '?wsdl');

            $result['latency_ms'] = (int) round((microtime(true) - $start) * 1000);
            $result['http_status'] = $response->status();
            // Any HTTP answer proves DNS/TLS/network reachability; 2xx also proves auth.
            $result['ok'] = $response->successful();

            if (!$result['ok']) {
                $result['error'] = 'El endpoint respondió HTTP ' . $response->status() . '.';
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::channel('soap')->warning('FV health check failed', ['error' => $e->getMessage()]);
        }

        Setting::updateOrCreate(
            ['key' => self::HEALTH_CHECK_SETTING_KEY],
            ['name' => 'FV - última prueba de conexión', 'value' => json_encode($result), 'show' => false]
        );

        if ($result['ok']) {
            return redirect()->route('settings.fv-integration')
                ->with('success', "Conexión exitosa (HTTP {$result['http_status']}, {$result['latency_ms']} ms).");
        }

        $detail = $result['error'] ?? 'Error desconocido.';
        if ($result['http_status'] !== null && $result['token_ok']) {
            $detail .= " (HTTP {$result['http_status']}, {$result['latency_ms']} ms)";
        }

        return redirect()->route('settings.fv-integration')
            ->with('error', 'Prueba de conexión fallida: ' . $detail);
    }

    private function lastHealthCheck(): ?array
    {
        $raw = Setting::getByKey(self::HEALTH_CHECK_SETTING_KEY);
        $payload = $raw ? json_decode((string) $raw, true) : null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * Scrape recent FV entries from the daily soap log files.
     *
     * @return array<int, string>
     */
    private function recentFvLogsFromFile(): array
    {
        $files = glob(storage_path('logs/soap-requests*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $lines = [];

        foreach (array_slice($files, 0, 2) as $file) {
            try {
                $fileSize = filesize($file);
                $readSize = min($fileSize, 500000);

                $handle = fopen($file, 'r');
                if (!$handle) {
                    continue;
                }
                fseek($handle, max(0, $fileSize - $readSize));
                $content = fread($handle, $readSize);
                fclose($handle);

                $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\][^\n]*(?:FV CreateSalesOrder|FV health check)[^\n]*/';
                preg_match_all($pattern, $content, $matches);
                $lines = array_merge($lines, $matches[0] ?? []);
            } catch (\Exception $e) {
                Log::warning('Could not read FV logs from file: ' . $e->getMessage(), ['file' => $file]);
            }
        }

        return array_reverse(array_slice($lines, -50));
    }
}
