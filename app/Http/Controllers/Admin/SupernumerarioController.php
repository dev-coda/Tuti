<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SupernumerarioController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->whereRelation('roles', 'name', 'supernumerario')
            ->when(request('q'), function ($query, $q) {
                $query->where('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate();

        return view('supernumerarios.index', compact('users'));
    }

    public function create()
    {
        return view('supernumerarios.create');
    }

    public function store(Request $request)
    {
        $coversAllZones = $this->requestedCoversAllZones($request, true);
        if ($request->input('zone') === '' || $request->input('zone') === null) {
            $request->merge(['zone' => null]);
        }

        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'zone' => [$coversAllZones ? 'nullable' : 'required', 'integer'],
        ], [
            'zone.required' => 'Indica la zona cuando el supernumerario no cubre todas las zonas.',
        ]);

        $validate['password'] = bcrypt($validate['password']);
        $validate['covers_all_zones'] = $coversAllZones;
        $validate['zone'] = $coversAllZones ? null : ($validate['zone'] ?? null);

        $user = User::create($validate);
        $user->assignRole('supernumerario');

        return to_route('supernumerarios.index')->with('success', 'Supernumerario creado');
    }

    public function edit($id)
    {
        $user = User::findorFail($id);

        return view('supernumerarios.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findorFail($id);
        $coversAllZones = $this->requestedCoversAllZones($request, $user->coversAllZones());
        if ($request->input('zone') === '' || $request->input('zone') === null) {
            $request->merge(['zone' => null]);
        }
        $validations = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'zone' => [$coversAllZones ? 'nullable' : 'required', 'integer'],
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations, [
            'zone.required' => 'Indica la zona cuando el supernumerario no cubre todas las zonas.',
        ]);

        if ($request->filled('password')) {
            $validate['password'] = bcrypt($validate['password']);
        }

        if ($request->exists('covers_all_zones')) {
            $validate['covers_all_zones'] = $coversAllZones;
            $validate['zone'] = $coversAllZones ? null : ($validate['zone'] ?? $user->zone);
        }

        $user->update($validate);

        if (! $user->hasRole('supernumerario')) {
            $user->assignRole('supernumerario');
        }

        return to_route('supernumerarios.index')->with('success', 'Supernumerario actualizado');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return to_route('supernumerarios.index')->with('success', 'Supernumerario eliminado');
    }

    /**
     * Checkbox plus hidden 0 can arrive as a scalar or as [0, 1].
     * When the field is absent (older clients), keep $default.
     */
    private function requestedCoversAllZones(Request $request, bool $default): bool
    {
        if (! $request->exists('covers_all_zones')) {
            return $default;
        }

        $value = $request->input('covers_all_zones');
        if (is_array($value)) {
            $value = end($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
