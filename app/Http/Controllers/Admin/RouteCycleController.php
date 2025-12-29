<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RouteCycle;
use Illuminate\Http\Request;

class RouteCycleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $routeCycles = RouteCycle::query()
            ->when($request->route, function ($query, $route) {
                $query->where('route', 'like', "%{$route}%");
            })
            ->when($request->cycle, function ($query, $cycle) {
                $query->where('cycle', $cycle);
            })
            ->orderBy('route')
            ->paginate(20);

        $context = compact('routeCycles');
        return view('route-cycles.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('route-cycles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validate = $request->validate([
            'route' => 'required|string|max:255|unique:route_cycles,route',
            'cycle' => 'required|in:A,B,C',
        ]);

        RouteCycle::create($validate);

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta creado');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RouteCycle $route_cycle)
    {
        return view('route-cycles.edit', ['routeCycle' => $route_cycle]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RouteCycle $route_cycle)
    {
        $validate = $request->validate([
            'route' => 'required|string|max:255|unique:route_cycles,route,' . $route_cycle->id,
            'cycle' => 'required|in:A,B,C',
        ]);

        $route_cycle->update($validate);

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta actualizado');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RouteCycle $route_cycle)
    {
        $route_cycle->delete();

        return to_route('route-cycles.index')->with('success', 'Ciclo de ruta eliminado');
    }

    /**
     * Show import form
     */
    public function showImport()
    {
        return view('route-cycles.import');
    }

    /**
     * Import route cycles from CSV
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
                    if (count($data) < 2) {
                        $errors[] = "Fila " . ($imported + count($errors) + 1) . ": columnas insuficientes";
                        continue;
                    }

                    $route = trim($data[0]);
                    $cycle = strtoupper(trim($data[1]));

                    if (!in_array($cycle, ['A', 'B', 'C'])) {
                        $errors[] = "Fila " . ($imported + count($errors) + 1) . ": ciclo inválido '{$cycle}'";
                        continue;
                    }

                    // Check for duplicates
                    $existing = RouteCycle::where('route', $route)->first();
                    
                    if ($existing) {
                        $duplicates++;
                        continue;
                    }

                    RouteCycle::create([
                        'route' => $route,
                        'cycle' => $cycle,
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Fila " . ($imported + count($errors) + 1) . ": " . $e->getMessage();
                }
            }

            fclose($handle);
        }

        return redirect()->route('route-cycles.index')->with([
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
        $filename = 'route_cycles_template_' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Write sample data rows (format: route, cycle)
            $sampleData = [
                ['1300', 'A'],
                ['1301', 'C'],
                ['1302', 'A'],
                ['1303', 'B'],
            ];

            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

