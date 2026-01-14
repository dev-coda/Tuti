<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ZoneWarehouse;
use Illuminate\Http\Request;
use App\Jobs\SyncProductInventory;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        // Ensure inventory management toggle exists
        Setting::firstOrCreate(
            ['key' => 'inventory_enabled'],
            [
                'name' => 'Inventario habilitado',
                'value' => '1',
                'show' => true,
            ]
        );

        $settings = Setting::query()
            ->when($request->q, function ($query, $q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->whereShow(true)
            ->orderBy('id')
            ->paginate();

        $context = compact('settings');
        return view('settings.index', $context);
    }

    /**
     * Display the specified resource.
     */
    public function show(Setting $setting)
    {
        $context = compact('setting');
        return view('settings.show', $context);
    }

    //edit
    public function edit(Setting $setting)
    {
        $context = compact('setting');
        return view('settings.edit', $context);
    }

    //update
    public function update(Request $request, Setting $setting)
    {
        $validate = $request->validate([
            'value' => 'required',
        ]);

        $setting->update($validate);
        return to_route('settings.index')->with('success', 'Texto actualizado');
    }

    public function syncInventory(Request $request)
    {
        // Check if user wants to run synchronously (for testing/debugging)
        $runSync = $request->has('sync') || $request->query('sync') === '1';
        
        try {
            if ($runSync) {
                // Run synchronously - useful for testing when Horizon isn't running
                \Illuminate\Support\Facades\Log::info('Inventory sync job running SYNCHRONOUSLY');
                
                $job = new SyncProductInventory();
                $job->handle();
                
                \Illuminate\Support\Facades\Log::info('Inventory sync job completed synchronously');
                
                return back()->with('success', 'Sincronización de inventario completada exitosamente.');
            }
            
            // Dispatch to queue for async processing with Horizon
            $queueConnection = config('queue.default');
            
            // If queue is set to 'sync', use 'redis' instead to ensure async processing with Horizon
            if ($queueConnection === 'sync') {
                $queueConnection = 'redis';
            }
            
            SyncProductInventory::dispatch()
                ->onConnection($queueConnection)
                ->onQueue('inventory');
            
            \Illuminate\Support\Facades\Log::info('Inventory sync job dispatched', [
                'connection' => $queueConnection,
                'queue' => 'inventory'
            ]);
            
            return back()->with('success', 'Sincronización de inventario iniciada. El proceso se ejecutará en segundo plano. Nota: Asegúrate de que Horizon esté ejecutándose (php artisan horizon).');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Inventory sync failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error al sincronizar inventario: ' . $e->getMessage());
        }
    }

    /**
     * Show mailer settings
     */
    public function mailer()
    {
        $mailerSettings = \App\Models\Setting::whereIn('key', [
            'mail_mailer',
            'mail_from_address',
            'mail_from_name',
            'mailgun_domain',
            'mailgun_secret',
            'mailgun_endpoint',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
        ])->get()->keyBy('key');

        return view('settings.mailer', compact('mailerSettings'));
    }

    /**
     * Update mailer settings
     */
    public function updateMailer(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => 'required|string|in:smtp,mailgun,sendmail,log',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string|max:255',
            'mailgun_domain' => 'nullable|string|max:255',
            'mailgun_secret' => 'nullable|string|max:255',
            'mailgun_endpoint' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|string|in:tls,ssl',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key],
                    ['name' => $this->getSettingName($key), 'value' => $value, 'show' => true]
                );
            }
        }

        // Update mail configuration
        $mailingService = app(\App\Services\MailingService::class);
        $mailingService->updateMailConfiguration();

        return back()->with('success', 'Configuración de correo actualizada exitosamente');
    }

    /**
     * Get human-readable setting name
     */
    private function getSettingName($key)
    {
        $names = [
            'mail_mailer' => 'Mail Driver',
            'mail_from_address' => 'Mail From Address',
            'mail_from_name' => 'Mail From Name',
            'mailgun_domain' => 'Mailgun Domain',
            'mailgun_secret' => 'Mailgun Secret',
            'mailgun_endpoint' => 'Mailgun Endpoint',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
            'smtp_encryption' => 'SMTP Encryption',
        ];

        return $names[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Show zone warehouse mappings
     */
    public function zoneWarehouses()
    {
        // Get mappings from database
        $dbMappings = ZoneWarehouse::orderBy('zone_code')->get();
        
        // Get mappings from config
        $configMappings = collect(config('zone_warehouses.mappings', []))
            ->map(function($bodega, $zone) {
                return [
                    'zone_code' => $zone,
                    'bodega_code' => is_array($bodega) ? $bodega[0] : $bodega,
                    'source' => 'config'
                ];
            });

        // Combine both sources
        $allMappings = $dbMappings->map(function($mapping) {
            return [
                'zone_code' => $mapping->zone_code,
                'bodega_code' => $mapping->bodega_code,
                'source' => 'database',
                'id' => $mapping->id
            ];
        })->concat($configMappings->values())
        ->unique(function($item) {
            return $item['zone_code'] . '-' . $item['bodega_code'];
        })
        ->sortBy('zone_code')
        ->values();

        // Group by bodega for stats
        $bodegas = $allMappings->groupBy('bodega_code')->map(function($group) {
            return $group->count();
        })->sortDesc();

        return view('settings.zone-warehouses', compact('allMappings', 'bodegas', 'dbMappings'));
    }

    /**
     * Sync zone warehouses from config to database
     */
    public function syncZoneWarehouses()
    {
        $mappings = (array) config('zone_warehouses.mappings', []);
        $count = 0;

        foreach ($mappings as $zone => $bodegas) {
            $bodegaList = is_array($bodegas) ? $bodegas : [$bodegas];
            foreach ($bodegaList as $bodega) {
                if (!$zone || !$bodega) {
                    continue;
                }
                ZoneWarehouse::updateOrCreate([
                    'zone_code' => trim((string) $zone),
                    'bodega_code' => trim((string) $bodega),
                ], []);
                $count++;
            }
        }

        return back()->with('success', "Sincronizadas {$count} asignaciones zona-bodega desde configuración");
    }

    /**
     * Store a new zone warehouse mapping
     */
    public function storeZoneWarehouse(Request $request)
    {
        $validated = $request->validate([
            'zone_code' => 'required|string|max:50',
            'bodega_code' => 'required|string|max:50',
        ]);

        ZoneWarehouse::updateOrCreate([
            'zone_code' => $validated['zone_code'],
            'bodega_code' => $validated['bodega_code'],
        ], []);

        return back()->with('success', 'Asignación creada exitosamente');
    }

    /**
     * Delete a zone warehouse mapping
     */
    public function destroyZoneWarehouse(ZoneWarehouse $zoneWarehouse)
    {
        $zoneWarehouse->delete();
        return back()->with('success', 'Asignación eliminada exitosamente');
    }

    /**
     * Show inventory sync logs
     */
    public function inventoryLogs()
    {
        $logs = collect();
        $tableExists = false;
        
        // Check if the inventory_sync_logs table exists
        try {
            $tableExists = \Illuminate\Support\Facades\Schema::hasTable('inventory_sync_logs');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not check if inventory_sync_logs table exists: ' . $e->getMessage());
        }
        
        if ($tableExists) {
            // Get the latest sync run (all bodegas from the most recent sync)
            $logs = \App\Models\InventorySyncLog::getLatestSyncRun();
            
            // If no logs, try to get the last 20 individual logs
            if ($logs->isEmpty()) {
                $logs = \App\Models\InventorySyncLog::latest()->take(20)->get();
            }
        }
        
        // Also try to get recent logs from the Laravel log file
        $fileLogs = $this->getRecentInventoryLogsFromFile();
        
        return view('settings.inventory-logs', [
            'logs' => $logs,
            'fileLogs' => $fileLogs,
            'tableExists' => $tableExists,
            'lastSync' => \App\Models\Setting::getByKey('inventory_last_synced_at'),
        ]);
    }
    
    /**
     * Parse recent inventory-related logs from the Laravel log file
     */
    private function getRecentInventoryLogsFromFile(): array
    {
        $logs = [];
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            return $logs;
        }
        
        try {
            // Read the last 200KB of the log file
            $fileSize = filesize($logFile);
            $readSize = min($fileSize, 200000);
            
            $handle = fopen($logFile, 'r');
            if ($handle) {
                fseek($handle, max(0, $fileSize - $readSize));
                $content = fread($handle, $readSize);
                fclose($handle);
                
                // Find inventory-related log entries
                $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\].*(?:INVENTORY|inventory|Inventory|bodega|SyncProductInventory).*(?:\n(?!\[\d{4}).*?)*/i';
                preg_match_all($pattern, $content, $matches);
                
                // Take last 50 matches
                $logs = array_slice($matches[0] ?? [], -50);
                $logs = array_reverse($logs); // Most recent first
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not read inventory logs from file: ' . $e->getMessage());
        }
        
        return $logs;
    }

    /**
     * Update vacation mode settings
     */
    public function updateVacationMode(Request $request)
    {
        $validated = $request->validate([
            'vacation_mode_enabled' => 'nullable|in:1',
            'vacation_mode_from_date' => 'nullable|date',
            'vacation_mode_date' => 'nullable|date',
        ]);

        // Update vacation mode enabled setting
        $enabled = isset($validated['vacation_mode_enabled']) ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'vacation_mode_enabled'],
            [
                'name' => 'Modo Vacaciones',
                'value' => $enabled,
                'show' => false,
            ]
        );

        // Update vacation mode from date if provided
        if (isset($validated['vacation_mode_from_date'])) {
            Setting::updateOrCreate(
                ['key' => 'vacation_mode_from_date'],
                [
                    'name' => 'Fecha de Inicio de Vacaciones',
                    'value' => $validated['vacation_mode_from_date'],
                    'show' => false,
                ]
            );
        }

        // Update vacation mode date (return date) if provided
        if (isset($validated['vacation_mode_date'])) {
            Setting::updateOrCreate(
                ['key' => 'vacation_mode_date'],
                [
                    'name' => 'Fecha de Regreso de Vacaciones',
                    'value' => $validated['vacation_mode_date'],
                    'show' => false,
                ]
            );
        }

        return back()->with('success', 'Configuración de modo vacaciones actualizada exitosamente');
    }

    /**
     * Update express 48h delivery setting
     */
    public function updateExpress48h(Request $request)
    {
        $validated = $request->validate([
            'express_48h_enabled' => 'nullable|in:1',
        ]);

        // Update express 48h enabled setting
        $enabled = isset($validated['express_48h_enabled']) ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'express_48h_enabled'],
            [
                'name' => 'Entrega Express 48h',
                'value' => $enabled,
                'show' => false,
            ]
        );

        return back()->with('success', 'Configuración de entrega express actualizada exitosamente');
    }

    /**
     * Update global minimum inventory setting
     */
    public function updateGlobalInventory(Request $request)
    {
        $validated = $request->validate([
            'global_minimum_inventory' => 'required|integer|min:0|max:100',
        ]);

        Setting::updateOrCreate(
            ['key' => 'global_minimum_inventory'],
            [
                'name' => 'Inventario Mínimo Global',
                'value' => $validated['global_minimum_inventory'],
                'show' => false,
            ]
        );

        \Illuminate\Support\Facades\Log::info('Global minimum inventory updated', [
            'value' => $validated['global_minimum_inventory'],
            'user' => auth()->user()->email ?? 'Unknown',
            'timestamp' => now()->toDateTimeString()
        ]);

        return back()->with('success', 'Inventario mínimo global actualizado exitosamente');
    }

    /**
     * Update force delivery date setting
     */
    public function updateForceDeliveryDate(Request $request)
    {
        $validated = $request->validate([
            'force_delivery_date_enabled' => 'nullable|in:1',
        ]);

        // Update force delivery date enabled setting
        $enabled = isset($validated['force_delivery_date_enabled']) ? '1' : '0';
        Setting::updateOrCreate(
            ['key' => 'force_delivery_date_enabled'],
            [
                'name' => 'Forzar Fecha de Entrega',
                'value' => $enabled,
                'show' => false,
            ]
        );

        \Illuminate\Support\Facades\Log::warning('Force Delivery Date setting changed', [
            'enabled' => $enabled,
            'user' => auth()->user()->email ?? 'Unknown',
            'timestamp' => now()->toDateTimeString()
        ]);

        $message = $enabled === '1' 
            ? '⚠️ Forzar Fecha de Entrega ACTIVADO: Los pedidos ahora se enviarán con el próximo día hábil como fecha de entrega.'
            : 'Forzar Fecha de Entrega DESACTIVADO: Los pedidos se enviarán con su fecha programada normal.';

        return back()->with('success', $message);
    }

    /**
     * Process all waiting orders created in the last 24 hours
     */
    public function processWaitingOrders(Request $request)
    {
        try {
            $twentyFourHoursAgo = \Carbon\Carbon::now()->subHours(24);
            
            // Find all waiting orders created in the last 24 hours
            $orders = \App\Models\Order::where('status_id', \App\Models\Order::STATUS_WAITING)
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->get();

            if ($orders->isEmpty()) {
                return back()->with('info', 'No se encontraron pedidos en espera creados en las últimas 24 horas.');
            }

            $processedCount = 0;
            
            // Determine queue connection
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync') {
                $queueConnection = 'database';
            }
            
            foreach ($orders as $order) {
                // Update order status to pending
                $order->update([
                    'status_id' => \App\Models\Order::STATUS_PENDING,
                ]);
                
                // Dispatch the job to process the order (pass the full Order model)
                \App\Jobs\ProcessOrderAsync::dispatch($order)
                    ->onConnection($queueConnection);
                
                $processedCount++;
            }

            \Illuminate\Support\Facades\Log::info('Emergency order processing initiated', [
                'total_orders' => $processedCount,
                'user' => auth()->user()->email ?? 'Unknown',
                'timestamp' => now()->toDateTimeString(),
                'force_delivery_date_enabled' => Setting::getByKey('force_delivery_date_enabled') == '1',
                'queue_connection' => $queueConnection
            ]);

            $message = "Se iniciaron {$processedCount} pedido(s) para procesamiento inmediato. Los pedidos se procesarán en segundo plano.";
            
            if (Setting::getByKey('force_delivery_date_enabled') == '1') {
                $message .= ' ⚠️ NOTA: Forzar Fecha de Entrega está activo, los pedidos usarán el próximo día hábil.';
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to process waiting orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error al procesar pedidos: ' . $e->getMessage());
        }
    }
}
