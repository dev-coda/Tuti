<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentPlaceholderZone;
use App\Services\DepartmentPlaceholderZoneService;
use Illuminate\Http\Request;

class DepartmentPlaceholderZoneController extends Controller
{
    public function index(DepartmentPlaceholderZoneService $placeholders)
    {
        $placeholders->syncCatalogFromPreferredCities();

        $rows = DepartmentPlaceholderZone::query()
            ->with(['state', 'city'])
            ->get()
            ->sortBy(fn (DepartmentPlaceholderZone $row) => mb_strtolower($row->state?->name ?? ''))
            ->values();

        return view('admin.department-placeholder-zones.index', compact('rows'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => 'required|integer|exists:department_placeholder_zones,id',
            'rows.*.zone' => 'nullable|string|max:8',
            'rows.*.route' => 'nullable|string|max:8',
            'rows.*.day' => 'nullable|string|max:32',
            'rows.*.dane_code' => 'nullable|string|max:16',
            'rows.*.address' => 'nullable|string|max:255',
            'rows.*.enabled' => 'nullable|in:0,1',
        ]);

        foreach ($validated['rows'] as $payload) {
            $row = DepartmentPlaceholderZone::query()->find($payload['id']);
            if (! $row) {
                continue;
            }

            $row->update([
                'zone' => $this->normalizeCode($payload['zone'] ?? null),
                'route' => $this->normalizeCode($payload['route'] ?? null),
                'day' => trim((string) ($payload['day'] ?? '')) ?: null,
                'dane_code' => $this->normalizeCode($payload['dane_code'] ?? null),
                'address' => trim((string) ($payload['address'] ?? '')) ?: null,
                'enabled' => ($payload['enabled'] ?? '0') === '1',
            ]);
        }

        return back()->with('success', 'Zonas placeholder por departamento actualizadas.');
    }

    private function normalizeCode(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' ? $value : null;
    }
}
