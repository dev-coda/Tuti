<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ExportFile;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Models\Setting;
use App\Repositories\UserRepository;
use App\Services\DraftOrderReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exports\UsersExport;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::query()
            ->whereDoesntHave('roles')
            ->when(request('q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%")
                        ->orWhere('document', 'ilike', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate();

        $lastRuteroBulkAt = Setting::getByKey('last_client_rutero_bulk_sync_at');
        $lastRuteroBulkSession = Setting::getByKey('last_client_rutero_bulk_sync_session');
        $lastRuteroReportFilename = Setting::getByKey('last_client_rutero_bulk_sync_report');
        $dailyRuteroSyncEnabled = Setting::getByKeyWithDefault('daily_client_rutero_sync_enabled', '1');

        $lastRuteroBulkAtFormatted = null;
        if ($lastRuteroBulkAt) {
            try {
                $lastRuteroBulkAtFormatted = Carbon::parse($lastRuteroBulkAt)
                    ->timezone(config('app.timezone'))
                    ->format('d/m/Y H:i');
            } catch (\Throwable) {
                $lastRuteroBulkAtFormatted = (string) $lastRuteroBulkAt;
            }
        }

        $lastRuteroReportExists = $lastRuteroReportFilename
            && Storage::disk('local')->exists('reports/' . $lastRuteroReportFilename);

        return view('users.index', compact(
            'users',
            'lastRuteroBulkAt',
            'lastRuteroBulkAtFormatted',
            'lastRuteroBulkSession',
            'lastRuteroReportFilename',
            'lastRuteroReportExists',
            'dailyRuteroSyncEnabled'
        ));
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

        $context = compact('user', 'states', 'cities', 'orders');

        return view('users.edit', $context);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validate = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class . ',email,' . $user->id],
            'document' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
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

    public function syncRuteroByDocument(Request $request, DraftOrderReconciliationService $reconciliationService)
    {
        $validated = $request->validate([
            'document' => ['required', 'string', 'max:255'],
        ]);

        $result = $reconciliationService->syncByDocument($validated['document']);

        if ($result['user']) {
            return redirect()
                ->route('users.edit', $result['user'])
                ->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function syncRutero(User $user, DraftOrderReconciliationService $reconciliationService)
    {
        if ($user->roles()->exists()) {
            return back()->with('error', 'Solo aplica a clientes, no a usuarios administrativos o vendedores.');
        }

        $result = $reconciliationService->syncUserFromRutero($user, true, true);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Queue an async export of all clients.
     *
     * The full clients list is too large to export synchronously (it times out
     * and exhausts memory in production), so the file is generated in the
     * background and made available via "Mis Exportaciones".
     */
    public function export()
    {
        $timestamp = time();
        $filename = "clientes_{$timestamp}.xlsx";

        $exportFile = ExportFile::create([
            'user_id' => auth()->id(),
            'type' => 'clients',
            'filename' => $filename,
            'file_path' => "exports/clients/{$filename}",
            'status' => ExportFile::STATUS_PENDING,
            'params' => [
                'label' => 'Clientes ' . now()->format('d/m/Y H:i'),
            ],
        ]);

        try {
            (new UsersExport())
                ->queue($exportFile->file_path, 'local')
                ->chain([
                    function () use ($exportFile) {
                        $totalRecords = User::query()->whereDoesntHave('roles')->count();
                        $exportFile->markAsCompleted($totalRecords);
                    },
                ]);

            $exportFile->markAsProcessing();

            return back()->with('success', 'La exportación de clientes se está generando en segundo plano. Aparecerá en "Mis Exportaciones" cuando esté lista.');
        } catch (\Throwable $e) {
            $exportFile->markAsFailed($e->getMessage());

            return back()->with('error', 'No se pudo iniciar la exportación: ' . $e->getMessage());
        }
    }

    /**
     * List the current user's client exports (JSON, used by the exports modal).
     */
    public function getExports()
    {
        $exports = ExportFile::forUser(auth()->id())
            ->where('type', 'clients')
            ->recent(90)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($export) {
                return [
                    'id' => $export->id,
                    'filename' => $export->filename,
                    'status' => $export->status,
                    'label' => $export->params['label'] ?? $export->filename,
                    'total_records' => $export->total_records,
                    'file_size' => $export->file_size,
                    'created_at' => $export->created_at->format('Y-m-d H:i'),
                    'completed_at' => $export->completed_at?->format('Y-m-d H:i'),
                    'download_url' => $export->download_url,
                    'is_completed' => $export->isCompleted(),
                    'is_processing' => $export->isProcessing(),
                    'has_failed' => $export->hasFailed(),
                    'error_message' => $export->error_message,
                ];
            });

        return response()->json($exports);
    }

    public function updateZone48h(Request $request, User $user, Zone $zone)
    {
        if ((int) $zone->user_id !== (int) $user->id) {
            return back()->with('error', 'La zona no pertenece al usuario seleccionado.');
        }

        $validated = $request->validate([
            'zip_code' => ['nullable', 'string', 'max:50'],
            'dane_code' => ['nullable', 'string', 'max:12', 'regex:/^\d{4,8}$/'],
            'fulfillment_provider_48h' => ['required', 'in:coordinadora,tronex'],
        ]);

        if (array_key_exists('dane_code', $validated) && filled($validated['dane_code'])) {
            $normalized = \App\Services\Shipping\DaneCodeService::normalize($validated['dane_code']);
            if ($normalized === null) {
                return back()->with('error', 'El código DANE no es válido (use 5 u 8 dígitos, ej. 11001 o 11001000).');
            }
            $validated['dane_code'] = $normalized;
        }

        $zone->update($validated);

        return back()->with('success', 'Zona 48H actualizada correctamente.');
    }
}
