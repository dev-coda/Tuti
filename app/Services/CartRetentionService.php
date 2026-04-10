<?php

namespace App\Services;

use App\Models\RetentionRule;
use Illuminate\Support\Collection;

class CartRetentionService
{
    /** Default IVA % on shipping quotes when splitting base / IVA (Colombia). Override via env RETENTIONS_SHIPPING_IVA_PERCENT. */
    private static function shippingIvaPercent(): float
    {
        return (float) env('RETENTIONS_SHIPPING_IVA_PERCENT', 19);
    }

    /**
     * @param  array{base_rte_fuente: float, pct_rte_fuente: float, base_rte_iva: float, pct_rte_iva: float}|null  $articuloRule
     * @param  array{base_rte_fuente: float, pct_rte_fuente: float, base_rte_iva: float, pct_rte_iva: float}|null  $fleteRule
     * @return array{
     *     applies: bool,
     *     tax_group: ?string,
     *     tax_group_label: ?string,
     *     rete_fuente_articulos: float,
     *     rete_fuente_flete: float,
     *     rete_iva_articulos: float,
     *     rete_iva_flete: float,
     *     retention_fuente: float,
     *     retention_iva: float,
     *     retention_total: float,
     *     rules_for_js: ?array
     * }
     */
    public function calculateFromAggregates(
        ?string $taxGroup,
        float $baseArticulos,
        float $ivaArticulos,
        float $shippingTaxedAmount = 0.0,
        bool $withRulesForJs = true
    ): array {
        $empty = [
            'applies' => false,
            'tax_group' => $taxGroup,
            'tax_group_label' => null,
            'rete_fuente_articulos' => 0.0,
            'rete_fuente_flete' => 0.0,
            'rete_iva_articulos' => 0.0,
            'rete_iva_flete' => 0.0,
            'retention_fuente' => 0.0,
            'retention_iva' => 0.0,
            'retention_total' => 0.0,
            'rules_for_js' => null,
        ];

        if (!$taxGroup || $taxGroup === 'C_NORETIE') {
            return $empty;
        }

        $label = RetentionRule::TAX_GROUPS[$taxGroup] ?? $taxGroup;

        $rules = RetentionRule::active()->forGroup($taxGroup)->get()->keyBy('product_type');
        $articuloRule = $rules->get('articulo');
        $fleteRule = $rules->get('flete');

        if (!$articuloRule && !$fleteRule) {
            return array_merge($empty, ['tax_group_label' => $label]);
        }

        $reteFuenteArt = $articuloRule ? $articuloRule->calculateReteFuente($baseArticulos) : 0.0;
        $reteIvaArt = $articuloRule ? $articuloRule->calculateReteIva($ivaArticulos) : 0.0;

        $shippingIvaPct = self::shippingIvaPercent();
        $baseFlete = 0.0;
        $ivaFlete = 0.0;

        if ($shippingTaxedAmount > 0) {
            if ($shippingIvaPct > 0) {
                $baseFlete = $shippingTaxedAmount / (1 + $shippingIvaPct / 100);
                $ivaFlete = $shippingTaxedAmount - $baseFlete;
            } else {
                $baseFlete = $shippingTaxedAmount;
            }
        }

        $reteFuenteFlete = $fleteRule ? $fleteRule->calculateReteFuente($baseFlete) : 0.0;
        $reteIvaFlete = $fleteRule ? $fleteRule->calculateReteIva($ivaFlete) : 0.0;

        $retentionFuente = round($reteFuenteArt + $reteFuenteFlete, 2);
        $retentionIva = round($reteIvaArt + $reteIvaFlete, 2);
        $retentionTotal = round($retentionFuente + $retentionIva, 2);

        $applies = $retentionTotal > 0.00001;

        $rulesForJs = null;
        if ($withRulesForJs) {
            $rulesForJs = [
                'tax_group' => $taxGroup,
                'articulo' => $articuloRule ? [
                    'base_rte_fuente' => (float) $articuloRule->base_rte_fuente,
                    'pct_rte_fuente' => (float) $articuloRule->pct_rte_fuente,
                    'base_rte_iva' => (float) $articuloRule->base_rte_iva,
                    'pct_rte_iva' => (float) $articuloRule->pct_rte_iva,
                ] : null,
                'flete' => $fleteRule ? [
                    'base_rte_fuente' => (float) $fleteRule->base_rte_fuente,
                    'pct_rte_fuente' => (float) $fleteRule->pct_rte_fuente,
                    'base_rte_iva' => (float) $fleteRule->base_rte_iva,
                    'pct_rte_iva' => (float) $fleteRule->pct_rte_iva,
                ] : null,
                'shipping_iva_percent' => $shippingIvaPct,
            ];
        }

        return [
            'applies' => $applies,
            'tax_group' => $taxGroup,
            'tax_group_label' => $label,
            'rete_fuente_articulos' => round($reteFuenteArt, 2),
            'rete_fuente_flete' => round($reteFuenteFlete, 2),
            'rete_iva_articulos' => round($reteIvaArt, 2),
            'rete_iva_flete' => round($reteIvaFlete, 2),
            'retention_fuente' => $retentionFuente,
            'retention_iva' => $retentionIva,
            'retention_total' => $retentionTotal,
            'rules_for_js' => $rulesForJs,
        ];
    }

    /**
     * Line total includes IVA (typical cart / first-order pricing).
     */
    public static function accumulateFromTaxInclusiveLine(float $lineTotal, float $taxPct, float $baseAcc, float $ivaAcc): array
    {
        if ($taxPct > 0) {
            $base = $lineTotal / (1 + $taxPct / 100);

            return [$baseAcc + $base, $ivaAcc + ($lineTotal - $base)];
        }

        return [$baseAcc + $lineTotal, $ivaAcc];
    }

    /**
     * Line total excludes IVA (e.g. has_orders reset branch using lista base sin impuesto).
     */
    public static function accumulateFromTaxExclusiveLine(float $lineTotal, float $taxPct, float $baseAcc, float $ivaAcc): array
    {
        if ($taxPct > 0) {
            return [$baseAcc + $lineTotal, $ivaAcc + $lineTotal * ($taxPct / 100)];
        }

        return [$baseAcc + $lineTotal, $ivaAcc];
    }

    /**
     * Estimate retentions for the cart using active rules for the customer's tax group.
     *
     * @param  Collection<int, \App\Models\Product>  $products  Items with calculatedFinalPrice, quantity, tax loaded
     */
    public function calculateForCart(?string $taxGroup, Collection $products, float $shippingTaxedAmount = 0.0): array
    {
        $baseArticulos = 0.0;
        $ivaArticulos = 0.0;

        foreach ($products as $product) {
            $lineTotal = (float) (($product->calculatedFinalPrice['price'] ?? 0) * ($product->quantity ?? 0));
            $taxPct = optional($product->tax)->tax;
            $taxPct = $taxPct !== null ? (float) $taxPct : 0.0;

            [$baseArticulos, $ivaArticulos] = self::accumulateFromTaxInclusiveLine(
                $lineTotal,
                $taxPct,
                $baseArticulos,
                $ivaArticulos
            );
        }

        return $this->calculateFromAggregates($taxGroup, $baseArticulos, $ivaArticulos, $shippingTaxedAmount, true);
    }
}
