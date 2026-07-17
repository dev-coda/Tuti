<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\PackageType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Assigns tamaños de empaque (PackageType) to shipments based on the
 * products' synced dimensions and the total size of the order.
 *
 * Strategy: find active package types that can hold the largest single item,
 * then pick the smallest one whose usable volume and max weight cover the
 * whole order. If the order exceeds the largest package, ship multiples of it.
 */
class PackageAssignmentService
{
    /**
     * Boxes are never filled 100%; this factor discounts usable volume.
     */
    private const DEFAULT_FILL_FACTOR = 0.85;

    /**
     * @return array<int, array{package_type_id: int, code: string, name: string, count: int}>
     */
    public function assignForOrder(Order $order): array
    {
        $order->loadMissing('products.product');

        $items = collect($order->products)
            ->map(function ($orderProduct) {
                $product = $orderProduct->product;
                if (!$product) {
                    return null;
                }

                return [
                    'quantity' => (int) $orderProduct->quantity,
                    'weight_kg' => (float) ($product->coordinadora_weight_kg ?? 0),
                    'length_cm' => (float) ($product->coordinadora_length_cm ?? 0),
                    'width_cm' => (float) ($product->coordinadora_width_cm ?? 0),
                    'height_cm' => (float) ($product->coordinadora_height_cm ?? 0),
                ];
            })
            ->filter(fn ($item) => $item !== null && $item['quantity'] > 0)
            ->values();

        return $this->assignForItems($items);
    }

    /**
     * @param Collection<int, array{quantity: int, weight_kg: float, length_cm: float, width_cm: float, height_cm: float}> $items
     * @return array<int, array{package_type_id: int, code: string, name: string, count: int}>
     */
    public function assignForItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $packageTypes = PackageType::query()
            ->active()
            ->get()
            ->sortBy(fn (PackageType $type) => $type->volumeCm3())
            ->values();

        if ($packageTypes->isEmpty()) {
            Log::warning('Package assignment skipped - no active package types configured.');

            return [];
        }

        $totalWeight = $items->sum(fn (array $item) => $item['weight_kg'] * $item['quantity']);
        $totalVolume = $items->sum(
            fn (array $item) => $item['length_cm'] * $item['width_cm'] * $item['height_cm'] * $item['quantity']
        );

        // Every candidate must be able to hold the bulkiest single item.
        $candidates = $packageTypes->filter(function (PackageType $type) use ($items) {
            return $items->every(
                fn (array $item) => $type->fitsItem($item['length_cm'], $item['width_cm'], $item['height_cm'])
            );
        })->values();

        if ($candidates->isEmpty()) {
            // No box holds the largest item; ship it in the biggest box available.
            Log::warning('Package assignment: an item exceeds every package type; using the largest.', [
                'largest_package' => $packageTypes->last()->code,
            ]);
            $candidates = collect([$packageTypes->last()]);
        }

        $fillFactor = (float) config('services.coordinadora.package_fill_factor', self::DEFAULT_FILL_FACTOR);

        foreach ($candidates as $candidate) {
            $usableVolume = $candidate->volumeCm3() * $fillFactor;
            if ($totalVolume <= $usableVolume && $totalWeight <= (float) $candidate->max_weight_kg) {
                return [$this->formatAssignment($candidate, 1)];
            }
        }

        // Order does not fit in a single package: use multiples of the largest candidate.
        $largest = $candidates->last();
        $usableVolume = $largest->volumeCm3() * $fillFactor;
        $maxWeight = (float) $largest->max_weight_kg;

        $countByVolume = $usableVolume > 0 ? (int) ceil($totalVolume / $usableVolume) : 1;
        $countByWeight = $maxWeight > 0 ? (int) ceil($totalWeight / $maxWeight) : 1;
        $count = max(1, $countByVolume, $countByWeight);

        return [$this->formatAssignment($largest, $count)];
    }

    private function formatAssignment(PackageType $type, int $count): array
    {
        return [
            'package_type_id' => (int) $type->id,
            'code' => (string) $type->code,
            'name' => (string) $type->name,
            'count' => $count,
        ];
    }
}
