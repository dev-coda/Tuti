<?php

uses(Tests\TestCase::class);

use App\Services\Shipping\FvLineProration;

it('weights the split by reference extended value instead of giving every sku the same unit price', function () {
    $lines = FvLineProration::allocate(9000, [
        ['sku' => 'PAID', 'qty' => 1, 'weight_cents' => 9000, 'cap_unit_cents' => 9000, 'tax_rate' => 19],
        ['sku' => 'GIFT', 'qty' => 1, 'weight_cents' => 1000, 'cap_unit_cents' => 1000, 'tax_rate' => 19],
    ]);

    expect($lines[0]['unit_cents'])->toBe(8100)
        ->and($lines[1]['unit_cents'])->toBe(900)
        ->and($lines[0]['unit_cents'])->not->toBe($lines[1]['unit_cents'])
        ->and($lines[0]['tax_value'])->toBe('15.39')
        ->and($lines[1]['tax_value'])->toBe('1.71');

    $sum = 0;
    foreach ($lines as $line) {
        expect($line['unit_cents'])->toBeGreaterThan(0)
            ->and($line['unit_cents'])->toBeLessThanOrEqual($line['sku'] === 'PAID' ? 9000 : 1000);
        $sum += $line['unit_cents'] * $line['qty'];
    }
    expect($sum)->toBe(9000);
});

it('puts the rounding remainder on the line that can absorb it', function () {
    $lines = FvLineProration::allocate(600, [
        ['sku' => 'CASE', 'qty' => 3, 'weight_cents' => 600, 'cap_unit_cents' => 200, 'tax_rate' => 0],
        ['sku' => 'EACH', 'qty' => 1, 'weight_cents' => 100, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]);

    expect($lines[0]['unit_cents'])->toBe(171)
        ->and($lines[0]['qty'])->toBe(3)
        ->and($lines[1]['unit_cents'])->toBe(87)
        ->and($lines[1]['qty'])->toBe(1)
        ->and(($lines[0]['unit_cents'] * 3) + $lines[1]['unit_cents'])->toBe(600)
        ->and($lines[0]['tax_value'])->toBe('0.00')
        ->and($lines[1]['unit_cents'])->toBeLessThanOrEqual(100)
        ->and($lines[0]['unit_cents'])->toBeLessThanOrEqual(200);
});

it('refuses a remainder that would need a zero or above-reference unit price', function () {
    expect(fn () => FvLineProration::allocate(100, [
        ['sku' => 'A', 'qty' => 3, 'weight_cents' => 300, 'cap_unit_cents' => 100, 'tax_rate' => 0],
        ['sku' => 'B', 'qty' => 3, 'weight_cents' => 300, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]))->toThrow(RuntimeException::class, 'rounding remainder');
});

it('refuses when caps or a zero approved total would make the prices nonsense', function () {
    expect(fn () => FvLineProration::allocate(500, [
        ['sku' => 'A', 'qty' => 1, 'weight_cents' => 100, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]))->toThrow(RuntimeException::class, 'exceed reference unit prices');

    expect(fn () => FvLineProration::allocate(0, [
        ['sku' => 'A', 'qty' => 1, 'weight_cents' => 100, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]))->toThrow(RuntimeException::class, 'would be zero');

    expect(fn () => FvLineProration::allocate(5, [
        ['sku' => 'A', 'qty' => 2, 'weight_cents' => 200, 'cap_unit_cents' => 100, 'tax_rate' => 0],
        ['sku' => 'B', 'qty' => 2, 'weight_cents' => 200, 'cap_unit_cents' => 100, 'tax_rate' => 0],
        ['sku' => 'C', 'qty' => 2, 'weight_cents' => 200, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]))->toThrow(RuntimeException::class, 'at least 0.01');

    expect(fn () => FvLineProration::allocate(100, [
        ['sku' => 'A', 'qty' => -1, 'weight_cents' => 100, 'cap_unit_cents' => 100, 'tax_rate' => 0],
    ]))->toThrow(RuntimeException::class, 'positive whole quantity');
});

it('resolves catalog reference cents and falls back only to the same sku paid price', function () {
    expect(FvLineProration::referenceUnitCents(8000, true, 6))->toBe(133333)
        ->and(FvLineProration::referenceUnitCents(8000, false, 6))->toBe(800000)
        ->and(FvLineProration::resolveReferenceUnitCents(0, 5000))->toBe(5000)
        ->and(FvLineProration::resolveReferenceUnitCents(0, null))->toBe(0)
        ->and(FvLineProration::resolveReferenceUnitCents(0, 0))->toBe(0)
        ->and(FvLineProration::paidLineNetCents(10, 500000, 5))->toBe(4750000);
});
