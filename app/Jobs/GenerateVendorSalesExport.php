<?php

namespace App\Jobs;

use App\Exports\VendorSalesExport;
use App\Models\ExportFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateVendorSalesExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $exportFileId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $exportFile = ExportFile::query()->find($this->exportFileId);
        if (! $exportFile || $exportFile->status === ExportFile::STATUS_FAILED) {
            return;
        }

        $exportFile->markAsProcessing();

        $params = $exportFile->params ?? [];
        $dateFrom = (string) ($params['date_from'] ?? '');
        $dateTo = (string) ($params['date_to'] ?? '');
        $vendorIds = array_values(array_map('intval', (array) ($params['vendor_ids'] ?? [])));

        if ($dateFrom === '' || $dateTo === '' || $vendorIds === []) {
            $exportFile->markAsFailed('Faltan fechas o proveedores para generar el reporte.');

            return;
        }

        ini_set('memory_limit', '512M');

        $export = new VendorSalesExport($dateFrom, $dateTo, $vendorIds);
        Excel::store($export, $exportFile->file_path, 'local');

        $total = $export->salesQuery()->count()
            + $export->bonificationsQuery()->count();

        $exportFile->markAsCompleted($total);

        Log::info('Vendor sales export completed', [
            'export_file_id' => $exportFile->id,
            'total_records' => $total,
            'file_path' => $exportFile->file_path,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $exportFile = ExportFile::query()->find($this->exportFileId);
        if (! $exportFile) {
            return;
        }

        $message = $exception?->getMessage() ?: 'Vendor sales export failed.';
        $exportFile->markAsFailed($message);

        Log::error('Vendor sales export failed', [
            'export_file_id' => $this->exportFileId,
            'error' => $message,
        ]);
    }
}
