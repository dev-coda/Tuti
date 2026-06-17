<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->whereRelation('roles', 'name', 'supervisor')
            ->when(request('q'), function ($query, $q) {
                $query->where(function ($search) use ($q) {
                    $search->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%")
                        ->orWhere('zone', 'ilike', "%{$q}%");
                });
            })
            ->when(filled(request('zone')), function ($query) {
                $query->where('zone', request('zone'));
            })
            ->orderBy('name')
            ->paginate();

        $zones = User::whereRelation('roles', 'name', 'supervisor')->distinct()->pluck('zone')->sort();

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
            'zone' => ['required', 'integer'],
        ]);

        $validate['password'] = bcrypt($validate['password']);

        $user = User::create($validate);
        $user->assignRole('supervisor');

        return to_route('supervisors.index')->with('success', 'Supervisor creado');
    }

    public function edit($id)
    {
        $user = User::findorFail($id);

        return view('supervisors.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findorFail($id);
        $validations = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'zone' => 'required|integer',
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations);

        if ($request->filled('password')) {
            $validate['password'] = bcrypt($validate['password']);
        }

        $user->update($validate);

        if (! $user->hasRole('supervisor')) {
            $user->assignRole('supervisor');
        }

        return to_route('supervisors.index')->with('success', 'Supervisor actualizado');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return to_route('supervisors.index')->with('success', 'Supervisor eliminado');
    }
}
