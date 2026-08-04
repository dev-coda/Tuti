<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves registration attachments stored on Contact.documents for a client User.
 */
class ClientRegistrationDocuments
{
    /**
     * @return array<int, string> Relative paths on the public disk
     */
    public static function pathsForUser(User $user): array
    {
        $document = trim((string) $user->document);
        if ($document === '') {
            return [];
        }

        return Contact::query()
            ->where('nit', $document)
            ->get(['documents'])
            ->flatMap(function (Contact $contact) {
                return is_array($contact->documents) ? $contact->documents : [];
            })
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->unique()
            ->filter(fn ($path) => Storage::disk('public')->exists($path))
            ->values()
            ->all();
    }

    /**
     * Absolute filesystem paths keyed by basename (unique).
     *
     * @return array<string, string> basename => absolute path
     */
    public static function absoluteFilesForUser(User $user): array
    {
        $files = [];
        foreach (self::pathsForUser($user) as $path) {
            $absolute = Storage::disk('public')->path($path);
            if (! is_file($absolute)) {
                continue;
            }
            $base = basename($path);
            $name = $base;
            $i = 1;
            while (isset($files[$name])) {
                $name = pathinfo($base, PATHINFO_FILENAME) . "_{$i}." . pathinfo($base, PATHINFO_EXTENSION);
                $i++;
            }
            $files[$name] = $absolute;
        }

        return $files;
    }

    /**
     * Human-readable client summary for zip packages.
     */
    public static function summaryText(User $user): string
    {
        $user->loadMissing('zones');

        $lines = [
            'Resumen del cliente Tuti',
            'Generado: ' . now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            str_repeat('-', 40),
            'ID: ' . $user->id,
            'Nombre: ' . ($user->name ?? ''),
            'Documento: ' . ($user->document ?? ''),
            'Razón social: ' . ($user->business_name ?? ''),
            'Email: ' . ($user->email ?? ''),
            'Teléfono: ' . ($user->phone ?? ''),
            'Móvil: ' . ($user->mobile_phone ?? ''),
            'WhatsApp: ' . ($user->whatsapp ?? ''),
            'Estado cliente: ' . ($user->client_status ?? ''),
            'Customer status: ' . ($user->customer_status ?? ''),
            'Account num: ' . ($user->account_num ?? ''),
            'City code: ' . ($user->city_code ?? ''),
            'County: ' . ($user->county_id ?? ''),
            'Tax group: ' . ($user->tax_group ?? ''),
            'Price group: ' . ($user->price_group ?? ''),
            'Balance: ' . ($user->balance ?? ''),
            'Rutero synced at: ' . optional($user->rutero_synced_at)->format('Y-m-d H:i:s'),
            '',
            'Zonas / sucursales:',
        ];

        if ($user->zones->isEmpty()) {
            $lines[] = '  (ninguna)';
        } else {
            foreach ($user->zones as $zone) {
                $lines[] = sprintf(
                    '  - id=%s code=%s zone=%s route=%s day=%s address=%s',
                    $zone->id,
                    $zone->code,
                    $zone->zone,
                    $zone->route,
                    $zone->day,
                    $zone->address
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Documentos incluidos:';
        $paths = self::pathsForUser($user);
        if ($paths === []) {
            $lines[] = '  (ninguno)';
        } else {
            foreach ($paths as $path) {
                $lines[] = '  - ' . $path;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
