<?php

namespace App\Services\Shipping;

use RuntimeException;

/**
 * Spreads the merchandise total a client already approved across FV product
 * units when a city includes bonificaciones. The presale workflow does not use
 * this class; it still sends zero-price gift lines or a separate obsequio.
 *
 * Approved total: tax-exclusive net of the paid lines only (quantity × the
 * unit price already stored for the presale × (1 − discount)). Shipping is
 * not an input and must stay on its own freight line.
 *
 * Weight: reference extended value (reference unit price × quantity). Paid
 * lines use that stored unit price. Bonificación lines use the catalog/list
 * price (variation pivot when it is positive, otherwise product.price),
 * divided by package_quantity when the product is priced per package. If that
 * catalog price is not positive, {@see resolveReferenceUnitCents()} falls
 * back to the paid reference of the same SKU. If neither is positive the
 * caller must refuse the FV instead of emitting 0.00.
 *
 * Guardrails, all enforced here: every unit price is a positive amount of
 * cents, never above that line's reference unit price, and the sum of
 * (unit price × quantity) equals the approved total exactly. A rounding
 * remainder is applied 0.01 at a time to a line whose quantity can absorb it
 * without reaching 0.00, going negative, or passing the reference cap. If the
 * floors and caps make an exact split impossible, allocation throws.
 */
class FvLineProration
{
    /**
     * Catalog/list unit price in cents. Package-priced products are divided
     * down to the same per-unit basis the presale stores on paid lines.
     */
    public static function referenceUnitCents(float $price, bool $calculatePackagePrice, int $packageQuantity): int
    {
        $packageQuantity = max(1, $packageQuantity);
        $unit = ($calculatePackagePrice && $packageQuantity > 1)
            ? $price / $packageQuantity
            : $price;

        return (int) round($unit * 100);
    }

    /**
     * Same-SKU paid price is the only fallback. A non-positive result means
     * no sensible reference exists and the prorated FV must not be sent.
     */
    public static function resolveReferenceUnitCents(int $catalogCents, ?int $sameSkuPaidCents): int
    {
        if ($catalogCents > 0) {
            return $catalogCents;
        }

        if ($sameSkuPaidCents !== null && $sameSkuPaidCents > 0) {
            return $sameSkuPaidCents;
        }

        return 0;
    }

    public static function paidLineNetCents(int $qty, int $unitCents, int $discountPercent): int
    {
        $discountPercent = max(0, min(100, $discountPercent));
        $numerator = $qty * $unitCents * (100 - $discountPercent);
        $cents = intdiv($numerator, 100);
        if ($numerator % 100 >= 50) {
            $cents++;
        }

        return $cents;
    }

