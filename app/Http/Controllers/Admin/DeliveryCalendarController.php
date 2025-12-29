<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCalendar;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeliveryCalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $calendars = DeliveryCalendar::query()
            ->when($request->year, function ($query, $year) {
                $query->where('year', 'like', "%{$year}%");
            })
            ->when($request->cycle, function ($query, $cycle) {
                $query->where('cycle', $cycle);
            })
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        $context = compact('calendars');
        return view('delivery-calendars.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('delivery-calendars.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'year' => 'required|string|max:255',
            'month' => 'required|string|max:255',
            'week_number' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cycle' => 'required|in:A,B,C',
        ]);

        DeliveryCalendar::create($validate);

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega creado');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeliveryCalendar $delivery_calendar)
    {
        return view('delivery-calendars.edit', ['deliveryCalendar' => $delivery_calendar]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryCalendar $delivery_calendar)
    {
        $validate = $request->validate([
            'year' => 'required|string|max:255',
            'month' => 'required|string|max:255',
            'week_number' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cycle' => 'required|in:A,B,C',
        ]);

        $delivery_calendar->update($validate);

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryCalendar $delivery_calendar)
    {
        $delivery_calendar->delete();

        return to_route('delivery-calendars.index')->with('success', 'Calendario de entrega eliminado');
    }

    /**
     * Show import form
     */
    public function showImport()
    {
        return view('delivery-calendars.import');
    }

    /**
     * Import delivery calendar entries from CSV
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
            while (($data = fgetcsv($handle)) !== false) {
                try {
                    if (count($data) < 6) {
                        $errors[] = "Fila " . ($imported + count($errors) + 1) . ": columnas insuficientes";
                        continue;
                    }

                    // Parse: year, month, week_number, start_date, end_date, cycle
                    $year = str_replace('.', '', trim($data[0]));
                    $month = trim($data[1]);
                    $weekNumber = (int) trim($data[2]);
                    $cycle = strtoupper(trim($data[5]));

                    if (!in_array($cycle, ['A', 'B', 'C'])) {
                        $errors[] = "Fila " . ($imported + count($errors) + 1) . ": ciclo inválido '{$cycle}'";
                        continue;
                    }

                    // Parse dates (format: d/m/Y)
                    try {
                        $startDate = Carbon::createFromFormat('d/m/Y', trim($data[3]));
                        $endDate = Carbon::createFromFormat('d/m/Y', trim($data[4]));
                    } catch (\Exception $e) {
                        $errors[] = "Fila " . ($imported + count($errors) + 1) . ": formato de fecha inválido";
                        continue;
                    }

                    // Check for duplicates
                    $existing = DeliveryCalendar::where('year', $year)
                        ->where('week_number', $weekNumber)
                        ->where('cycle', $cycle)
                        ->first();
                    
                    if ($existing) {
                        $duplicates++;
                        continue;
                    }

                    DeliveryCalendar::create([
                        'year' => $year,
                        'month' => $month,
                        'week_number' => $weekNumber,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'cycle' => $cycle,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($imported + count($errors) + 1) . ": " . $e->getMessage();
                }
            }

            fclose($handle);
        }

        return redirect()->route('delivery-calendars.index')->with([
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
     * Export CSV template
     */
    public function exportTemplate()
    {
        $filename = 'delivery_calendar_template_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Write sample data rows (format: year, month, week_number, start_date, end_date, cycle)
            $sampleData = [
                ['2.025', 'Enero', '3', '1/1/2025', '19/1/2025', 'A'],
                ['2.025', 'Enero', '4', '20/1/2025', '26/1/2025', 'B'],
                ['2.025', 'Enero', '5', '27/1/2025', '2/2/2025', 'C'],
            ];

            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

