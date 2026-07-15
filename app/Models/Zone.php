<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Zone extends Model
{
    use HasFactory;

    /**
     * Memoized presence of the `sucursal_uid` column. Guards against the deploy window where
     * application code may briefly run before the migration has added the column: in that case
     * the creating hook simply skips setting the identity instead of throwing on every insert.
     */
    private static ?bool $sucursalUidColumnExists = null;

    protected $fillable = [
        'route',
        'zone',
        'day',
        'address',
        'code',
        'sucursal_uid',
        'tax_group',
        'zip_code',
        'dane_code',
        'fulfillment_provider_48h',
        'user_id',
    ];

    public const FULFILLMENT_PROVIDER_COORDINADORA = 'coordinadora';
    public const FULFILLMENT_PROVIDER_TRONEX = 'tronex';

    /**
     * Freeze a stable sucursal identity at row creation. `sucursal_uid` is intentionally
     * never recomputed on update so rutero sync can refresh mutable logistics attributes
     * (zone/route/day/address) without changing which sucursal a row — and therefore any
     * order pointing at it — represents.
     */
    protected static function booted(): void
    {
        static::creating(function (self $zone) {
            if (! self::supportsSucursalUid()) {
                return;
            }
            if ($zone->sucursal_uid === null || $zone->sucursal_uid === '') {
                $zone->sucursal_uid = self::makeSucursalUid($zone->code, $zone->address);
            }
        });
    }

    private static function supportsSucursalUid(): bool
    {
        if (self::$sucursalUidColumnExists === true) {
            return true;
        }

        try {
            self::$sucursalUidColumnExists = Schema::hasColumn('zones', 'sucursal_uid');
        } catch (\Throwable $e) {
            return false;
        }

        return self::$sucursalUidColumnExists;
    }

    /**
     * Stable identity for a sucursal: the Dynamics CustRuteroID when present, otherwise a
     * deterministic hash of the (normalized) physical address. The logistics zona number is
     * deliberately excluded — it is a mutable attribute of the sucursal, not its identity.
     */
    public static function makeSucursalUid(?string $code, ?string $address): string
    {
        $code = is_string($code) ? trim($code) : '';
        if ($code !== '') {
            return 'cust:'.$code;
        }

        return 'addr:'.sha1(self::normalizeSucursalAddress($address));
    }

    public static function normalizeSucursalAddress(?string $address): string
    {
        if (! is_string($address)) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $address) ?? $address), 'UTF-8');
    }

    /**
     * Resolve the Carbon dayOfWeek (0=domingo..6=sábado) encoded in a rutero `day`
     * value. Depending on the source the field may be "5", "5-Viernes" or "Viernes".
     */
    public static function carbonDayOfWeekFromDay(?string $day): ?int
    {
        $raw = trim((string) $day);
        if ($raw === '') {
            return null;
        }

        $names = [
            'domingo' => 0,
            'lunes' => 1,
            'martes' => 2,
            'miercoles' => 3,
            'miércoles' => 3,
            'jueves' => 4,
            'viernes' => 5,
            'sabado' => 6,
            'sábado' => 6,
        ];

        // Prefer the weekday name when present ("5-Viernes" or "Viernes").
        $namePart = str_contains($raw, '-') ? trim(explode('-', $raw, 2)[1] ?? '') : $raw;
        $nameKey = mb_strtolower($namePart, 'UTF-8');
        if (isset($names[$nameKey])) {
            return $names[$nameKey];
        }

        if (preg_match('/^\d+/', $raw, $matches)) {
            $num = (int) $matches[0];
            if ($num === 7) {
                return 0; // 1=lunes..7=domingo convention
            }
            if ($num >= 0 && $num <= 6) {
                return $num;
            }
        }

        return null;
    }

    /**
     * Spanish weekday label for a rutero `day` value, or null when unparsable.
     */
    public static function weekdayLabelFromDay(?string $day): ?string
    {
        $dow = self::carbonDayOfWeekFromDay($day);
        if ($dow === null) {
            return null;
        }

        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][$dow] ?? null;
    }

    public function usesCoordinadoraFor48h(): bool
    {
        return ($this->fulfillment_provider_48h ?? self::FULFILLMENT_PROVIDER_COORDINADORA) === self::FULFILLMENT_PROVIDER_COORDINADORA;
    }

    /**
     * Destination DANE code (8-digit Coordinadora format "ddmmm000") for this zone.
     *
     * Resolution order: explicit zones.dane_code, a DANE-looking value stored in the
     * legacy zip_code field, the owning user's numeric city_code from Dynamics, and
     * finally a catalog lookup by the user's city/state names.
     */
    public function coordinadoraDaneCode(): ?string
    {
        $explicit = \App\Services\Shipping\DaneCodeService::normalize($this->dane_code);
        if ($explicit !== null) {
            return $explicit;
        }

        $fromZip = \App\Services\Shipping\DaneCodeService::normalize($this->zip_code);
        if ($fromZip !== null) {
            return $fromZip;
        }

        $user = $this->user;
        if (!$user) {
            return null;
        }

        $fromUserCode = \App\Services\Shipping\DaneCodeService::normalize($user->city_code);
        if ($fromUserCode !== null) {
            return $fromUserCode;
        }

        $city = $user->city;

        return \App\Services\Shipping\DaneCodeService::forCity(
            $city?->name ?? $user->city_code,
            $city?->state?->name
        );
    }

    public function user()
    {

        return $this->belongsTo(User::class);

    }
}
