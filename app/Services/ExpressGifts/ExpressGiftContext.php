<?php

namespace App\Services\ExpressGifts;

/**
 * Contexto de la peticion actual para el flujo de obsequios Express.
 *
 * Se registra en el contenedor solo durante un checkout Express (vive lo que dura
 * la peticion). Mientras exista, las reglas "descuento O obsequio" se resuelven
 * por producto con el planificador en lugar del interruptor global allow_discounts.
 *
 * Modos:
 *  - MODE_PLANNING: el planificador necesita ver el descuento "crudo" de cada producto,
 *    asi que ningun bloqueo por bonificacion aplica.
 *  - MODE_ACTIVE: solo los productos donde gano el obsequio quedan sin descuentos.
 */
final class ExpressGiftContext
{
    public const MODE_PLANNING = 'planning';

    public const MODE_ACTIVE = 'active';

    /**
     * @param  array<int, true>  $blockedProductIds  product_id => true (gano el obsequio; sin descuentos)
     */
    public function __construct(
        public string $mode = self::MODE_PLANNING,
        public array $blockedProductIds = [],
    ) {
    }

    public static function current(): ?self
    {
        if (! function_exists('app') || ! app()->bound(self::class)) {
            return null;
        }

        return app(self::class);
    }

    public static function activate(string $mode, array $blockedProductIds = []): self
    {
        $ctx = new self($mode, $blockedProductIds);
        app()->instance(self::class, $ctx);

        return $ctx;
    }

    public static function deactivate(): void
    {
        if (function_exists('app') && app()->bound(self::class)) {
            app()->forgetInstance(self::class);
        }
    }

    /**
     * Whether bonifications currently remove discounts from the given product.
     * Returns null when no express context exists (caller keeps the legacy rule).
     */
    public static function blocksDiscountsFor(int $productId): ?bool
    {
        $ctx = self::current();
        if ($ctx === null) {
            return null;
        }

        if ($ctx->mode === self::MODE_PLANNING) {
            return false;
        }

        return isset($ctx->blockedProductIds[$productId]);
    }
}
