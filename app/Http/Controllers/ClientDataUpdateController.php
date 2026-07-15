<?php

namespace App\Http\Controllers;

use App\Models\ClientDataUpdateRequest;
use App\Models\User;
use App\Models\Zone;
use App\Repositories\UserRepository;
use App\Rules\ValidClientEmail;
use App\Services\MailingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientDataUpdateController extends Controller
{
    public function edit(Request $request, Zone $zone): View|RedirectResponse
    {
        $this->authorizeSellerZoneAccess($zone);

        $zone->load(['user.city']);

        return view('client-data-updates.edit', $this->formViewData($zone->user, $zone, [
            'returnTab' => (string) $request->query('return_tab', 'mi-ruta'),
            'returnRoute' => (string) $request->query('ruta', ''),
            'returnSearch' => (string) $request->query('ruta_q', ''),
        ]));
    }

    public function store(Request $request, Zone $zone): RedirectResponse
    {
        $this->authorizeSellerZoneAccess($zone);

        $zone->load(['user.city']);
        $client = $zone->user;

        if (!$client) {
            abort(404);
        }

        $validated = $this->validateUpdateRequest($request, requireValidEmail: false);
        $validated = $this->applyRuteroRouteData($client, $zone, $validated);

        $this->createUpdateRequest($zone, $client, $request->user(), $validated);

        $redirectParams = array_filter([
            'tab' => $validated['return_tab'] ?? 'mi-ruta',
            'ruta' => $validated['return_route'] ?? null,
            'ruta_q' => $validated['return_search'] ?? null,
        ]);

        return redirect()
            ->route('clients.orders.index', $redirectParams)
            ->with('success', 'La solicitud de actualización de datos fue enviada correctamente.');
    }

    public function clientEdit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->requiresClientEmailUpdate()) {
            return redirect()->route('home');
        }

        $user->load('city');
        $zone = $user->zones()->first();

        return view('client-data-updates.edit', $this->formViewData($user, $zone, [
            'isClientSelfService' => true,
        ]));
    }

    public function clientStore(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->requiresClientEmailUpdate()) {
            return redirect()->route('home');
        }

        $zone = $user->zones()->first();
        if ($zone && $zone->user_id !== $user->id) {
            abort(404);
        }

        $validated = $this->validateUpdateRequest($request, requireValidEmail: true);
        $validated = $this->applyRuteroRouteData($user, $zone, $validated);
        $this->createUpdateRequest($zone, $user, $user, $validated);

        return redirect()
            ->route('client-data-updates.client.edit')
            ->with('success', 'Tu solicitud de actualización fue enviada. Te contactaremos cuando tus datos estén listos.');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formViewData(User $client, ?Zone $zone = null, array $extra = []): array
    {
        $ruteroRoute = $this->resolveRuteroRoute($client, $zone);

        return array_merge([
            'zone' => $zone,
            'client' => $client,
            'ruteroRoute' => $ruteroRoute,
            'ruteroUnavailable' => $ruteroRoute === null && $this->documentForRutero($client) !== '',
            'returnTab' => 'mi-ruta',
            'returnRoute' => '',
            'returnSearch' => '',
            'isClientSelfService' => false,
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdateRequest(Request $request, bool $requireValidEmail): array
    {
        $emailRules = $requireValidEmail
            ? ['required', 'email', 'max:255', new ValidClientEmail()]
            : ['nullable', 'email', 'max:255', new ValidClientEmail()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'string', 'max:20'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'seller_notes' => ['nullable', 'string', 'max:2000'],
            'return_tab' => ['nullable', 'string', 'max:50'],
            'return_route' => ['nullable', 'string', 'max:100'],
            'return_search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createUpdateRequest(
        ?Zone $zone,
        User $client,
        User $submittedBy,
        array $validated
    ): ClientDataUpdateRequest {
        $updateRequest = ClientDataUpdateRequest::create([
            'user_id' => $client->id,
            'zone_id' => $zone?->id,
            'submitted_by' => $submittedBy->id,
            'document' => preg_replace('/\D+/', '', $validated['document']),
            'name' => $validated['name'],
            'business_name' => $validated['business_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile_phone' => $validated['mobile_phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'address' => $validated['address'],
            'city_name' => $validated['city_name'] ?? null,
            'zone_code' => $validated['zone_code'] ?? null,
            'route' => $validated['route'] ?? null,
            'day' => $validated['day'] ?? null,
            'seller_notes' => $validated['seller_notes'] ?? null,
            'previous_data' => [
                'name' => $client->name,
                'business_name' => $client->business_name,
                'document' => $client->document,
                'email' => $client->clientDisplayEmail(),
                'phone' => $client->phone,
                'mobile_phone' => $client->mobile_phone,
                'whatsapp' => $client->whatsapp,
                'address' => $zone?->address,
                'city_name' => $client->city?->name,
                'zone_code' => $zone?->zone,
                'route' => $zone?->route,
                'day' => $zone?->day,
            ],
        ]);

        app(MailingService::class)->sendClientDataUpdateNotification($updateRequest);

        return $updateRequest;
    }

    /**
     * @return array{zone_code: ?string, route: ?string, day: ?string, address: ?string}|null
     */
    private function resolveRuteroRoute(User $client, ?Zone $zone = null): ?array
    {
        $document = $this->documentForRutero($client);
        if ($document === '') {
            return null;
        }

        $zoneFilter = $zone?->zone ?: null;
        $rutero = UserRepository::getCustomRuteroId($document, $zoneFilter);
        if (!$rutero || empty($rutero['routes'])) {
            return null;
        }

        $routes = collect($rutero['routes']);

        if ($zone) {
            $matched = $routes->first(function (array $route) use ($zone) {
                if ($zone->zone && trim((string) ($route['zone'] ?? '')) !== trim((string) $zone->zone)) {
                    return false;
                }

                if ($zone->route && trim((string) ($route['route'] ?? '')) !== trim((string) $zone->route)) {
                    return false;
                }

                $routeCode = trim((string) ($route['code'] ?? ''));
                if ($zone->code && $routeCode !== '' && $routeCode !== trim((string) $zone->code)) {
                    return false;
                }

                return true;
            });

            if ($matched) {
                return $this->normalizeRuteroRouteRow($matched);
            }
        }

        $first = $routes->first();

        return is_array($first) ? $this->normalizeRuteroRouteRow($first) : null;
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array{zone_code: ?string, route: ?string, day: ?string, address: ?string}
     */
    private function normalizeRuteroRouteRow(array $route): array
    {
        return [
            'zone_code' => isset($route['zone']) ? trim((string) $route['zone']) : null,
            'route' => isset($route['route']) ? trim((string) $route['route']) : null,
            'day' => isset($route['day']) ? trim((string) $route['day']) : null,
            'address' => isset($route['address']) ? trim((string) $route['address']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyRuteroRouteData(User $client, ?Zone $zone, array $validated): array
    {
        $ruteroRoute = $this->resolveRuteroRoute($client, $zone);
        if (!$ruteroRoute) {
            throw ValidationException::withMessages([
                'document' => 'No pudimos consultar la zona y la ruta en Tronex. Intenta más tarde o contáctanos.',
            ]);
        }

        $validated['zone_code'] = $ruteroRoute['zone_code'];
        $validated['route'] = $ruteroRoute['route'];
        $validated['day'] = $ruteroRoute['day'];

        return $validated;
    }

    private function documentForRutero(User $client): string
    {
        return preg_replace('/\D+/', '', (string) $client->document);
    }

    private function authorizeSellerZoneAccess(Zone $zone): void
    {
        $seller = auth()->user();

        if (!$seller || !$seller->hasAnyRole(['seller', 'supervisor', 'admin'])) {
            abort(403);
        }

        if (!$zone->user_id) {
            abort(404);
        }

        $sellerZone = trim((string) $seller->zone);
        if ($sellerZone !== '' && trim((string) $zone->zone) !== $sellerZone) {
            abort(403);
        }
    }
}
