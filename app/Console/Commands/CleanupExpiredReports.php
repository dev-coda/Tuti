<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupExpiredReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired reports and their files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of expired reports...');

        // Get expired reports
        $expiredReports = Report::expired()->get();
        $count = $expiredReports->count();

        if ($count === 0) {
            $this->info('No expired reports found.');
            return 0;
        }

        $this->info("Found {$count} expired report(s).");

        $deleted = 0;
        $errors = 0;

        foreach ($expiredReports as $report) {
            try {
                // Delete file if exists
                if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
                    Storage::disk('local')->delete($report->file_path);
                }

                // Delete report record
                $report->delete();
                $deleted++;

                $this->line("Deleted report ID {$report->id} ({$report->name})");
            } catch (\Exception $e) {
                $errors++;
                Log::error("Failed to delete expired report", [
                    'report_id' => $report->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to delete report ID {$report->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cleanup completed. Deleted: {$deleted}, Errors: {$errors}");

        return 0;
    }
}
