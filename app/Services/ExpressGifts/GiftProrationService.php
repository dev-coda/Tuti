<?php

namespace App\Services\ExpressGifts;

/**
 * Prorratea el valor facturable de uno o varios obsequios entre las lineas pagadas
 * sin descuento, de modo que el total de la factura (con IVA) no cambie.
 *
 * Es una clase pura (sin base de datos) para poder probarla de forma exhaustiva.
 *
 * Modelo:
 *  - El obsequio se factura con un valor neto (>= minimo) por unidad.
 *  - Ese valor, ya con IVA del obsequio, se resta de las lineas elegibles de forma
 *    proporcional a su valor con IVA. Como todas las lineas pierden la misma fraccion
 *    f = valorObsequioConIva / valorLineasConIva, cada precio unitario baja f%.
 *  - La rebaja por unidad se redondea a centavos; despues el valor facturado del
 *    obsequio se recalcula con lo realmente descontado para que la factura cuadre.
 *  - Ninguna linea puede quedar por debajo del piso (por defecto 10% del precio) ni en cero.
 *
 * Todos los calculos internos se hacen en centavos enteros.
 */
final class GiftProrationService
{
    public const CODE_NO_ELIGIBLE_LINES = 'no_eligible_lines';

    public const CODE_LINE_BELOW_FLOOR = 'line_below_floor';

    public const CODE_GIFT_VALUE_TOO_LOW = 'gift_value_too_low';

    public const CODE_BALANCE_OUT_OF_TOLERANCE = 'balance_out_of_tolerance';

    public const SCOPE_CART = 'cart';

    public const SCOPE_TRIGGER = 'trigger';

