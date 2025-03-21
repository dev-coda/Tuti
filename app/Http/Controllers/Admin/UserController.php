<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
        $users = User::query()
        ->whereDoesntHave('roles')
        ->when(request('q'), function($query, $q){
            $query->where('name', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%");
        })
        ->orderBy('name')
        ->paginate();

        $context = compact('users'); 

        return view('users.index', $context);
    }

  

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load('zones');
        $states = State::orderBy('name')->pluck('name', 'id');
        $cities = City::whereStateId($user->state_id)->orderBy('name')->pluck('name', 'id');
    
        $orders = $user->orders()
            ->with(['zone', 'products'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($order) {
                return [
                    'date' => $order->created_at->format('Y-m-d H:i:s'),
                    'address' => optional($order->zone)->address,
                    'order_number' => $order->id,
                    'value' => $order->products->sum(fn($p) => $p->price * $p->quantity),
                    'units' => $order->products->sum('quantity'),
                    'status' => $order->status_id == 0 ? 'Pendiente' : 'Completado',
                ];
            });
    
        return view('users.edit', compact('user', 'states', 'cities', 'orders'));
    }    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'state_id' => ['required', 'exists:' . State::class . ',id'],
            'city_id' => ['required', 'exists:' . City::class . ',id'],
            'document' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:255'],
            'has_whatsapp' => ['required', 'boolean'],
            'visit_by_tronex' => ['required', 'boolean'],
        ]);
        $user->update($validate);

        return to_route('users.index')->with('success', 'Usuario actualizado');
    }

 
    // public function code(Request $request, User $user)
    // {

    //     $validate = $request->validate([
    //         'code' => ['required', 'string', 'max:255'],
    //     ]);

    //     $code = $validate['code'];


    //     $response = UserRepository::getCustomRuteroId($code);
        
    //     if($response){

    //         $orders = $user->orders()->where('status_id', Order::STATUS_PENDING)->get();
            
    //         foreach($orders as $order){
    //             OrderRepository::presalesOrder($order);
    //         }


    //         $user->update($response);

    //         //pending order to processed
           

    //         return back()->with('success', 'Código actualizado, ya este cliente puede comprar');
    //     }

    //     return back()->with('error', 'Código no encontrado');

    // }


    public function password(Request $request, User $user)
    {

        $validate = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $validate['password'] = Hash::make($validate['password']);


        $user->update($validate);

        return back()->with('success', 'Contraseña actualizada');
    }

    public function export()
    {
        return Excel::download(new UsersExport, 'usuarios.xlsx');
    }
}
