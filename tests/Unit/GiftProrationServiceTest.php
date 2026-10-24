<?php

use App\Services\ExpressGifts\GiftProrationService;

uses(Tests\TestCase::class);

function proratedInclTotal(array $lines, array $result, array $gifts): array
{
    $before = 0.0;
    $after = 0.0;
    foreach ($lines as $line) {
        $tax = 1 + (($line['tax_rate'] ?? 0) / 100);
        $deduction = $result['deductions'][$line['key']] ?? 0;
        $before += $line['qty'] * $line['unit_price'] * $tax;
        $after += $line['qty'] * ($line['unit_price'] - $deduction) * $tax;
    }
    foreach ($gifts as $gift) {
        $after += $result['gift_totals'][$gift['key']] * (1 + (($gift['tax_rate'] ?? 0) / 100));
    }

    return [round($before, 2), round($after, 2)];
}

it('keeps the invoice total unchanged when everything has the same tax', function () {
    $lines = [
        ['key' => 'a', 'qty' => 10, 'unit_price' => 1000.00, 'tax_rate' => 19],
        ['key' => 'b', 'qty' => 4, 'unit_price' => 2500.00, 'tax_rate' => 19],
    ];
    $gifts = [['key' => 'g', 'qty' => 2, 'unit_value' => 150.00, 'tax_rate' => 19]];

    $result = (new GiftProrationService)->prorate($lines, $gifts);

    expect($result['feasible'])->toBeTrue();
    [$before, $after] = proratedInclTotal($lines, $result, $gifts);
    expect(abs($before - $after))->toBeLessThanOrEqual(0.05);
    expect($result['gift_totals']['g'])->toBeGreaterThan(0);
    foreach ($lines as $line) {
        expect($result['deductions'][$line['key']])->toBeGreaterThan(0)
            ->and($result['deductions'][$line['key']])->toBeLessThan($line['unit_price']);
    }
});

it('keeps the invoice total with tax balanced across mixed tax rates', function () {
    $lines = [
        ['key' => 'a', 'qty' => 7, 'unit_price' => 1234.56, 'tax_rate' => 0],
        ['key' => 'b', 'qty' => 3, 'unit_price' => 987.65, 'tax_rate' => 19],
        ['key' => 'c', 'qty' => 12, 'unit_price' => 410.10, 'tax_rate' => 5],
    ];
    $gifts = [
        ['key' => 'g1', 'qty' => 3, 'unit_value' => 100.00, 'tax_rate' => 19],
        ['key' => 'g2', 'qty' => 1, 'unit_value' => 55.50, 'tax_rate' => 0],
    ];

    $result = (new GiftProrationService)->prorate($lines, $gifts);

    expect($result['feasible'])->toBeTrue();
    [$before, $after] = proratedInclTotal($lines, $result, $gifts);
    expect(abs($before - $after))->toBeLessThanOrEqual(0.05)
        ->and(abs($result['balance_delta']))->toBeLessThanOrEqual(0.05);
});

it('never returns a zero or negative line price when feasible', function () {
    mt_srand(42);
    for ($i = 0; $i < 200; $i++) {
        $lines = [];
        for ($j = 0; $j < mt_rand(1, 5); $j++) {
            $lines[] = [
                'key' => "l$j",
                'qty' => mt_rand(1, 20),
                'unit_price' => mt_rand(100, 500000) / 100,
                'tax_rate' => [0, 5, 19][mt_rand(0, 2)],
            ];
        }
        $gifts = [['key' => 'g', 'qty' => mt_rand(1, 6), 'unit_value' => mt_rand(1, 20000) / 100, 'tax_rate' => [0, 19][mt_rand(0, 1)]]];

        $result = (new GiftProrationService)->prorate($lines, $gifts);

        if ($result['feasible']) {
            foreach ($lines as $line) {
                $newPrice = $line['unit_price'] - $result['deductions'][$line['key']];
                expect($newPrice)->toBeGreaterThan(0);
            }
            expect(abs($result['balance_delta']))->toBeLessThanOrEqual(0.05);
        } else {
            expect($result['code'])->not->toBeNull()->and($result['reason'])->not->toBeEmpty();
        }
    }
});

