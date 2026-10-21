<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ShippingMethod;
use App\Services\ExpressVisibilityDebugger;
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
    public function edit(ShippingMethod $shippingMethod, Request $request, ExpressVisibilityDebugger $debugger)
    {
        $cities = City::query()
            ->with('state')
            ->orderBy('name')
            ->get();

        $cityEnabled = $shippingMethod->cities()
            ->pluck('city_shipping_method.enabled', 'cities.id')
            ->map(fn ($enabled) => $enabled !== false && $enabled !== 0 && $enabled !== '0')
            ->all();

        $diagnoseCityId = $request->integer('diagnose_city') ?: null;
        $diagnosis = null;
        if ($shippingMethod->code === 'express' && $diagnoseCityId) {
            $diagnosis = $debugger->forCity($diagnoseCityId);
        }

        return view('admin.shipping-methods.edit', compact(
            'shippingMethod',
            'cities',
            'cityEnabled',
            'diagnoseCityId',
            'diagnosis'
        ));
    }

    /**
     * Update the specified shipping method (metadata only — cities use toggleCity).
     */
    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'restrict_cities' => 'boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        $validated['enabled'] = $request->has('enabled');
        $wasRestrict = (bool) $shippingMethod->restrict_cities;
        $validated['restrict_cities'] = $request->has('restrict_cities');

        $shippingMethod->update($validated);

        // Switching modes clears city overrides so old opt-out rows never become allowlist rows by accident.
        if ($wasRestrict !== $shippingMethod->restrict_cities) {
            $shippingMethod->cities()->detach();
        }

        $message = 'Método de envío actualizado correctamente';
        if ($wasRestrict !== $shippingMethod->restrict_cities) {
            $message .= $shippingMethod->restrict_cities
                ? '. Modo piloto activado: activa ciudades una por una (la lista quedó vacía a propósito).'
                : '. Modo global activado: exclusiones anteriores se limpiaron; desactiva ciudades una por una si hace falta.';
        }

        return redirect()->route('shipping-methods.edit', $shippingMethod)
            ->with('success', $message);
    }

    /**
     * Toggle the enabled status of a shipping method.
     */
    public function toggle(ShippingMethod $shippingMethod)
    {
        $shippingMethod->update(['enabled' => ! $shippingMethod->enabled]);

        $status = $shippingMethod->enabled ? 'habilitado' : 'deshabilitado';

        return back()->with('success', "Método de envío {$status} correctamente");
    }

    /**
     * Enable or disable this method for exactly one city (other cities untouched).
     */
    public function toggleCity(Request $request, ShippingMethod $shippingMethod, City $city)
    {
        $validated = $request->validate([
            'enabled' => 'required|in:0,1',
        ]);

        $enabled = $validated['enabled'] === '1';
        $shippingMethod->setCityEnabled($city->id, $enabled);

        $status = $enabled ? 'activado' : 'desactivado';

        return back()->with(
            'success',
            "{$shippingMethod->name}: {$status} solo para {$city->name}. Las demás ciudades no cambiaron."
        );
    }

    /**
     * JSON diagnosis of Entrega Especial visibility for a city or client city_code.
     */
    public function diagnose(Request $request, ExpressVisibilityDebugger $debugger)
    {
        $validated = $request->validate([
            'city_id' => 'nullable|integer|exists:cities,id',
            'city_code' => 'nullable|string|max:32',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (! empty($validated['user_id'])) {
            $user = \App\Models\User::query()->findOrFail($validated['user_id']);

            return response()->json($debugger->forUser($user));
        }

        if (! empty($validated['city_code']) && empty($validated['city_id'])) {
            $resolved = City::findIdByDaneCode($validated['city_code']);
            $payload = $debugger->forCity($resolved);
            $payload['city_code'] = $validated['city_code'];
            $payload['city_source'] = 'city_code→DANE→cities.id';

            return response()->json($payload);
        }

        return response()->json(
            $debugger->forCity(isset($validated['city_id']) ? (int) $validated['city_id'] : null)
        );
    }
}