<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Contact;
use App\Models\ZoneRoute;
use App\Services\NewClientService;
use Illuminate\Http\Request;
use App\Exports\ContactsExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->with('city')
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
        $contact->load('city');

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
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Contact::STATUSES)),
        ]);

        $contact->update($validated);

        return back()->with('success', 'Estado actualizado a: ' . Contact::STATUSES[$validated['status']]);
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
}
