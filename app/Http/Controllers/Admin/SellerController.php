<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SellersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DraftOrderReconciliationService;
use App\Services\PendingClientProvisioningService;
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

            ->when(filled(request('zone')), function ($query) {
                $query->where('zone', request('zone'));
            })

            ->orderBy('name')
            ->paginate();

        $zones = User::whereRelation('roles', 'name', 'seller')->distinct()->pluck('zone')->sort();

        $context = compact('users', 'zones');

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
            'zone' => ['required', 'integer'],
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




        return to_route('sellers.index')->with('success', 'Vendedor actualizado');
    }


    public function setclient(Request $request)
    {
        $validate = $request->validate([
            'document' => ['required', 'string', 'regex:/^\d+$/'],
        ]);

        $document = preg_replace('/\D+/', '', $validate['document']) ?: $validate['document'];
        $sellerZone = auth()->user()?->zone;
        $requestZone = $request->input('zone');
        $resolvedZone = $sellerZone !== null && $sellerZone !== '' ? $sellerZone : $requestZone;
        $zone = $resolvedZone !== null && $resolvedZone !== '' ? (string) $resolvedZone : null;
        $reconciliation = app(DraftOrderReconciliationService::class);
        $user = User::whereDocument($document)->first();
        if (!$user) {
            // Try with zone first, if fails will retry without zone automatically
            $data = UserRepository::getCustomRuteroId($document, $zone);

            if ($data) {
                $user = User::create([
                    'name' => $data['name'] ?? ('Cliente '.$document),
                    'email' => time().'@tuti.com',
                    'document' => $document,
                    'password' => bcrypt(time().'@tuti.com'),
                    'status_id' => User::ACTIVE,
                    'client_status' => User::CLIENT_STATUS_CLIENTE,
                ]);

                UserRepository::syncUserRuteroData($user);
                $user = $user->fresh(['zones']);

                session()->put('user_id', $user->id);
                session()->forget('zone_id');

                return to_route('cart');
            } else {
                $provisioning = app(PendingClientProvisioningService::class);
                $user = $provisioning->provisionProspectByDocument(
                    $document,
                    $zone !== null ? (string) $zone : null
                );

                session()->put('user_id', $user->id);
                session()->forget('zone_id');

                return to_route('cart')->with(
                    'success',
                    'Cliente prospecto vinculado. Puede realizar pedidos; la transmisión al sistema se hará cuando el rutero esté disponible.'
                );
            }
        } else {
            $reconciliation->syncUserFromRutero(
                $user,
                promoteIfPossible: ! $user->isCliente(),
                transmitDrafts: true,
            );
            $user = $user->fresh(['zones']);

            session()->put('user_id', $user->id);
            session()->forget('zone_id');

            if ($user->isCliente()) {
                return to_route('cart');
            }

            return to_route('cart')->with(
                'success',
                'Cliente prospecto vinculado. Puede realizar pedidos; la transmisión al sistema se hará cuando el rutero esté disponible.'
            );
        }



    }

    public function removeclient()
    {
        session()->forget('user_id');
        session()->forget('zone_id');

        return to_route('cart')->with('success', 'Cliente desvinculado');
    }

    public function export()
    {
        return Excel::download(new SellersExport, 'vendedores.xlsx');
    }
}