it('refuses to apply the gift when a line would fall below the floor', function () {
    $lines = [['key' => 'a', 'qty' => 1, 'unit_price' => 100.00, 'tax_rate' => 0]];
    $gifts = [['key' => 'g', 'qty' => 1, 'unit_value' => 95.00, 'tax_rate' => 0]];

    $result = (new GiftProrationService)->prorate($lines, $gifts);

    expect($result['feasible'])->toBeFalse()
        ->and($result['code'])->toBe(GiftProrationService::CODE_LINE_BELOW_FLOOR)
        ->and($result['offending_key'])->toBe('a')
        ->and($result['reason'])->not->toBeEmpty();
});

it('refuses when the gift value would cancel the lines entirely', function () {
    $lines = [['key' => 'a', 'qty' => 2, 'unit_price' => 50.00, 'tax_rate' => 0]];
    $gifts = [['key' => 'g', 'qty' => 1, 'unit_value' => 500.00, 'tax_rate' => 0]];

    $result = (new GiftProrationService)->prorate($lines, $gifts);

    expect($result['feasible'])->toBeFalse();
});

it('refuses when there are no discount-free lines to prorate against', function () {
    $result = (new GiftProrationService)->prorate([], [['key' => 'g', 'qty' => 1, 'unit_value' => 10.0]]);

    expect($result['feasible'])->toBeFalse()
        ->and($result['code'])->toBe(GiftProrationService::CODE_NO_ELIGIBLE_LINES);
});

it('is a no-op when there are no gifts', function () {
    $result = (new GiftProrationService)->prorate(
        [['key' => 'a', 'qty' => 1, 'unit_price' => 100.0]],
        []
    );

    expect($result['feasible'])->toBeTrue()->and($result['deductions'])->toBe([]);
});

it('prorates a trigger-scoped gift only onto the product that earned it', function () {
    $lines = [
        ['key' => 'a', 'product_id' => 1, 'qty' => 10, 'unit_price' => 1000.00, 'tax_rate' => 19],
        ['key' => 'b', 'product_id' => 2, 'qty' => 4, 'unit_price' => 2500.00, 'tax_rate' => 19],
    ];
    $gifts = [[
        'key' => 'g',
        'qty' => 1,
        'unit_value' => 100.00,
        'tax_rate' => 19,
        'scope' => GiftProrationService::SCOPE_TRIGGER,
        'trigger_product_id' => 1,
    ]];

    $result = (new GiftProrationService)->prorateScoped($lines, $gifts);

    expect($result['failed'])->toBe([])
        ->and($result['deductions']['a'])->toBeGreaterThan(0)
        ->and($result['deductions'])->not->toHaveKey('b');
});

it('prorates a cart-scoped gift across every discount-free line', function () {
    $lines = [
        ['key' => 'a', 'product_id' => 1, 'qty' => 10, 'unit_price' => 1000.00, 'tax_rate' => 0],
        ['key' => 'b', 'product_id' => 2, 'qty' => 4, 'unit_price' => 2500.00, 'tax_rate' => 0],
    ];
    $gifts = [[
        'key' => 'g',
        'qty' => 1,
        'unit_value' => 100.00,
        'tax_rate' => 0,
        'scope' => GiftProrationService::SCOPE_CART,
    ]];

    $result = (new GiftProrationService)->prorateScoped($lines, $gifts);

    expect($result['failed'])->toBe([])
        ->and($result['deductions']['a'])->toBeGreaterThan(0)
        ->and($result['deductions']['b'])->toBeGreaterThan(0);
});

it('splits a gift total into exact cent-level unit prices', function () {
    foreach ([[10.00, 3], [10.01, 3], [0.07, 5], [123.45, 7], [99.99, 1]] as [$total, $qty]) {
        $lines = GiftProrationService::splitGiftTotal($total, $qty);
        $sumQty = array_sum(array_column($lines, 'qty'));
        $sumValue = 0.0;
        foreach ($lines as $line) {
            $sumValue += $line['qty'] * $line['unit_price'];
        }
        expect($sumQty)->toBe($qty)
            ->and(round($sumValue, 2))->toBe(round($total, 2));
    }
});
