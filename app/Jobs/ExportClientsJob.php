<?php

namespace App\Jobs;

use App\Exports\ClientsExportRows;
use App\Models\ExportFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stream all clients to a CSV on disk.
 *
 * PhpSpreadsheet XLSX queued exports load the growing workbook into memory and
 * die under Horizon's default 60s/128MB limits (~90k clients). CSV + chunkById
 * keeps memory flat and finishes on the dedicated exports queue.
 */
class ExportClientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $exportFileId;

    public function __construct(int $exportFileId)
    {
        $this->exportFileId = $exportFileId;
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $exportFile = ExportFile::query()->find($this->exportFileId);
        if (! $exportFile) {
            return;
        }

        if ($exportFile->status === ExportFile::STATUS_FAILED) {
            return;
        }

        $exportFile->markAsProcessing();

        $relativePath = $exportFile->file_path;
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create export directory: {$directory}");
        }

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open export file for writing: {$absolutePath}");
        }

        $total = 0;

        try {
            // Excel-friendly UTF-8 BOM so accents open correctly on Windows.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ClientsExportRows::headings());

            ClientsExportRows::clientsQuery()
                ->orderBy('id')
                ->chunkById(500, function ($users) use ($handle, &$total) {
                    foreach ($users as $user) {
                        fputcsv($handle, ClientsExportRows::map($user));
                        $total++;
                    }
                });
        } finally {
            fclose($handle);
        }

        $exportFile->markAsCompleted($total);

        Log::info('Clients CSV export completed', [
            'export_file_id' => $exportFile->id,
            'total_records' => $total,
            'file_path' => $relativePath,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $exportFile = ExportFile::query()->find($this->exportFileId);
        if (! $exportFile) {
            return;
        }

        $message = $exception?->getMessage() ?: 'Client export failed.';
        $exportFile->markAsFailed($message);

        Log::error('Clients CSV export failed', [
            'export_file_id' => $this->exportFileId,
            'error' => $message,
        ]);
    }
}
