<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->whereRelation('roles', 'name', 'admin')
            ->when(request('q'), function ($query, $q) {
                $query->where('name', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%");
            })

            ->orderBy('name')
            ->paginate();

        $context = compact('users'); 

        return view('admins.index' , $context);
    }

    public function create()
    {
        return view('admins.create');
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

        $user->assignRole('admin');

        return to_route('admins.index')->with('success', 'Usuario creado');
    }


    public function edit($id)
    {
        $user = User::findorFail($id);
        $context = compact('user');

        return view('admins.edit', $context);
    }


    public function update(Request $request, $id)
    {

        $user = User::findorFail($id);
        $validations =  [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations);

        if ($request->filled('password')) {
            $validate['password']  = bcrypt($validate['password']);
        }

        $user->update($validate);


        return to_route('admins.index')->with('success', 'Usuario actualizado');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return to_route('admins.index')->with('success', 'Usuario eliminado');
    }
}