    /**
     * @param  list<array{sku: string, qty: int, weight_cents: int, cap_unit_cents: int, tax_rate: float}>  $lines
     * @return list<array{sku: string, qty: int, unit_cents: int, tax_value: string}>
     */
    public static function allocate(int $approvedMerchandiseCents, array $lines): array
    {
        if ($lines === []) {
            throw new RuntimeException('FV proration refused: there are no product lines to price.');
        }

        if ($approvedMerchandiseCents < 0) {
            throw new RuntimeException('FV proration refused: approved merchandise total is negative.');
        }

        if ($approvedMerchandiseCents === 0) {
            throw new RuntimeException(
                'FV proration refused: approved merchandise total is 0.00, so every prorated unit price would be zero.'
            );
        }

        $sumWeight = 0;
        $minExtended = 0;
        $maxExtended = 0;

        foreach ($lines as $line) {
            $qty = (int) $line['qty'];
            $cap = (int) $line['cap_unit_cents'];
            $weight = (int) $line['weight_cents'];
            $sku = (string) $line['sku'];

            if ($qty <= 0 || $weight <= 0 || $cap <= 0) {
                throw new RuntimeException(
                    "FV proration refused: SKU {$sku} needs a positive whole quantity and a positive reference price."
                );
            }

            $sumWeight += $weight;
            $minExtended += $qty;
            $maxExtended += $cap * $qty;
        }

        if ($approvedMerchandiseCents < $minExtended) {
            throw new RuntimeException(
                'FV proration refused: cannot price every unit at least 0.01 without exceeding the approved merchandise total of '
                .self::formatCents($approvedMerchandiseCents).'.'
            );
        }

        if ($approvedMerchandiseCents > $maxExtended) {
            throw new RuntimeException(
                'FV proration refused: prorated prices would exceed reference unit prices (approved '
                .self::formatCents($approvedMerchandiseCents)
                .', reference cap '.self::formatCents($maxExtended).').'
            );
        }

        $units = [];
        $meta = [];
        $assigned = 0;

        foreach ($lines as $i => $line) {
            $qty = (int) $line['qty'];
            $denominator = $sumWeight * $qty;
            $numerator = $approvedMerchandiseCents * (int) $line['weight_cents'];
            $unclamped = intdiv($numerator, $denominator);
            $fraction = $numerator % $denominator;
            $unit = $unclamped;
            if ($unit < 1) {
                $unit = 1;
            }
            if ($unit > (int) $line['cap_unit_cents']) {
                $unit = (int) $line['cap_unit_cents'];
            }

            $units[$i] = $unit;
            $meta[$i] = [
                'unclamped' => $unclamped,
                'fraction' => $fraction,
                'denominator' => $denominator,
            ];
            $assigned += $unit * $qty;
        }

        $remainder = $approvedMerchandiseCents - $assigned;
        $guard = 0;
        $limit = max(1, abs($approvedMerchandiseCents) + $sumWeight);

        while ($remainder !== 0) {
            if (++$guard > $limit) {
                throw new RuntimeException(self::remainderMessage($remainder));
            }

            $chosen = self::chooseRemainderLine($lines, $units, $meta, $remainder);
            if ($chosen === null) {
                throw new RuntimeException(self::remainderMessage($remainder));
            }

            if ($remainder > 0) {
                $units[$chosen]++;
                $remainder -= (int) $lines[$chosen]['qty'];
            } else {
                $units[$chosen]--;
                $remainder += (int) $lines[$chosen]['qty'];
            }
        }

        $result = [];
        $sum = 0;

        foreach ($lines as $i => $line) {
            $unit = $units[$i];
            $qty = (int) $line['qty'];
            if ($unit < 1 || $unit > (int) $line['cap_unit_cents']) {
                throw new RuntimeException(
                    'FV proration refused: SKU '.$line['sku'].' unit price left the allowed range.'
                );
            }

            $sum += $unit * $qty;
            $result[] = [
                'sku' => (string) $line['sku'],
                'qty' => $qty,
                'unit_cents' => $unit,
                'tax_value' => self::taxValue($qty, $unit, (float) $line['tax_rate']),
            ];
        }

        if ($sum !== $approvedMerchandiseCents) {
            throw new RuntimeException(
                'FV proration refused: allocated total does not match the approved merchandise total.'
            );
        }

        return $result;
    }

    /**
     * Same basis as paid FV lines: round(qty × unitPrice × (1 − discount) × taxRate / 100, 2)
     * with the prorated discount already baked into the unit price.
     */
    public static function taxValue(int $qty, int $unitCents, float $taxRate): string
    {
        $unitPrice = $unitCents / 100;
        $tax = round($qty * $unitPrice * (1 - 0 / 100) * $taxRate / 100, 2);

        return number_format($tax, 2, '.', '');
    }

    private static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function remainderMessage(int $remainder): string
    {
        return 'FV proration refused: rounding remainder of '.$remainder
            .' cents cannot be absorbed without a zero, negative, or above-reference unit price.';
    }

    /**
     * @param  list<array{sku: string, qty: int, weight_cents: int, cap_unit_cents: int, tax_rate: float}>  $lines
     * @param  array<int, int>  $units
     * @param  array<int, array{unclamped: int, fraction: int, denominator: int}>  $meta
     */
    private static function chooseRemainderLine(array $lines, array $units, array $meta, int $remainder): ?int
    {
        $best = null;
        $bestShortfall = null;

        foreach ($lines as $i => $line) {
            $qty = (int) $line['qty'];
            if ($remainder > 0) {
                if ($units[$i] >= (int) $line['cap_unit_cents'] || $qty > $remainder) {
                    continue;
                }
            } elseif ($units[$i] <= 1 || $qty > -$remainder) {
                continue;
            }

            $shortfall = ($meta[$i]['unclamped'] + ($meta[$i]['fraction'] / $meta[$i]['denominator'])) - $units[$i];
            if ($best === null) {
                $best = $i;
                $bestShortfall = $shortfall;

                continue;
            }

            // Extra cents go to the line furthest under its weighted ideal.
            // Cents we have to take back come off the line furthest above it.
            // Equal shortfalls keep the earlier line.
            $better = $remainder > 0 ? $shortfall > $bestShortfall : $shortfall < $bestShortfall;
            if ($better) {
                $best = $i;
                $bestShortfall = $shortfall;
            }
        }

        return $best;
    }
}
