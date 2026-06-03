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

    public function usesCoordinadoraFor48h(): bool
    {
        return ($this->fulfillment_provider_48h ?? self::FULFILLMENT_PROVIDER_COORDINADORA) === self::FULFILLMENT_PROVIDER_COORDINADORA;
    }

    public function user()
    {

        return $this->belongsTo(User::class);

    }
}
