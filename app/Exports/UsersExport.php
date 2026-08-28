<?php

namespace App\Exports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @deprecated Prefer {@see \App\Jobs\ExportClientsJob} (streaming CSV).
 * Kept for row/heading parity in case a small sync Excel export is needed.
 */
class UsersExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, WithBatchInserts, ShouldQueue
{
    use Exportable;

    public function query()
    {
        return ClientsExportRows::clientsQuery()->orderBy('name');
    }

    public function map($user): array
    {
        return ClientsExportRows::map($user);
    }

    public function headings(): array
    {
        return ClientsExportRows::headings();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }
}
