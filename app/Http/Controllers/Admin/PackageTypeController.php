<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProductDimensions;
use App\Models\PackageType;
use App\Models\Product;
use App\Models\ProductDimensionSyncLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class PackageTypeController extends Controller
{
    public function index()
    {
        $packageTypes = PackageType::query()
            ->orderBy('position')
            ->orderBy('max_length_cm')
            ->get();

        $dimensionStats = [
            'total_products' => Product::count(),
            'with_dimensions' => Product::where(function ($query) {
                $query->where('coordinadora_weight_kg', '>', 0)
                    ->orWhere('coordinadora_height_cm', '>', 0)
                    ->orWhere('coordinadora_width_cm', '>', 0)
                    ->orWhere('coordinadora_length_cm', '>', 0);
            })->count(),
            'last_synced_at' => Setting::getByKey('product_dimensions_last_synced_at'),
        ];

        $syncLogs = ProductDimensionSyncLog::latest()->take(10)->get();

        return view('package-types.index', compact('packageTypes', 'dimensionStats', 'syncLogs'));
    }

    public function create()
    {
        return view('package-types.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        PackageType::create($data);

        return redirect()->route('package-types.index')->with('success', 'Tamaño de empaque creado.');
    }

    public function edit(PackageType $packageType)
    {
        return view('package-types.edit', compact('packageType'));
    }

    public function update(Request $request, PackageType $packageType)
    {
        $data = $this->validated($request, $packageType);

        $packageType->update($data);

        return redirect()->route('package-types.index')->with('success', 'Tamaño de empaque actualizado.');
    }

    public function destroy(PackageType $packageType)
    {
        $packageType->delete();

        return redirect()->route('package-types.index')->with('success', 'Tamaño de empaque eliminado.');
    }

    public function syncDimensions()
    {
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'redis';
        }

        SyncProductDimensions::dispatch()
            ->onConnection($queueConnection)
            ->onQueue('inventory');

        return redirect()->route('package-types.index')
            ->with('success', 'Sincronización de dimensiones encolada. Los resultados aparecerán en el registro.');
    }

    private function validated(Request $request, ?PackageType $packageType = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:package_types,code' . ($packageType ? ',' . $packageType->id : ''),
            'max_weight_kg' => 'required|numeric|min:0.001',
            'max_length_cm' => 'required|numeric|min:0.01',
            'max_width_cm' => 'required|numeric|min:0.01',
            'max_height_cm' => 'required|numeric|min:0.01',
            'position' => 'nullable|integer|min:0',
            'active' => 'nullable|in:1',
        ]);

        $data['active'] = $request->boolean('active');
        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }
}