    /**
     * @param  array<int, array{key: int|string, qty: int|float, unit_price: float|int|string, tax_rate?: float|int|string}>  $lines  Lineas pagadas SIN descuento (precio neto por unidad de factura).
     * @param  array<int, array{key: int|string, qty: int|float, unit_value: float|int|string, tax_rate?: float|int|string}>  $gifts  Obsequios a facturar (valor neto por unidad).
     * @return array{
     *     feasible: bool,
     *     code: ?string,
     *     reason: ?string,
     *     offending_key: int|string|null,
     *     fraction: float,
     *     deductions: array<int|string, float>,
     *     gift_totals: array<int|string, float>,
     *     gift_incl_total: float,
     *     reduction_incl_total: float,
     *     balance_delta: float
     * }
     */
    public function prorate(array $lines, array $gifts, ?float $floorRatio = null): array
    {
        $floorRatio ??= (float) config('express_gifts.line_floor_ratio', 0.10);
        $tolerance = (float) config('express_gifts.balance_tolerance', 0.05);

        $result = $this->emptyResult();

        $giftRows = [];
        $giftInclCents = 0.0;
        foreach ($gifts as $gift) {
            $qty = (int) round((float) $gift['qty']);
            $valueCents = $this->cents($gift['unit_value']);
            if ($qty <= 0 || $valueCents <= 0) {
                continue;
            }
            $tax = (float) ($gift['tax_rate'] ?? 0);
            $giftRows[] = [
                'key' => $gift['key'],
                'qty' => $qty,
                'base_cents' => $qty * $valueCents,
                'tax' => $tax,
            ];
            $giftInclCents += $qty * $valueCents * (1 + $tax / 100);
        }

        if ($giftRows === []) {
            $result['feasible'] = true;

            return $result;
        }

        $lineRows = [];
        $linesInclCents = 0.0;
        foreach ($lines as $line) {
            $qty = (int) round((float) $line['qty']);
            $priceCents = $this->cents($line['unit_price']);
            if ($qty <= 0 || $priceCents <= 0) {
                continue;
            }
            $tax = (float) ($line['tax_rate'] ?? 0);
            $floorCents = array_key_exists('floor_unit_price', $line)
                ? $this->cents($line['floor_unit_price'])
                : $priceCents;
            $lineRows[] = [
                'key' => $line['key'],
                'qty' => $qty,
                'price_cents' => $priceCents,
                'floor_cents' => max(1, $floorCents),
                'tax' => $tax,
            ];
            $linesInclCents += $qty * $priceCents * (1 + $tax / 100);
        }

        if ($lineRows === [] || $linesInclCents <= 0) {
            return $this->fail(
                $result,
                self::CODE_NO_ELIGIBLE_LINES,
                'No hay productos sin descuento sobre los cuales prorratear el obsequio.'
            );
        }

        $fraction = $giftInclCents / $linesInclCents;
        $result['fraction'] = $fraction;

        // Rebaja por unidad (centavos) y validacion del piso por linea.
        $deductionCents = [];
        $reductionInclCents = 0.0;
        foreach ($lineRows as $row) {
            $deduction = (int) round($row['price_cents'] * $fraction);
            $newPrice = $row['price_cents'] - $deduction;
            $minAllowed = max(1, (int) ceil($row['floor_cents'] * $floorRatio));

            if ($newPrice < $minAllowed) {
                $result['offending_key'] = $row['key'];

                return $this->fail(
                    $result,
                    self::CODE_LINE_BELOW_FLOOR,
                    'Despues de prorratear el obsequio un producto quedaria en valor cero o por debajo del minimo permitido.'
                );
            }

            $deductionCents[$row['key']] = $deduction;
            $reductionInclCents += $deduction * $row['qty'] * (1 + $row['tax'] / 100);
        }

        // Valor facturado de cada obsequio, proporcional a lo realmente descontado.
        $scale = $giftInclCents > 0 ? $reductionInclCents / $giftInclCents : 0.0;
        $giftTotals = [];
        foreach ($giftRows as $i => $row) {
            $total = (int) round($row['base_cents'] * $scale);
            if ($total < $row['qty']) {
                $result['offending_key'] = $row['key'];

                return $this->fail(
                    $result,
                    self::CODE_GIFT_VALUE_TOO_LOW,
                    'El valor del obsequio despues del prorrateo es inferior al minimo facturable.'
                );
            }
            $giftTotals[$i] = $total;
        }

        // Ajuste fino de centavos sobre el ultimo obsequio para acercar el total con IVA.
        $lastIndex = array_key_last($giftTotals);
        $diff = $this->giftInclCents($giftRows, $giftTotals) - $reductionInclCents;
        for ($guard = 0; $guard < 20; $guard++) {
            $improved = false;
            foreach ([1, -1] as $step) {
                $candidate = $giftTotals;
                $candidate[$lastIndex] += $step;
                if ($candidate[$lastIndex] < $giftRows[$lastIndex]['qty']) {
                    continue;
                }
                $candidateDiff = $this->giftInclCents($giftRows, $candidate) - $reductionInclCents;
                if (abs($candidateDiff) < abs($diff) - 1e-9) {
                    $giftTotals = $candidate;
                    $diff = $candidateDiff;
                    $improved = true;
                    break;
                }
            }
            if (! $improved) {
                break;
            }
        }

        $balanceDelta = round($diff / 100, 2);
        if (abs($balanceDelta) > $tolerance) {
            return $this->fail(
                $result,
                self::CODE_BALANCE_OUT_OF_TOLERANCE,
                'No fue posible cuadrar el total de la factura con el obsequio.'
            );
        }

        $result['feasible'] = true;
        $result['balance_delta'] = $balanceDelta;
        $result['gift_incl_total'] = round($this->giftInclCents($giftRows, $giftTotals) / 100, 2);
        $result['reduction_incl_total'] = round($reductionInclCents / 100, 2);
        foreach ($deductionCents as $key => $cents) {
            $result['deductions'][$key] = round($cents / 100, 2);
        }
        foreach ($giftRows as $i => $row) {
            $result['gift_totals'][$row['key']] = round($giftTotals[$i] / 100, 2);
        }

        return $result;
    }

