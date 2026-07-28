<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupervisorController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('supervisorRoutes')
            ->whereRelation('roles', 'name', 'supervisor')
            ->when(request('q'), function ($query, $q) {
                $query->where(function ($search) use ($q) {
                    $search->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%")
                        ->orWhere('zone', 'ilike', "%{$q}%")
                        ->orWhereHas('supervisorRoutes', function ($routes) use ($q) {
                            $routes->where('zone', 'ilike', "%{$q}%");
                        });
                });
            })
            ->when(filled(request('zone')), function ($query) {
                $zone = request('zone');
                $query->where(function ($sub) use ($zone) {
                    $sub->where('zone', $zone)
                        ->orWhereHas('supervisorRoutes', fn ($routes) => $routes->where('zone', $zone));
                });
            })
            ->orderBy('name')
            ->paginate();

        $zones = User::whereRelation('roles', 'name', 'supervisor')
            ->distinct()
            ->pluck('zone')
            ->merge(
                \App\Models\SupervisorRoute::query()
                    ->whereHas('user', fn ($q) => $q->whereRelation('roles', 'name', 'supervisor'))
                    ->distinct()
                    ->pluck('zone')
            )
            ->filter(fn ($zone) => filled($zone))
            ->unique()
            ->sort()
            ->values();

        return view('supervisors.index', compact('users', 'zones'));
    }

    public function create()
    {
        return view('supervisors.create');
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'zone' => ['nullable', 'integer'],
        ]);

        $assignments = $this->validatedAssignments($request);

        $validate['password'] = bcrypt($validate['password']);

        $user = User::create($validate);
        $user->assignRole('supervisor');
        $this->syncAssignments($user, $assignments);

        return to_route('supervisors.index')->with('success', 'Supervisor creado');
    }

    public function edit($id)
    {
        $user = User::with('supervisorRoutes')->findorFail($id);

        return view('supervisors.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findorFail($id);
        $validations = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'zone' => 'nullable|integer',
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations);

        $assignments = $this->validatedAssignments($request);

        if ($request->filled('password')) {
            $validate['password'] = bcrypt($validate['password']);
        }

        $user->update($validate);

        if (! $user->hasRole('supervisor')) {
            $user->assignRole('supervisor');
        }

        $this->syncAssignments($user, $assignments);

        return to_route('supervisors.index')->with('success', 'Supervisor actualizado');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return to_route('supervisors.index')->with('success', 'Supervisor eliminado');
    }

    /**
     * Normalize the repeatable zone rows: drop empties, reject non-numeric
     * values and de-duplicate zones.
     *
     * @return array<int, array{zone: string, route: null}>
     */
    private function validatedAssignments(Request $request): array
    {
        $rows = collect($request->input('assignments', []))
            ->map(fn ($row) => trim((string) ($row['zone'] ?? '')))
            ->reject(fn ($zone) => $zone === '')
            ->values();

        $invalid = $rows->first(fn ($zone) => ! preg_match('/^\d{1,10}$/', $zone));

        if ($invalid !== null) {
            throw ValidationException::withMessages([
                'assignments' => 'Cada zona asignada debe ser numérica.',
            ]);
        }

        return $rows
            ->unique()
            ->map(fn ($zone) => ['zone' => $zone, 'route' => null])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{zone: string, route: null}>  $assignments
     */
    private function syncAssignments(User $user, array $assignments): void
    {
        $user->supervisorRoutes()->delete();

        foreach ($assignments as $assignment) {
            $user->supervisorRoutes()->create($assignment);
        }
    }
}
