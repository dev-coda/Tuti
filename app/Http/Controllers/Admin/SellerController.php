<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SellersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->whereRelation('roles', 'name', 'seller')
            ->when(request('q'), function ($query, $q) {
                $query->where('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%")
                    ->orWhere('zone', 'ilike', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate();

        $context = compact('users');

        return view('sellers.index', $context);
    }

    public function create()
    {
        return view('sellers.create');
    }

    public function store(Request $request)
    {
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'zone'=>['required', 'integer'],
        ]);

        $validate['password'] = bcrypt($validate['password']);

        $user = User::create($validate);


        $user->assignRole('seller');

        return to_route('sellers.index')->with('success', 'Usuario creado');
    }


    public function edit($id)
    {
        $user = User::findorFail($id);
        $context = compact('user');

        return view('sellers.edit', $context);
    }


    public function update(Request $request, $id)
    {
        $user = User::findorFail($id);
        $validations =  [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'zone' => 'required|integer',
        ];

        if ($request->filled('password')) {
            $validations['password'] = 'required|confirmed';
        }

        $validate = $request->validate($validations);

        if ($request->filled('password')) {
            $validate['password']  = bcrypt($validate['password']);
        }

        $user->update($validate);

      


        return to_route('sellers.index')->with('success', 'Vendedor actualizado');
    }


    public function setclient(Request $request){
        $validate = $request->validate([
            'document' => 'required|integer',
        ]);
    
        $document = $validate['document'];
        $zone = $request->zone || null;
        $user = User::whereDocument($document)->first();
 
        if(!$user){
    
            $data = UserRepository::getCustomRuteroId($document, $zone);
         
            if($data){
                $name = $data['name'];

                $email = time() . '@tuti.com';
                $password = bcrypt($email);
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'document' => $document,
                    'password' => $password,
                    'status_id' => User::PENDING
                ]);
              
                foreach ($data['routes'] as $route) {
                    $user->zones()->create([
                        'route' => $route['route'],
                        'zone' => $route['zone'],
                        'day' => $route['day'],
                        'address' => $route['address'],
                        'code' => $route['code'],
                    ]);
                }
              

                session()->put('user_id', $user->id);
                return to_route('cart');

    
            }else{
                return back()->with('error', 'No se encontró el rutero');    
            }
               
        }


        session()->put('user_id', $user->id);
        return to_route('cart');
    

         
    }

    public function removeclient(){
        session()->forget('user_id');
        return to_route('cart')->with('success', 'Cliente desvinculado');
    }

    public function export()
    {
        return Excel::download(new SellersExport, 'vendedores.xlsx');
    }
}
