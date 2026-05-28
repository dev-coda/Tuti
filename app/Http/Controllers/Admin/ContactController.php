<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Contact;
use App\Models\User;
use App\Models\ZoneRoute;
use App\Services\NewClientService;
use Illuminate\Http\Request;
use App\Exports\ContactsExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->with(['city', 'clientUser.zones'])
            ->when($request->date_from, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate();

        return view('contacts.index', compact('contacts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource (detail view with all data and uploads).
     */
    public function show(Contact $contact)
    {
        $contact->load(['city', 'clientUser.zones']);

        $zoneOptions = ZoneRoute::query()
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->values();

        $routesByZone = ZoneRoute::query()
            ->orderBy('zone')
            ->orderBy('route')
            ->get(['zone', 'route'])
            ->groupBy('zone')
            ->map(fn ($items) => $items->pluck('route')->values())
            ->toArray();

        return view('contacts.show', compact('contact', 'zoneOptions', 'routesByZone'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        if ($request->boolean('quick_status_update')) {
            $validated = $request->validate([
                'status' => 'required|in:' . implode(',', array_keys(Contact::STATUSES)),
            ]);

            $contact->update($validated);

            return back()->with('success', 'Estado actualizado a: ' . Contact::STATUSES[$validated['status']]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Contact::STATUSES))],
            'client_status' => ['required', Rule::in([
                User::CLIENT_STATUS_PROSPECTO,
                User::CLIENT_STATUS_PENDIENTE,
                User::CLIENT_STATUS_CLIENTE,
                User::CLIENT_STATUS_RECHAZADO,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'Zona' => ['nullable', 'string', 'max:3'],
            'RutaZonaVentas' => ['nullable', 'regex:/^\d{4}$/'],
            'DiaRecorrido' => ['nullable', Rule::in(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'])],
            'Posicion' => ['nullable', 'integer', 'min:1'],
            'verification_documents' => ['nullable', 'array'],
            'verification_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $payload = array_merge((array) $contact->new_client_payload, [
            'Zona' => $validated['Zona'] ? strtoupper((string) $validated['Zona']) : null,
            'RutaZonaVentas' => $validated['RutaZonaVentas'] ?? null,
            'DiaRecorrido' => $validated['DiaRecorrido'] ?? null,
            'Posicion' => $validated['Posicion'] ?? null,
            'NombreNegocio' => $validated['business_name'],
            'Direccion' => $validated['address'],
            'Departamento' => $validated['department'] ?? null,
            'Ciudad' => $validated['city'] ?? null,
            'Documento' => $validated['nit'],
        ]);

        $storedDocs = is_array($contact->documents) ? $contact->documents : [];
        foreach (($request->file('verification_documents') ?? []) as $document) {
            $storedDocs[] = $document->store('contact-documents/new-client-documents', 'public');
        }

        $contact->update([
            'status' => $validated['status'],
            'name' => $validated['name'],
            'business_name' => $validated['business_name'],
            'nit' => $validated['nit'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'department' => $validated['department'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'documents' => $storedDocs,
            'new_client_payload' => $payload,
        ]);

        $linkedClient = $this->resolveOrCreateLinkedClient($contact);
        if ($linkedClient) {
            $linkedClient->update([
                'name' => $validated['name'],
                'business_name' => $validated['business_name'],
                'document' => $validated['nit'],
                'email' => $validated['email'] ?: $linkedClient->email,
                'phone' => $validated['phone'],
                'client_status' => $validated['client_status'],
                'status_id' => $validated['client_status'] === User::CLIENT_STATUS_CLIENTE ? User::ACTIVE : User::PENDING,
            ]);

            $this->syncClientZoneData($linkedClient, $payload);
        }

        return back()->with('success', 'Interesado actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        //
    }

    public function export(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        
        return Excel::download(
            new ContactsExport($dateFrom, $dateTo),
            'interesados_' . now()->format('Y-m-d_His') . '.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public function submitNewClient(Request $request, Contact $contact, NewClientService $service)
    {
        if ($contact->new_client_mode !== 'self_service') {
            return back()->with('error', 'Este contacto no pertenece al flujo de Cliente Nuevo autogestionado.');
        }

        $linkedClient = $contact->resolveLinkedClient();
        if (! $linkedClient || $linkedClient->client_status !== User::CLIENT_STATUS_PENDIENTE) {
            return back()->with('error', 'Solo clientes en estado Pendiente pueden ser transmitidos al webservice.');
        }

        $validated = $request->validate([
            'Zona' => ['required', 'string', 'max:3'],
            'RutaZonaVentas' => [
                'required',
                'regex:/^\d{4}$/',
                Rule::exists('zone_routes', 'route')->where(fn ($q) => $q->where('zone', $request->input('Zona'))),
            ],
            'DiaRecorrido' => ['required', Rule::in(['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'])],
            'Posicion' => ['required', 'integer', 'min:1'],
        ]);

        $payload = array_merge((array) $contact->new_client_payload, $validated);
        $requiredForApi = [
            'Documento', 'TipoDocumento', 'NombreNegocio', 'IdClasificacionCliente',
            'Departamento', 'Ciudad', 'Direccion', 'Barrio', 'Pep',
        ];
        foreach ($requiredForApi as $field) {
            if (empty($payload[$field])) {
                return back()->with('error', "Falta el campo requerido {$field} en el registro del interesado.");
            }
        }

        $result = $service->registerClient($payload);
        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        [$pdf, $images, $fileError] = $this->loadContactFilesForSubmission($contact);
        if ($fileError) {
            return back()->with('warning', "Cliente creado en API (Código: {$result['codigo_cliente']}), pero {$fileError}");
        }

        $mediaResult = $service->uploadMedia((int) $result['id'], $pdf, $images);
        if (! $mediaResult['success']) {
            return back()->with('warning', "Cliente creado en API (Código: {$result['codigo_cliente']}), pero falló la carga de archivos: {$mediaResult['message']}");
        }

        $contact->update([
            'status' => 'creado',
            'new_client_payload' => $payload,
            'external_client_id' => (int) ($result['id'] ?? 0),
            'external_client_code' => $result['codigo_cliente'] ?? null,
            'external_submitted_at' => now(),
        ]);

        return back()->with('success', "Cliente enviado correctamente a la API externa. Código: {$result['codigo_cliente']}");
    }

    /**
     * @return array{0:?UploadedFile,1:array<int, UploadedFile>,2:?string}
     */
    private function loadContactFilesForSubmission(Contact $contact): array
    {
        $docs = is_array($contact->documents) ? $contact->documents : [];
        $pdf = null;
        $fallbackPdf = null;
        $images = [];

        foreach ($docs as $path) {
            $absolutePath = Storage::disk('public')->path($path);
            if (!is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if ($extension === 'pdf') {
                $uploadedPdf = new UploadedFile($absolutePath, basename($absolutePath), 'application/pdf', null, true);
                if (str_starts_with($path, 'contact-documents/signatures/')) {
                    $pdf = $uploadedPdf;
                    continue;
                }

                if ($fallbackPdf === null) {
                    $fallbackPdf = $uploadedPdf;
                }

                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                if (count($images) < 3) {
                    $images[] = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);
                }
            }
        }

        if (!$pdf && $fallbackPdf) {
            $pdf = $fallbackPdf;
        }

        if (!$pdf) {
            return [null, [], 'no se encontró el PDF de firma en los documentos adjuntos'];
        }

        return [$pdf, $images, null];
    }

    private function resolveOrCreateLinkedClient(Contact $contact): ?User
    {
        $linked = $contact->resolveLinkedClient();
        if ($linked) {
            return $linked;
        }

        if (empty($contact->nit)) {
            return null;
        }

        $email = $contact->email;
        if (! $email || User::where('email', $email)->exists()) {
            $email = 'contacto_'.$contact->nit.'_'.Str::lower(Str::random(4)).'@tuti.com';
        }

        return User::create([
            'name' => $contact->name ?: 'Prospecto '.$contact->nit,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'document' => $contact->nit,
            'phone' => $contact->phone,
            'business_name' => $contact->business_name,
            'client_status' => User::CLIENT_STATUS_PROSPECTO,
            'status_id' => User::PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncClientZoneData(User $user, array $payload): void
    {
        $zone = strtoupper((string) ($payload['Zona'] ?? ''));
        $route = (string) ($payload['RutaZonaVentas'] ?? '');
        $day = (string) ($payload['DiaRecorrido'] ?? '');

        if ($zone === '') {
            return;
        }

        $address = trim((string) ($payload['Direccion'] ?? 'Dirección por asignar'));
        $zoneModel = $user->zones()->first();

        if ($zoneModel) {
            $zoneModel->update([
                'zone' => $zone,
                'route' => $route,
                'day' => $day,
                'address' => $address !== '' ? $address : $zoneModel->address,
            ]);
            return;
        }

        $user->zones()->create([
            'zone' => $zone,
            'route' => $route,
            'day' => $day,
            'address' => $address !== '' ? $address : 'Dirección por asignar',
            'code' => null,
        ]);
    }
}
