<?php

namespace App\Jobs;

use App\Models\Report;
use App\Reports\UserEmailReport;
use App\Exports\UserEmailReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GenerateUserEmailReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $report;

    /**
     * Create a new job instance.
     */
    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Mark as processing
            $this->report->update([
                'status' => Report::STATUS_PROCESSING,
            ]);

            // Generate report data
            $reportGenerator = new UserEmailReport();
            $data = $reportGenerator->generate();

            // Generate filename
            $filename = 'reporte_correos_usuarios_' . now()->format('Y-m-d_His') . '.xlsx';
            $filePath = 'reports/' . $this->report->user_id . '/' . $filename;

            // Generate Excel file
            $export = new UserEmailReportExport($data);
            Excel::store($export, $filePath, 'local');

            // Update report record
            $this->report->update([
                'status' => Report::STATUS_COMPLETED,
                'filename' => $filename,
                'file_path' => $filePath,
                'data' => $data,
                'completed_at' => now(),
                'expires_at' => now()->addWeek(), // Expires in 1 week
            ]);

            Log::info("User email report generated successfully", [
                'report_id' => $this->report->id,
                'user_id' => $this->report->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to generate user email report", [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->report->update([
                'status' => Report::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
