<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DraftOrderReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BulkSyncClientsData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 7200; // 2 hours

    /**
     * @var array<int> User IDs to sync
     */
    protected array $userIds;

    /**
     * @var string Unique session ID for this bulk sync
     */
    protected string $sessionId;

    /**
     * Create a new job instance.
     *
     * @param array<int> $userIds
     * @param string $sessionId
     */
    public function __construct(array $userIds, string $sessionId)
    {
        $this->userIds = $userIds;
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $results = [];
        $totalUsers = count($this->userIds);
        $processed = 0;

        Log::info("Starting bulk client sync", [
            'session_id' => $this->sessionId,
            'total_users' => $totalUsers,
        ]);

        foreach ($this->userIds as $userId) {
            $processed++;
            $result = [
                'user_id' => $userId,
                'processed_at' => now()->toDateTimeString(),
                'status' => 'pending',
                'error' => null,
                'updated_fields' => [],
                'zones_synced' => 0,
                'activated' => false,
            ];

            try {
                $user = User::find($userId);

                if (!$user) {
                    $result['status'] = 'skipped';
                    $result['error'] = 'User not found';
                    $results[] = $result;
                    continue;
                }

                if (!$user->document) {
                    $result['status'] = 'skipped';
                    $result['error'] = 'No document number';
                    $result['user_email'] = $user->email;
                    $result['user_name'] = $user->name;
                    $results[] = $result;
                    continue;
                }

                // Store original data to detect changes. The periodic bulk sync
                // only refreshes contact data (email + phones); every other
                // profile field and the zone rows are left untouched.
                $originalData = $user->only(array_merge(
                    ['email'],
                    UserRepository::CONTACT_SYNC_FIELDS
                ));
                $originalZonesCount = $user->zones()->count();

                // Perform the contact-only sync
                $syncSuccess = UserRepository::syncUserContactData($user);

                if ($syncSuccess) {
                    $user->refresh();

                    // Activate clients (e.g. self-registered ones stuck in PENDING) whose
                    // locally stored rutero has a valid CustRuteroID. The contact-only
                    // sync above does not restructure zones, so this relies on the codes
                    // captured at registration or by the event-driven full syncs.
                    $result['activated'] = app(DraftOrderReconciliationService::class)
                        ->promoteUserIfReady($user->load('zones'));
                    if ($result['activated']) {
                        $user->refresh();
                    }

                    // Detect which fields were updated
                    $updatedFields = [];
                    $newData = $user->only(array_keys($originalData));

                    foreach ($originalData as $field => $originalValue) {
                        $newValue = $newData[$field] ?? null;
                        if (self::syncReportScalarChanged($field, $originalValue, $newValue)) {
                            $updatedFields[] = $field;
                        }
                    }

                    $newZonesCount = $user->zones()->count();
                    $zonesChanged = $newZonesCount !== $originalZonesCount;

                    $result['status'] = 'success';
                    $result['updated_fields'] = $updatedFields;
                    $result['zones_synced'] = $newZonesCount;
                    $result['zones_changed'] = $zonesChanged;
                    $result['user_email'] = $user->email;
                    $result['user_name'] = $user->name;
                    $result['user_document'] = $user->document;
                } else {
                    $result['status'] = 'failed';
                    $result['error'] = 'Sync returned false';
                    $result['user_email'] = $user->email;
                    $result['user_name'] = $user->name;
                }
            } catch (\Throwable $e) {
                $result['status'] = 'error';
                $result['error'] = $e->getMessage();
                // Safe access in case $user wasn't initialized due to early exception
                $result['user_email'] = isset($user) ? ($user->email ?? 'Unknown') : 'Unknown';
                $result['user_name'] = isset($user) ? ($user->name ?? 'Unknown') : 'Unknown';

                Log::error("Error syncing client data", [
                    'session_id' => $this->sessionId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            $results[] = $result;

            // Log progress every 10 users
            if ($processed % 10 === 0) {
                Log::info("Bulk client sync progress", [
                    'session_id' => $this->sessionId,
                    'processed' => $processed,
                    'total' => $totalUsers,
                    'progress_percent' => round(($processed / $totalUsers) * 100, 2),
                ]);
            }
        }

        $reportFilename = $this->generateReport($results);

        Setting::updateOrCreate(
            ['key' => 'last_client_rutero_bulk_sync_at'],
            ['name' => 'Última sincronización rutero (clientes)', 'value' => now()->toIso8601String(), 'show' => false]
        );
        Setting::updateOrCreate(
            ['key' => 'last_client_rutero_bulk_sync_session'],
            ['name' => 'Sesión última sync rutero', 'value' => $this->sessionId, 'show' => false]
        );
        Setting::updateOrCreate(
            ['key' => 'last_client_rutero_bulk_sync_report'],
            ['name' => 'Archivo CSV última sync rutero', 'value' => $reportFilename, 'show' => false]
        );

        Log::info("Bulk client sync completed", [
            'session_id' => $this->sessionId,
            'total_users' => $totalUsers,
            'processed' => $processed,
            'activated' => count(array_filter(array_column($results, 'activated'))),
            'report' => $reportFilename,
        ]);
    }

    /**
     * Generate CSV report of the sync results
     */
    protected function generateReport(array $results): string
    {
        $csvData = [];

        // Header
        $csvData[] = [
            'User ID',
            'User Email',
            'User Name',
            'Document',
            'Status',
            'Updated Fields',
            'Zones Synced',
            'Zones Changed',
            'Activated',
            'Error',
            'Processed At',
        ];

        // Data rows
        foreach ($results as $result) {
            $csvData[] = [
                $result['user_id'],
                $result['user_email'] ?? '',
                $result['user_name'] ?? '',
                $result['user_document'] ?? '',
                $result['status'],
                implode(', ', $result['updated_fields']),
                $result['zones_synced'] ?? 0,
                isset($result['zones_changed']) ? ($result['zones_changed'] ? 'Yes' : 'No') : 'N/A',
                ($result['activated'] ?? false) ? 'Yes' : 'No',
                $result['error'] ?? '',
                $result['processed_at'],
            ];
        }

        // Generate CSV content
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Store the report
        $filename = "bulk-client-sync-{$this->sessionId}.csv";
        Storage::disk('local')->put("reports/{$filename}", $csvContent);

        Log::info("Bulk client sync report generated", [
            'session_id' => $this->sessionId,
            'filename' => $filename,
            'path' => storage_path("app/reports/{$filename}"),
        ]);

        return $filename;
    }

    /**
     * Stable comparison for CSV "updated fields" (matches UserRepository ruteroScalarUnchanged logic).
     */
    private static function syncReportScalarChanged(string $field, $old, $new): bool
    {
        if ($field === 'is_locked') {
            return (bool) $old !== (bool) $new;
        }

        if (in_array($field, ['balance', 'quota_value', 'line_discount'], true)) {
            return abs((float) $old - (float) $new) >= 0.00001;
        }

        if ($field === 'order_sequence') {
            return (int) $old !== (int) $new;
        }

        $so = $old === null ? '' : trim((string) $old);
        $sn = $new === null ? '' : trim((string) $new);
        if ($field === 'email') {
            return strtolower($so) !== strtolower($sn);
        }

        return $so !== $sn;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Bulk client sync job failed", [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
