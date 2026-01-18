<?php

namespace App\Jobs;

use App\Models\User;
use App\Repositories\UserRepository;
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

                // Store original data to detect changes
                $originalData = $user->only([
                    'name',
                    'phone',
                    'mobile_phone',
                    'whatsapp',
                    'business_name',
                    'account_num',
                    'city_code',
                    'county_id',
                    'customer_type',
                    'price_group',
                    'tax_group',
                    'line_discount',
                    'balance',
                    'quota_value',
                    'customer_status',
                    'is_locked',
                    'order_sequence',
                ]);
                $originalZonesCount = $user->zones()->count();

                // Perform the sync
                $syncSuccess = UserRepository::syncUserRuteroData($user);

                if ($syncSuccess) {
                    $user->refresh();

                    // Detect which fields were updated
                    $updatedFields = [];
                    $newData = $user->only(array_keys($originalData));

                    foreach ($originalData as $field => $originalValue) {
                        $newValue = $newData[$field] ?? null;
                        if ($originalValue != $newValue) {
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

        // Generate CSV report
        $this->generateReport($results);

        Log::info("Bulk client sync completed", [
            'session_id' => $this->sessionId,
            'total_users' => $totalUsers,
            'processed' => $processed,
        ]);
    }

    /**
     * Generate CSV report of the sync results
     */
    protected function generateReport(array $results): void
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