    /**
     * Prorratea cada obsequio segun su alcance.
     *
     * scope=cart: todas las lineas sin descuento.
     * scope=trigger: solo las lineas del producto que activo esa bonificacion.
     * Los de alcance "trigger" se aplican primero; el del carrito usa el precio que queda.
     * El piso del 10% se calcula sobre el precio original de la linea.
     *
     * @param  array<int, array{key: int|string, qty: int|float, unit_price: float|int|string, tax_rate?: float|int|string, product_id?: int}>  $lines
     * @param  array<int, array{key: int|string, qty: int|float, unit_value: float|int|string, tax_rate?: float|int|string, scope?: string, trigger_product_id?: int}>  $gifts
     * @return array<string, mixed>
     */
    public function prorateScoped(array $lines, array $gifts, ?float $floorRatio = null): array
    {
        $working = [];
        foreach ($lines as $line) {
            $working[$line['key']] = $line;
            $working[$line['key']]['floor_unit_price'] = $line['floor_unit_price'] ?? $line['unit_price'];
        }

        $merged = $this->emptyResult();
        $merged['feasible'] = true;
        $merged['failed'] = [];

        $triggerGroups = [];
        $cartGifts = [];
        foreach ($gifts as $gift) {
            $scope = ($gift['scope'] ?? self::SCOPE_CART) === self::SCOPE_TRIGGER
                ? self::SCOPE_TRIGGER
                : self::SCOPE_CART;
            if ($scope === self::SCOPE_TRIGGER) {
                $ids = array_values(array_unique(array_map(
                    'intval',
                    $gift['trigger_product_ids'] ?? [(int) ($gift['trigger_product_id'] ?? 0)]
                )));
                sort($ids);
                $groupKey = implode(',', $ids);
                $triggerGroups[$groupKey]['ids'] = $ids;
                $triggerGroups[$groupKey]['gifts'][] = $gift;
            } else {
                $cartGifts[] = $gift;
            }
        }

        $apply = function (array $subsetLines, array $subsetGifts) use (&$working, &$merged, $floorRatio): void {
            if ($subsetGifts === []) {
                return;
            }
            if ($subsetLines === []) {
                $merged['feasible'] = false;
                foreach ($subsetGifts as $gift) {
                    $merged['failed'][$gift['key']] = 'No hay productos sin descuento sobre los cuales prorratear el obsequio.';
                }

                return;
            }

            $priced = [];
            foreach ($subsetLines as $line) {
                $current = $working[$line['key']];
                $priced[] = [
                    'key' => $line['key'],
                    'qty' => $current['qty'],
                    'unit_price' => $current['unit_price'],
                    'tax_rate' => $current['tax_rate'] ?? 0,
                    'floor_unit_price' => $current['floor_unit_price'],
                ];
            }

            $result = $this->prorate($priced, $subsetGifts, $floorRatio);
            if (! $result['feasible']) {
                $merged['feasible'] = false;
                $merged['code'] = $result['code'];
                $merged['reason'] = $result['reason'];
                foreach ($subsetGifts as $gift) {
                    $merged['failed'][$gift['key']] = $result['reason'];
                }

                return;
            }

            foreach ($result['deductions'] as $key => $amount) {
                $merged['deductions'][$key] = round(($merged['deductions'][$key] ?? 0) + (float) $amount, 2);
                $working[$key]['unit_price'] = round((float) $working[$key]['unit_price'] - (float) $amount, 2);
            }
            foreach ($result['gift_totals'] as $key => $total) {
                $merged['gift_totals'][$key] = $total;
            }
            $merged['balance_delta'] = round($merged['balance_delta'] + $result['balance_delta'], 2);
        };

        foreach ($triggerGroups as $group) {
            $subset = array_values(array_filter(
                $working,
                fn ($line) => in_array((int) ($line['product_id'] ?? 0), $group['ids'], true)
            ));
            $apply($subset, $group['gifts']);
        }

        if ($cartGifts !== []) {
            $apply(array_values($working), $cartGifts);
        }

        return $merged;
    }

    /**
     * Divide el total facturado de un obsequio en lineas de precio unitario exacto (a centavos).
     * La suma de qty x precio de las lineas devueltas es exactamente $total.
     *
     * @return array<int, array{qty: int, unit_price: float}>
     */
    public static function splitGiftTotal(float $total, int $qty): array
    {
        if ($qty <= 0) {
            return [];
        }

        $totalCents = (int) round($total * 100);
        $base = intdiv($totalCents, $qty);
        $remainder = $totalCents - ($base * $qty);

        if ($remainder === 0) {
            return [['qty' => $qty, 'unit_price' => round($base / 100, 2)]];
        }

        $lines = [];
        if ($qty - $remainder > 0) {
            $lines[] = ['qty' => $qty - $remainder, 'unit_price' => round($base / 100, 2)];
        }
        $lines[] = ['qty' => $remainder, 'unit_price' => round(($base + 1) / 100, 2)];

        return $lines;
    }

    /**
     * @param  array<int, array{qty:int, base_cents:int, tax:float}>  $giftRows
     * @param  array<int, int>  $giftTotals
     */
    private function giftInclCents(array $giftRows, array $giftTotals): float
    {
        $sum = 0.0;
        foreach ($giftRows as $i => $row) {
            $sum += $giftTotals[$i] * (1 + $row['tax'] / 100);
        }

        return $sum;
    }

    private function cents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function emptyResult(): array
    {
        return [
            'feasible' => false,
            'code' => null,
            'reason' => null,
            'offending_key' => null,
            'fraction' => 0.0,
            'deductions' => [],
            'gift_totals' => [],
            'gift_incl_total' => 0.0,
            'reduction_incl_total' => 0.0,
            'balance_delta' => 0.0,
        ];
    }

    private function fail(array $result, string $code, string $reason): array
    {
        $result['feasible'] = false;
        $result['code'] = $code;
        $result['reason'] = $reason;

        return $result;
    }
}
