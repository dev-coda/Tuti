<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $holidays = Holiday::query()
            ->orderBy('date')
            ->when($request->has('show_past') && $request->show_past, function ($query) {
                // Show all holidays if show_past parameter is set
                return $query;
            }, function ($query) {
                // Default: show only future holidays
                return $query->where('date', '>=', now());
            })
            ->when($request->type_id && $request->type_id > 0, function ($query, $type_id) {
                return $query->where('type_id', $type_id);
            })
            ->paginate();

        $context = compact('holidays');

        return view('holidays.index', $context);
    }

    /**
     * Display the specified resource.
     */
    public function show(Holiday $holiday)
    {
        return view('holidays.show', compact('holiday'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Holiday $holiday)
    {
        return view('holidays.edit', compact('holiday'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('holidays.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'type_id' => 'required',
            'date' => 'required|date',
        ]);

        if ($validate['type_id'] == Holiday::SATURDAY) {
            //validate if date is saturday
            if (date('N', strtotime($validate['date'])) != 6) {
                return back()->with('error', 'La fecha no es un sábado')->withInput();
            }
        }


        Holiday::create($validate);

        return to_route('holidays.index')->with('success', 'Festivo creado');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $validate = $request->validate([
            'type_id' => 'required',
            'date' => 'required|date',
        ]);

        if ($validate['type_id'] == Holiday::SATURDAY) {
            //validate if date is saturday
            if (date('N', strtotime($validate['date'])) != 6) {
                return back()->with('error', 'La fecha no es un sábado')->withInput();
            }
        }

        $holiday->update($validate);

        return to_route('holidays.index')->with('success', 'Festivo actualizado');
    }

    /**
     * Show all holidays data for debugging.
     */
    public function debug()
    {
        $holidays = Holiday::query()
            ->orderBy('date', 'desc') // Show most recent first for debugging
            ->get();

        $totalCount = $holidays->count();
        $holidayCount = $holidays->where('type_id', Holiday::HOLIDAY)->count();
        $saturdayCount = $holidays->where('type_id', Holiday::SATURDAY)->count();

        $context = compact('holidays', 'totalCount', 'holidayCount', 'saturdayCount');

        return view('holidays.debug', $context);
    }

    /**
     * Export holidays to CSV.
     */
    public function export(Request $request)
    {
        // Check if this is a template request
        if ($request->has('template')) {
            return $this->exportTemplate();
        }

        $holidays = Holiday::query()
            ->orderBy('date')
            ->get();

        $filename = 'holidays_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($holidays) {
            $file = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($file, ['ID', 'Type', 'Type_ID', 'Date', 'Day', 'Created_At', 'Updated_At']);

            // Write data rows
            foreach ($holidays as $holiday) {
                fputcsv($file, [
                    $holiday->id,
                    $holiday->type,
                    $holiday->type_id,
                    $holiday->date->format('Y-m-d'),
                    $holiday->day,
                    $holiday->created_at?->format('Y-m-d H:i:s'),
                    $holiday->updated_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export CSV template for import.
     */
    private function exportTemplate()
    {
        $filename = 'holidays_template_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($file, ['ID', 'Type', 'Type_ID', 'Date', 'Day', 'Created_At', 'Updated_At']);

            // Write sample data rows
            $sampleData = [
                ['', 'Festivo', '1', '2024-12-25', 'Miércoles', '', ''],
                ['', 'Festivo', '1', '2024-12-31', 'Martes', '', ''],
                ['', 'Sábado', '2', '2024-12-28', 'Sábado', '', ''],
                ['', 'Festivo', '1', '2025-01-01', 'Miércoles', '', ''],
            ];

            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show import form.
     */
    public function showImport()
    {
        return view('holidays.import');
    }

    /**
     * Import holidays from CSV.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        $imported = 0;
        $errors = [];
        $duplicates = 0;

        if (($handle = fopen($path, 'r')) !== false) {
            $header = fgetcsv($handle); // Skip header row

            while (($data = fgetcsv($handle)) !== false) {
                try {
                    // Skip if less than 4 columns
                    if (count($data) < 4) {
                        $errors[] = "Fila " . ($imported + count($errors) + 2) . ": insuficientes columnas";
                        continue;
                    }

                    $typeId = trim($data[2]); // Type_ID column
                    $dateStr = trim($data[3]); // Date column

                    // Validate type_id
                    if (!in_array($typeId, [1, 2])) {
                        $errors[] = "Fila " . ($imported + count($errors) + 2) . ": type_id debe ser 1 (Festivo) o 2 (Sábado)";
                        continue;
                    }

                    // Validate and parse date
                    try {
                        $date = Carbon::createFromFormat('Y-m-d', $dateStr);
                    } catch (\Exception $e) {
                        $errors[] = "Fila " . ($imported + count($errors) + 2) . ": fecha inválida '{$dateStr}' (formato esperado: YYYY-MM-DD)";
                        continue;
                    }

                    // Validate Saturday dates
                    if ($typeId == Holiday::SATURDAY && $date->dayOfWeek != 6) {
                        $errors[] = "Fila " . ($imported + count($errors) + 2) . ": fecha '{$dateStr}' no es sábado";
                        continue;
                    }

                    // Check for duplicates
                    $existing = Holiday::where('date', $date)->where('type_id', $typeId)->first();
                    if ($existing) {
                        $duplicates++;
                        continue;
                    }

                    // Create holiday
                    Holiday::create([
                        'type_id' => $typeId,
                        'date' => $date,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($imported + count($errors) + 2) . ": " . $e->getMessage();
                }
            }

            fclose($handle);
        }

        return redirect()->route('holidays.index')->with([
            'success' => "Importación completada. {$imported} registros importados.",
            'import_stats' => [
                'imported' => $imported,
                'duplicates' => $duplicates,
                'errors' => count($errors),
                'error_details' => $errors
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return to_route('holidays.index')->with('success', 'Festivo eliminado');
    }
}
