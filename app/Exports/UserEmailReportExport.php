<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UserEmailReportExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the report data as array
     */
    public function array(): array
    {
        $rows = [];

        // Title rows (will be merged in AfterSheet event)
        $rows[] = ['REPORTE DE CORREOS ELECTRÓNICOS DE USUARIOS', '', '', '', ''];
        $rows[] = ['Generado el: ' . ($this->data['generated_at'] ?? now()->format('Y-m-d H:i:s')), '', '', '', ''];
        $rows[] = ['', '', '', '', '']; // Empty row

        // Summary section
        $rows[] = ['Métrica', 'Valor', '', '', ''];
        $rows[] = ['Total de Usuarios Registrados', $this->data['total_users'], '', '', ''];
        $rows[] = ['Usuarios con Correo Electrónico', $this->data['users_with_email'], '', '', ''];
        $rows[] = ['Usuarios con Correos Sospechosos', $this->data['suspicious_users_count'], '', '', ''];
        $rows[] = ['', '', '', '', '']; // Empty row
        $rows[] = ['', '', '', '', '']; // Empty row

        // Suspicious users detail section
        $rows[] = ['ID', 'Nombre', 'Correo Electrónico', 'Documento', 'Fecha de Registro'];
        
        foreach ($this->data['suspicious_users'] as $user) {
            $rows[] = [
                $user['id'],
                $user['name'],
                $user['email'],
                $user['document'] ?? '',
                $user['created_at'],
            ];
        }

        return $rows;
    }


    /**
     * Get sheet title
     */
    public function title(): string
    {
        return 'Correos de Usuarios';
    }

    /**
     * Apply styles
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
            10 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 35,
            'D' => 20,
            'E' => 20,
        ];
    }

    /**
     * Register events
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Merge cells for title
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');
                
                // Apply borders to data sections
                $highestRow = $sheet->getHighestRow();
                
                // Summary section borders (rows 4-7)
                $summaryRange = 'A4:B7';
                $sheet->getStyle($summaryRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Suspicious users section borders (row 10 onwards)
                if ($highestRow > 10) {
                    $usersRange = 'A10:E' . $highestRow;
                    $sheet->getStyle($usersRange)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}

