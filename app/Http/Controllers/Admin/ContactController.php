<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Contact;
use App\Models\User;
use App\Models\ZoneRoute;
use App\Services\DraftOrderReconciliationService;
use App\Services\NewClientService;
use Illuminate\Http\Request;
use App\Exports\ContactsExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
        $zoneFilter = trim((string) $request->input('zone', ''));

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
            ->when($zoneFilter !== '', function ($query) use ($zoneFilter) {
                $query->where(function ($sub) use ($zoneFilter) {
                    $sub->where('new_client_payload->Zona', $zoneFilter)
                        ->orWhereHas('clientUser.zones', function ($zones) use ($zoneFilter) {
                            $zones->where('zone', $zoneFilter);
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate()
            ->withQueryString();

        $zoneOptions = ZoneRoute::query()
            ->distinct()
            ->orderBy('zone')
            ->pluck('zone')
            ->values();

        $layout = $this->resolveLayout();

        return view('contacts.index', compact('contacts', 'zoneOptions', 'layout', 'zoneFilter'));
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

        $layout = $this->resolveLayout();

        return view('contacts.show', compact('contact', 'zoneOptions', 'routesByZone', 'layout'));
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

        try {
            $linkedClient = $this->resolveOrCreateLinkedClient($contact);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($linkedClient) {
            $emailToPersist = $validated['email'] ?: $linkedClient->email;
            if ($validated['email'] && User::isInvalidClientEmail($validated['email'])) {
                return back()->withErrors([
                    'email' => 'Debes ingresar un correo electrónico válido y personal (no se permiten correos @tuti).',
                ])->withInput();
            }

            $linkedClient->update([
                'name' => $validated['name'],
                'business_name' => $validated['business_name'],
                'document' => $validated['nit'],
                'email' => $emailToPersist,
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

        $this->syncClientZoneData($linkedClient, $payload);
        $this->attemptPostCreateRuteroSync($linkedClient->fresh(['zones']));

        return back()->with('success', "Cliente enviado correctamente a la API externa. Código: {$result['codigo_cliente']}");
    }

    /**
     * Best-effort rutero sync after admin completes ClienteNuevo transmission.
     * Dynamics may not have the rutero yet — failures are expected and non-blocking.
     */
    private function attemptPostCreateRuteroSync(User $user): void
    {
        try {
            $result = app(DraftOrderReconciliationService::class)->syncUserFromRutero(
                $user,
                promoteIfPossible: true,
                transmitDrafts: false,
            );

            Log::info('Contact NewClient: post-create rutero sync attempted', [
                'user_id' => $user->id,
                'document' => $user->document,
                'synced' => $result['synced'],
                'promoted' => $result['promoted'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Contact NewClient: post-create rutero sync failed', [
                'user_id' => $user->id,
                'document' => $user->document,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build Activity media payload: signature (or first PDF) as `pdf`, every other
     * registration file (images and PDFs) as `imagenes[]`.
     *
     * @return array{0:?UploadedFile,1:array<int, UploadedFile>,2:?string}
     */
    private function loadContactFilesForSubmission(Contact $contact): array
    {
        $docs = is_array($contact->documents) ? $contact->documents : [];
        $signaturePdf = null;
        $otherPdfs = [];
        $attachments = [];

        foreach ($docs as $path) {
            $absolutePath = Storage::disk('public')->path($path);
            if (! is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if ($extension === 'pdf') {
                $uploadedPdf = new UploadedFile($absolutePath, basename($absolutePath), 'application/pdf', null, true);
                if (str_starts_with($path, 'contact-documents/signatures/')) {
                    $signaturePdf = $uploadedPdf;
                    continue;
                }

                $otherPdfs[] = $uploadedPdf;
                continue;
            }

            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $attachments[] = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);
            }
        }

        $pdf = $signaturePdf;
        if (! $pdf && $otherPdfs !== []) {
            $pdf = array_shift($otherPdfs);
        }

        // Non-signature PDFs travel with images so Activity receives every attachment.
        $attachments = array_merge($attachments, $otherPdfs);

        if (! $pdf) {
            return [null, [], 'no se encontró el PDF de firma en los documentos adjuntos'];
        }

        return [$pdf, $attachments, null];
    }

    private function resolveOrCreateLinkedClient(Contact $contact): ?User
    {
        $linked = $contact->resolveLinkedClient();
        if ($linked) {
            $this->applyContactEmailToClient($linked, $contact);

            return $linked->fresh();
        }

        if (empty($contact->nit)) {
            return null;
        }

        $email = is_string($contact->email) ? trim($contact->email) : '';
        if ($email === '' || User::isInvalidClientEmail($email) || User::query()->whereEmailCaseInsensitive($email)->exists()) {
            // Prefer payload Correo when contact.email is missing/invalid/taken.
            $payloadEmail = trim((string) data_get($contact->new_client_payload, 'Correo', ''));
            if ($payloadEmail !== '' && ! User::isInvalidClientEmail($payloadEmail)
                && ! User::query()->whereEmailCaseInsensitive($payloadEmail)->exists()) {
                $email = $payloadEmail;
            } else {
                throw new \RuntimeException(
                    'El interesado no tiene un correo válido único; no se puede crear el cliente local.'
                );
            }
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

    private function applyContactEmailToClient(User $user, Contact $contact): void
    {
        $candidates = array_filter([
            is_string($contact->email) ? trim($contact->email) : '',
            trim((string) data_get($contact->new_client_payload, 'Correo', '')),
        ]);

        foreach ($candidates as $email) {
            if (User::isInvalidClientEmail($email)) {
                continue;
            }

            $taken = User::query()
                ->whereEmailCaseInsensitive($email)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($taken) {
                continue;
            }

            if (User::isInvalidClientEmail($user->email) || strcasecmp((string) $user->email, $email) !== 0) {
                $user->update(['email' => $email]);
            }

            return;
        }
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

    private function resolveLayout(): string
    {
        $user = auth()->user();
        if ($user && $user->hasRole('admin')) {
            return 'layouts.admin';
        }

        return 'layouts.page';
    }
}
