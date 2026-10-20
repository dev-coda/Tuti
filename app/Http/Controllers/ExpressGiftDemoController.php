<?php

namespace App\Http\Controllers;

use App\Services\BonificationCheckoutService;
use App\Services\ExpressGifts\GiftProrationService;

/**
 * Página local para ver el prorrateo Express con el mismo cálculo del checkout.
 */
class ExpressGiftDemoController extends Controller
{
    public function __invoke(GiftProrationService $proration)
    {
        abort_unless(app()->environment('local'), 404);

        $tax = 19;
        $lines = [
            ['key' => 'cafe', 'product_id' => 1, 'name' => 'Café molido 500g', 'role' => 'Activa la bonificación (compra 10)', 'qty' => 10, 'unit_price' => 10000.00, 'tax_rate' => $tax],
            ['key' => 'azucar', 'product_id' => 2, 'name' => 'Azúcar 1kg', 'role' => 'Otro producto sin descuento', 'qty' => 4, 'unit_price' => 5000.00, 'tax_rate' => $tax],
        ];
        $gift = [
            'key' => 'taza',
            'name' => 'Taza obsequio',
            'qty' => 1,
            'unit_value' => 500.00,
            'tax_rate' => $tax,
            'rule' => 'Compra 10, lleva 1',
        ];

        $before = $this->inclTotal($lines);

        $spendingLines = [
            ['key' => 'cafe', 'product_id' => 1, 'name' => 'Café molido 500g', 'qty' => 10, 'unit_price' => 10000.00, 'tax_rate' => $tax],
            ['key' => 'azucar', 'product_id' => 2, 'name' => 'Azúcar 1kg', 'qty' => 4, 'unit_price' => 5000.00, 'tax_rate' => $tax],
            ['key' => 'galletas', 'product_id' => 3, 'name' => 'Galletas surtidas', 'qty' => 6, 'unit_price' => 3000.00, 'tax_rate' => $tax],
        ];
        $spendingBefore = $this->inclTotal($spendingLines);
        $spent = [];
        foreach ($spendingLines as $line) {
            $spent[$line['product_id']] = round($line['qty'] * $line['unit_price'] * (1 + $line['tax_rate'] / 100), 2);
        }
        $rules = [
            [
                'name' => 'Taza',
                'gift' => 'Taza de cerámica',
                'products' => 'Café y azúcar',
                'ids' => [1, 2],
                'minimum' => 100000.00,
                'qty' => 1,
                'unit_value' => 500.00,
            ],
            [
                'name' => 'Cuchara',
                'gift' => 'Cuchara de madera',
                'products' => 'Solo azúcar',
                'ids' => [2],
                'minimum' => 50000.00,
                'qty' => 1,
                'unit_value' => 300.00,
            ],
            [
                'name' => 'Termo',
                'gift' => 'Termo 500 ml',
                'products' => 'Solo galletas',
                'ids' => [3],
                'minimum' => 15000.00,
                'qty' => 1,
                'unit_value' => 1200.00,
            ],
        ];
        $granted = [];
        foreach ($rules as &$rule) {
            $rule['spent'] = round(array_sum(array_map(fn ($id) => $spent[$id] ?? 0, $rule['ids'])), 2);
            $rule['granted'] = $rule['spent'] + 0.009 >= $rule['minimum'];
            if ($rule['granted']) {
                $granted[] = [
                    'key' => $rule['name'],
                    'name' => $rule['gift'],
                    'qty' => $rule['qty'],
                    'unit_value' => $rule['unit_value'],
                    'tax_rate' => $tax,
                    'scope' => GiftProrationService::SCOPE_CART,
                ];
            }
        }
        unset($rule);

        return view('express-gifts.demo', [
            'lines' => $lines,
            'gift' => $gift,
            'before' => $before,
            'cart' => $this->present($proration, $lines, $gift, GiftProrationService::SCOPE_CART),
            'trigger' => $this->present($proration, $lines, $gift, GiftProrationService::SCOPE_TRIGGER),
            'blocked' => $this->present($proration, $lines, array_merge($gift, ['unit_value' => 100000.00]), GiftProrationService::SCOPE_TRIGGER),
            'spendingLines' => $spendingLines,
            'spendingBefore' => $spendingBefore,
            'rules' => $rules,
            'spendingInvoice' => $this->presentMany($proration, $spendingLines, $granted),
            'mixedGift' => BonificationCheckoutService::giftUnits(10, 1, 1, 6 + 4),
        ]);
    }

    private function present(GiftProrationService $proration, array $lines, array $gift, string $scope): array
    {
        $result = $proration->prorateScoped($lines, [[
            'key' => $gift['key'],
            'qty' => $gift['qty'],
            'unit_value' => $gift['unit_value'],
            'tax_rate' => $gift['tax_rate'],
            'scope' => $scope,
            'trigger_product_id' => 1,
        ]]);

        $rows = [];
        $after = 0.0;
        foreach ($lines as $line) {
            $deduction = (float) ($result['deductions'][$line['key']] ?? 0);
            $unit = round($line['unit_price'] - $deduction, 2);
            $net = $unit * $line['qty'];
            $incl = round($net * (1 + $line['tax_rate'] / 100), 2);
            $after += $incl;
            $rows[] = [
                'name' => $line['name'],
                'qty' => $line['qty'],
                'before' => $line['unit_price'],
                'deduction' => $deduction,
                'after' => $unit,
                'incl' => $incl,
                'touched' => $deduction > 0,
            ];
        }

        $giftNet = (float) ($result['gift_totals'][$gift['key']] ?? 0);
        $giftIncl = round($giftNet * (1 + $gift['tax_rate'] / 100), 2);
        $after += $giftIncl;

        return [
            'feasible' => ($result['failed'] ?? []) === [],
            'reason' => $result['reason'] ?? ($result['failed'][$gift['key']] ?? null),
            'rows' => $rows,
            'gift_net' => $giftNet,
            'gift_incl' => $giftIncl,
            'after' => round($after, 2),
            'delta' => (float) ($result['balance_delta'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array{key: string, qty: int, unit_value: float, tax_rate: float, scope: string, name: string}>  $gifts
     */
    private function presentMany(GiftProrationService $proration, array $lines, array $gifts): array
    {
        $result = $proration->prorateScoped($lines, $gifts);
        $rows = [];
        $after = 0.0;
        foreach ($lines as $line) {
            $deduction = (float) ($result['deductions'][$line['key']] ?? 0);
            $unit = round($line['unit_price'] - $deduction, 2);
            $incl = round($unit * $line['qty'] * (1 + $line['tax_rate'] / 100), 2);
            $after += $incl;
            $rows[] = [
                'name' => $line['name'],
                'before' => $line['unit_price'],
                'deduction' => $deduction,
                'after' => $unit,
                'touched' => $deduction > 0,
            ];
        }

        $giftRows = [];
        foreach ($gifts as $gift) {
            $net = (float) ($result['gift_totals'][$gift['key']] ?? 0);
            $incl = round($net * (1 + $gift['tax_rate'] / 100), 2);
            $after += $incl;
            $giftRows[] = ['name' => $gift['name'], 'net' => $net, 'incl' => $incl];
        }

        return [
            'feasible' => ($result['failed'] ?? []) === [],
            'reason' => $result['reason'] ?? null,
            'rows' => $rows,
            'gifts' => $giftRows,
            'after' => round($after, 2),
        ];
    }

    private function inclTotal(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += $line['qty'] * $line['unit_price'] * (1 + $line['tax_rate'] / 100);
        }

        return round($total, 2);
    }
}
