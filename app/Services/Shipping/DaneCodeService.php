<?php

namespace App\Services\Shipping;

/**
 * Resolves Colombian DANE municipality codes in the 8-digit format that the
 * Coordinadora APIs expect ("ddmmm000": departamento + municipio + zona 000).
 *
 * The catalog source is storage/states.csv (official DANE divipola listing,
 * "departamento.municipio" decimal notation, e.g. "5.001" => Medellín 05001).
 */
class DaneCodeService
{
    /** @var array<string, string>|null map of "normalized city|normalized state" and "normalized city" to 8-digit code */
    private static ?array $catalog = null;

    /**
     * Normalize any admin/user provided DANE value to the 8-digit API format.
     * Accepts 5-digit divipola ("05001"), 8-digit ("05001000") and values with
     * separators ("05.001"). Returns null when the value is not a DANE code.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 4) { // leading zero dropped, e.g. "5001"
            $digits = '0' . $digits;
        }

        if (strlen($digits) === 5) {
            return $digits . '000';
        }

        if (strlen($digits) === 8) {
            return $digits;
        }

        return null;
    }

    /**
     * Look up the DANE code for a city name, optionally disambiguated by
     * department/state name. Returns the 8-digit Coordinadora format or null.
     */
    public static function forCity(?string $city, ?string $state = null): ?string
    {
        $city = self::normalizeName($city);
        if ($city === '') {
            return null;
        }

        $catalog = self::catalog();

        $state = self::normalizeName($state);
        if ($state !== '' && isset($catalog[$city . '|' . $state])) {
            return $catalog[$city . '|' . $state];
        }

        return $catalog[$city] ?? null;
    }

    /** @return array<string, string> */
    private static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $catalog = [];
        $path = storage_path('states.csv');

        if (is_readable($path) && ($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                // Columns: region, dept code, dept name, "dept.muni" code, city name
                $code = self::fromDivipolaDecimal($row[3] ?? '');
                $state = self::normalizeName($row[2] ?? '');
                $city = self::normalizeName($row[4] ?? '');

                if ($code === null || $city === '') {
                    continue;
                }

                if ($state !== '') {
                    $catalog[$city . '|' . $state] = $code;
                }

                // Plain city key: first occurrence wins so capital cities
                // (listed first per department) take precedence on collisions.
                if (!isset($catalog[$city])) {
                    $catalog[$city] = $code;
                }
            }

            fclose($handle);
        }

        return self::$catalog = $catalog;
    }

    /**
     * Convert the CSV "departamento.municipio" decimal notation to the 8-digit
     * code. The municipality part is a decimal fraction, so it must be
     * right-padded to 3 digits ("5.03" => 05 + 030 => "05030000").
     */
    private static function fromDivipolaDecimal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^(\d{1,2})\.(\d{1,3})$/', $value, $m)) {
            return null;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT)
            . str_pad($m[2], 3, '0', STR_PAD_RIGHT)
            . '000';
    }

    private static function normalizeName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($value === '') {
            return '';
        }

        // Strip accents (the CSV uses combining diacritics) so lookups match
        // regardless of how the city name was typed.
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value;
            }
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
