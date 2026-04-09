<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CouponTestDiagnosticService
{
    public function __construct(
        private CouponDiscountService $couponDiscountService,
    ) {}

    public function buildMockDiagnostic(User $user, $zone, array $cart, array $couponCodes, bool $includeBonifications = false): array
    {
        $hasOrders = $user->orders()->exists();
        $modifiedProductsLookup = [];
        $couponResult = ['success' => true, 'total_coupon_discount' => 0, 'modified_products' => []];

        if (!empty($couponCodes)) {
            $coupons = Coupon::whereIn('code', $couponCodes)->get();
            $couponResult = $this->couponDiscountService->applyMultipleCouponsToProducts(
                $coupons->all(),
                $user,
                collect($cart),
                $hasOrders
            );
            if ($couponResult['success']) {
                foreach ($couponResult['modified_products'] ?? [] as $modProduct) {
                    $key = $modProduct['product_id'] . '_' . ($modProduct['variation_id'] ?? 'null');
                    $modifiedProductsLookup[$key] = $modProduct;
                }
            }
        }

        $order = new Order([
            'id' => 0,
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'total' => 0,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'observations' => '[TEST] Mock order - not transmitted',
            'created_at' => now(),
        ]);
        $order->id = 0;
        $order->setRelation('zone', $zone);
        $order->setRelation('user', $user);

        $orderProducts = collect();
        $totalOrder = 0;

        foreach ($cart as $row) {
            $product = Product::with(['brand.vendor', 'items'])->find($row['product_id']);
            if (!$product) {
                continue;
            }

            $lookupKey = $row['product_id'] . '_' . ($row['variation_id'] ?? 'null');
            $basePrice = $product->price;
            $variation = $row['variation_id']
                ? $product->items()->where('variation_items.id', $row['variation_id'])->first()
                : null;
            if ($variation && $variation->pivot) {
                $basePrice = $variation->pivot->price;
            }

            $lineDiscountPercent = 0;
            $orderDiscountType = 'percentage';
            $flatDiscountAmount = 0;
            $unitPrice = 0;

            if (isset($modifiedProductsLookup[$lookupKey])) {
                $modProduct = $modifiedProductsLookup[$lookupKey];
                $discountType = $modProduct['applied_discount_type'] ?? 'percentage';
                $basePrice = $modProduct['base_price'];
                if ($discountType === 'fixed_amount') {
                    $unitPrice = $product->calculate_package_price
                        ? $basePrice * ($product->package_quantity ?? 1)
                        : $basePrice;
                    $lineDiscountPercent = 0;
                    $orderDiscountType = 'fixed_amount';
                    $flatDiscountAmount = (float) ($modProduct['fixed_discount_per_unit']
                        ?? $modProduct['unit_price_reduction']
                        ?? 0);
                } else {
                    $unitPrice = $product->calculate_package_price
                        ? $basePrice * ($product->package_quantity ?? 1)
                        : $basePrice;
                    $lineDiscountPercent = (int) round((float) ($modProduct['effective_discount_percentage']
                        ?? $modProduct['applied_discount_percentage']
                        ?? 0));
                }
            } else {
                $vendorId = $product->brand && $product->brand->vendor ? $product->brand->vendor->id : null;
                $vendorTotal = $vendorId ? 0 : null;
                $lineFinal = $product->getFinalPriceForUser($hasOrders, $vendorTotal);
                $lineDiscountPercent = max(0, min(100, (int) ($lineFinal['discount'] ?? 0)));
                $unitPrice = $product->calculate_package_price
                    ? ($lineFinal['originalPrice'] ?? ($basePrice * ($product->package_quantity ?? 1)))
                    : ($lineFinal['price'] ?? $basePrice);
                if ($variation && isset($variation->pivot->price)) {
                    $unitPrice = $product->calculate_package_price
                        ? $variation->pivot->price * ($product->package_quantity ?? 1)
                        : $variation->pivot->price;
                }
            }

            $lineTotal = $unitPrice * (int) $row['quantity'];
            $op = new OrderProduct([
                'order_id' => 0,
                'product_id' => $product->id,
                'quantity' => (int) $row['quantity'],
                'price' => $unitPrice,
                'percentage' => $lineDiscountPercent,
                'discount_type' => $orderDiscountType,
                'flat_discount_amount' => $flatDiscountAmount,
                'variation_item_id' => $row['variation_id'] ?? null,
                'package_quantity' => (int) ($product->package_quantity ?? 1),
            ]);
            $op->setRelation('product', $product);
            $orderProducts->push($op);
            $totalOrder += $lineTotal;
        }

        $order->total = $totalOrder;
        $order->setRelation('products', $orderProducts);

        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, $includeBonifications, $orderProducts) ?? '<!-- No zone -->';
        $assertions = $this->buildXmlAssertions($orderProducts, $xml);

        $productSummary = $orderProducts->map(fn ($op) => [
            'product_id' => $op->product_id,
            'name' => $op->product?->name ?? 'N/A',
            'sku' => $op->product?->sku ?? 'N/A',
            'quantity' => $op->quantity,
            'price' => $op->price,
            'percentage' => $op->percentage,
            'discount_type' => $op->discount_type ?? 'percentage',
            'flat_discount_amount' => $op->flat_discount_amount ?? 0,
        ]);

        return [
            'order' => $order,
            'xml' => $xml,
            'couponResult' => $couponResult,
            'assertions' => $assertions,
            'productSummary' => $productSummary,
        ];
    }

    public function buildXmlAssertions(Collection $orderProducts, string $xml): array
    {
        preg_match_all(
            '/<dyn:listDetails>\s*<dyn:discount>(.*?)<\/dyn:discount>.*?<dyn:itemId>(.*?)<\/dyn:itemId>.*?<dyn:qty>.*?<\/dyn:qty>.*?<dyn:unitPrice>(.*?)<\/dyn:unitPrice>.*?<\/dyn:listDetails>/s',
            $xml,
            $matches,
            PREG_SET_ORDER
        );

        $xmlBySku = [];
        foreach ($matches as $match) {
            $sku = trim($match[2]);
            if (!isset($xmlBySku[$sku])) {
                $xmlBySku[$sku] = [];
            }
            $xmlBySku[$sku][] = [
                'discount' => (int) trim($match[1]),
                'unit_price' => (float) trim($match[3]),
            ];
        }

        $variationSkuMap = [];
        $variationRows = $orderProducts
            ->filter(fn ($op) => !empty($op->variation_item_id))
            ->map(fn ($op) => ['product_id' => (int) $op->product_id, 'variation_item_id' => (int) $op->variation_item_id])
            ->unique()
            ->values()
            ->all();
        if (!empty($variationRows)) {
            $variationSkuData = DB::table('product_item_variation')
                ->whereIn('product_id', collect($variationRows)->pluck('product_id')->all())
                ->whereIn('variation_item_id', collect($variationRows)->pluck('variation_item_id')->all())
                ->select('product_id', 'variation_item_id', 'sku')
                ->get();
            foreach ($variationSkuData as $row) {
                $variationSkuMap[$row->product_id . '_' . $row->variation_item_id] = (string) $row->sku;
            }
        }

        $assertions = [];
        foreach ($orderProducts as $op) {
            $sku = (string) ($op->product?->sku ?? '');
            if (!empty($op->variation_item_id)) {
                $sku = $variationSkuMap[$op->product_id . '_' . $op->variation_item_id] ?? $sku;
            }

            if ($sku === '' || empty($xmlBySku[$sku])) {
                $assertions[] = [
                    'passed' => false,
                    'message' => "SKU {$sku} no encontrado en XML.",
                ];
                continue;
            }

            $row = array_shift($xmlBySku[$sku]);
            $expectedDiscount = ($op->discount_type === 'fixed_amount')
                ? 0
                : max(0, min(100, (int) ($op->percentage ?? 0)));

            $packageQty = max(1, (int) ($op->package_quantity ?? 1));
            $baseUnitPrice = $op->product?->calculate_package_price ? ((float) $op->price / $packageQty) : (float) $op->price;
            if ($op->discount_type === 'fixed_amount' && (float) $op->flat_discount_amount > 0) {
                $minAllowed = $baseUnitPrice * 0.1;
                $expectedUnitPrice = max($minAllowed, $baseUnitPrice - (float) $op->flat_discount_amount);
            } else {
                $expectedUnitPrice = $baseUnitPrice;
            }

            $discountOk = ((int) $row['discount']) === (int) $expectedDiscount;
            $priceOk = abs(((float) $row['unit_price']) - ((float) $expectedUnitPrice)) < 0.01;
            $assertions[] = [
                'passed' => $discountOk && $priceOk,
                'message' => "{$sku}: discount={$row['discount']} (esp {$expectedDiscount}), unitPrice={$row['unit_price']} (esp " . number_format($expectedUnitPrice, 2, '.', '') . ')',
            ];
        }

        return $assertions;
    }
}
