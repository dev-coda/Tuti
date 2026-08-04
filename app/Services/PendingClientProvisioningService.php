<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PendingClientProvisioningService
{
    /**
     * Create or update a local pending client after ClienteNuevo registration (seller flow).
     *
     * @param  array<string, mixed>  $validated
     * @param  bool  $preserveExistingStatus  Keep the current status of an already active client
     *                                        (sucursal additions must not demote a "cliente" to pending).
     */
    public function provisionFromNewClient(
        array $validated,
        ?string $externalClientCode = null,
        string $clientStatus = User::CLIENT_STATUS_PENDIENTE,
        bool $preserveExistingStatus = false
    ): User
    {
        $document = $this->normalizeDocument((string) ($validated['Documento'] ?? ''));
        $businessName = trim((string) ($validated['NombreNegocio'] ?? ''));
        $legalName = trim((string) ($validated['RazonSocial'] ?? ''));
        $displayName = $legalName !== '' ? $legalName : ($businessName !== '' ? $businessName : 'Cliente '.$document);

        $user = User::query()->where('document', $document)->first();
        $email = $this->resolveRegistrationEmail($validated['Correo'] ?? null, $document, $user?->id);

        if ($user) {
            $payload = [
                'name' => $displayName,
                'business_name' => $businessName ?: $user->business_name,
                'mobile_phone' => $validated['Movil'] ?? $user->mobile_phone,
                'whatsapp' => $validated['Whatsapp'] ?? $user->whatsapp,
                'phone' => $validated['Telefono'] ?? $user->phone,
            ];

            if ($this->shouldReplaceUserEmail($user, $email)) {
                $payload['email'] = $email;
            }

            if (! ($preserveExistingStatus && $user->isCliente())) {
                $payload['client_status'] = $clientStatus;
                $payload['status_id'] = User::PENDING;
            }

            $user->update($payload);
        } else {
            $user = User::create([
                'name' => $displayName,
                'email' => $email,
                'document' => $document,
                'password' => bcrypt(Str::random(32)),
                'status_id' => User::PENDING,
                'client_status' => $clientStatus,
                'business_name' => $businessName ?: null,
                'mobile_phone' => $validated['Movil'] ?? null,
                'whatsapp' => $validated['Whatsapp'] ?? null,
                'phone' => $validated['Telefono'] ?? null,
            ]);
        }

        if ($clientStatus === User::CLIENT_STATUS_PROSPECTO) {
            $this->ensureProspectPlaceholderZone($user, $validated);
        } else {
            $this->syncPlaceholderZone($user, $validated);
        }

        return $user->fresh(['zones']);
    }

    /**
     * Create a pending prospect client when rutero is not yet available (seller cart lookup).
     * No email is known yet, so a temporary internal address is used until registration.
     */
    public function provisionProspectByDocument(string|int $document, ?string $zoneNumber = null, ?string $displayName = null): User
    {
        $normalizedDocument = $this->normalizeDocument((string) $document);
        $user = User::query()->where('document', $normalizedDocument)->first();

        if ($user) {
            if ($user->client_status !== User::CLIENT_STATUS_CLIENTE) {
                $user->update(['client_status' => User::CLIENT_STATUS_PENDIENTE]);
            }

            if ($zoneNumber && $user->zones()->count() === 0) {
                $this->createPlaceholderZone($user, [
                    'Zona' => $zoneNumber,
                    'RutaZonaVentas' => '',
                    'DiaRecorrido' => '',
                    'Direccion' => 'Pendiente de sincronización rutero',
                    'Barrio' => '',
                ]);
            }

            return $user->fresh(['zones']);
        }

        $email = $this->makePlaceholderEmail($normalizedDocument);
        $name = $displayName ?: ('Prospecto '.$normalizedDocument);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'document' => $normalizedDocument,
            'password' => bcrypt(Str::random(32)),
            'status_id' => User::PENDING,
            'client_status' => User::CLIENT_STATUS_PENDIENTE,
        ]);

        if ($zoneNumber) {
            $this->createPlaceholderZone($user, [
                'Zona' => $zoneNumber,
                'RutaZonaVentas' => '',
                'DiaRecorrido' => '',
                'Direccion' => 'Pendiente de sincronización rutero',
                'Barrio' => '',
            ]);
        }

        return $user->fresh(['zones']);
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D+/', '', $document) ?: $document;
    }

    /**
     * Resolve the real registration email. Never invents @tuti.com placeholders here —
     * those are only for document-only prospect stubs.
     */
    private function resolveRegistrationEmail(?string $preferredEmail, string $document, ?int $ignoreUserId = null): string
    {
        $email = is_string($preferredEmail) ? trim($preferredEmail) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || User::isInvalidClientEmail($email)) {
            throw new InvalidArgumentException(
                "Correo electrónico válido requerido para aprovisionar el cliente {$document}."
            );
        }

        $taken = User::query()
            ->whereEmailCaseInsensitive($email)
            ->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException(
                "El correo {$email} ya está registrado en otra cuenta."
            );
        }

        return $email;
    }

    private function shouldReplaceUserEmail(User $user, string $newEmail): bool
    {
        if (strcasecmp((string) $user->email, $newEmail) === 0) {
            return false;
        }

        // Always replace internal / placeholder addresses with the real registration email.
        if (User::isInvalidClientEmail($user->email)) {
            return true;
        }

        // Also allow an explicit correction when the submitted email differs.
        return true;
    }

    private function makePlaceholderEmail(string $document): string
    {
        do {
            $candidate = 'cliente_'.$document.'_'.time().'_'.Str::lower(Str::random(4)).'@tuti.com';
        } while (User::query()->whereEmailCaseInsensitive($candidate)->exists());

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncPlaceholderZone(User $user, array $validated): void
    {
        $zoneNumber = isset($validated['Zona']) ? strtoupper(trim((string) $validated['Zona'])) : '';
        if ($zoneNumber === '') {
            return;
        }

        $route = (string) ($validated['RutaZonaVentas'] ?? '');
        $day = (string) ($validated['DiaRecorrido'] ?? '');
        $address = trim(((string) ($validated['Direccion'] ?? '')).' '.((string) ($validated['Barrio'] ?? '')));

        $existing = $user->zones()
            ->where('zone', $zoneNumber)
            ->where('route', $route)
            ->first();

        if ($existing) {
            $existing->update([
                'day' => $day ?: $existing->day,
                'address' => $address !== '' ? $address : $existing->address,
            ]);

            return;
        }

        $this->createPlaceholderZone($user, $validated);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createPlaceholderZone(User $user, array $payload): void
    {
        $user->zones()->create([
            'zone' => strtoupper(trim((string) ($payload['Zona'] ?? ''))),
            'route' => (string) ($payload['RutaZonaVentas'] ?? ''),
            'day' => (string) ($payload['DiaRecorrido'] ?? ''),
            'address' => trim((string) ($payload['Direccion'] ?? 'Pendiente de sincronización rutero')),
            'code' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureProspectPlaceholderZone(User $user, array $payload): void
    {
        if ($user->zones()->exists()) {
            return;
        }

        $address = trim(((string) ($payload['Direccion'] ?? '')).' '.((string) ($payload['Barrio'] ?? '')));

        $user->zones()->create([
            'zone' => '000',
            'route' => '0000',
            'day' => '',
            'address' => $address !== '' ? $address : 'Dirección prospecto pendiente de validación',
            'code' => null,
        ]);
    }
}
