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
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validate['password'] = bcrypt($validate['password']);

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
        $validations = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations);

        if ($request->filled('password')) {
            $validate['password'] = bcrypt($validate['password']);
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
}
