<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Display a listing of shipping methods.
     */
    public function index()
    {
        $shippingMethods = ShippingMethod::query()
            ->orderBy('sort_order')
            ->withCount([
                'cities as allowed_cities_count' => fn ($q) => $q->where('city_shipping_method.enabled', true),
                'cities as blocked_cities_count' => fn ($q) => $q->where('city_shipping_method.enabled', false),
            ])
            ->get();

        return view('admin.shipping-methods.index', compact('shippingMethods'));
    }

    /**
     * Show the form for editing a shipping method.
     */
    public function edit(ShippingMethod $shippingMethod)
    {
        $cities = City::query()
            ->with('state')
            ->orderBy('name')
            ->get();

        $cityEnabled = $shippingMethod->cities()
            ->pluck('city_shipping_method.enabled', 'cities.id')
            ->map(fn ($enabled) => $enabled !== false && $enabled !== 0 && $enabled !== '0')
            ->all();

        return view('admin.shipping-methods.edit', compact('shippingMethod', 'cities', 'cityEnabled'));
    }

    /**
     * Update the specified shipping method.
     */
    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'restrict_cities' => 'boolean',
            'sort_order' => 'required|integer|min:0',
            'city_enabled' => 'nullable|array',
            'city_enabled.*' => 'in:0,1',
        ]);

        $validated['enabled'] = $request->has('enabled');
        $validated['restrict_cities'] = $request->has('restrict_cities');
        $cityEnabled = $validated['city_enabled'] ?? [];
        unset($validated['city_enabled']);

        $shippingMethod->update($validated);
        $this->syncCityAvailability($shippingMethod, $cityEnabled);

        return redirect()->route('shipping-methods.index')
            ->with('success', 'Método de envío actualizado correctamente');
    }

    /**
     * Toggle the enabled status of a shipping method.
     */
    public function toggle(ShippingMethod $shippingMethod)
    {
        $shippingMethod->update(['enabled' => !$shippingMethod->enabled]);

        $status = $shippingMethod->enabled ? 'habilitado' : 'deshabilitado';
        
        return back()->with('success', "Método de envío {$status} correctamente");
    }

    /**
     * Persist per-city availability.
     *
     * Opt-out (restrict_cities = false): store only explicit disables.
     * Allowlist (restrict_cities = true): store only explicit enables.
     *
     * @param  array<string, mixed>  $cityEnabled
     */
    private function syncCityAvailability(ShippingMethod $shippingMethod, array $cityEnabled): void
    {
        $cityIds = City::query()->pluck('id');
        $sync = [];
        $default = $shippingMethod->restrict_cities ? '0' : '1';

        foreach ($cityIds as $cityId) {
            $allowed = ($cityEnabled[(string) $cityId] ?? $cityEnabled[$cityId] ?? $default) === '1';

            if ($shippingMethod->restrict_cities) {
                if ($allowed) {
                    $sync[$cityId] = ['enabled' => true];
                }

                continue;
            }

            if ($allowed) {
                continue;
            }

            $sync[$cityId] = ['enabled' => false];
        }

        $shippingMethod->cities()->sync($sync);
    }
}
