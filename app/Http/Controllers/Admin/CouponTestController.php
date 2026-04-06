<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\CouponDiscountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Diagnostic and unit test module for coupon workflow.
 * Creates mock orders (not transmitted) and inspects generated XML.
 */
class CouponTestController extends Controller
{
    public function index()
    {
        $recentOrders = Order::with(['user', 'zone', 'coupon'])
            ->whereNotNull('zone_id')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'user_id', 'zone_id', 'total', 'coupon_id', 'coupon_code', 'created_at']);

        $coupons = Coupon::active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'value', 'applies_to']);

        return view('admin.coupon-tests.index', compact('recentOrders', 'coupons'));
    }

    /**
     * Preview XML for an existing order (no transmission).
     */
    public function previewOrderXml(Request $request)
    {
        $orderId = $request->query('order_id');
        if (!$orderId) {
            return redirect()->route('coupon-tests.index')->with('error', 'Selecciona una orden.');
        }
        $order = Order::find($orderId);
        if (!$order) {
            return redirect()->route('coupon-tests.index')->with('error', 'Orden no encontrada.');
        }
        $order->load(['products.product', 'user', 'zone', 'coupon']);

        $xml = OrderRepository::buildOrderXmlForDiagnostic($order);

        if (!$xml) {
            return redirect()->route('coupon-tests.index')->with('error', 'La orden no tiene zona asignada o no se pudo generar el XML.');
        }

        $productSummary = $order->products->map(fn ($op) => [
            'product_id' => $op->product_id,
            'name' => $op->product?->name ?? 'N/A',
            'quantity' => $op->quantity,
            'price' => $op->price,
            'percentage' => $op->percentage,
            'discount_type' => $op->discount_type ?? 'percentage',
            'flat_discount_amount' => $op->flat_discount_amount ?? 0,
        ]);

        return view('admin.coupon-tests.preview-xml', [
            'order' => $order,
            'xml' => $xml,
            'productSummary' => $productSummary,
        ]);
    }

    /**
     * Show form to run a mock coupon test.
     */
    public function showMockForm()
    {
        $users = User::whereHas('zones')->orderBy('name')->get(['id', 'name', 'document']);
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'price']);
        $coupons = Coupon::active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'value']);

        return view('admin.coupon-tests.mock-form', compact('users', 'products', 'coupons'));
    }

    /**
     * Run a mock coupon test: build mock order with selected products + coupons, generate XML.
     * Order is NOT saved or transmitted.
     */
    public function runMockTest(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.variation_id' => 'nullable|integer',
            'coupon_codes_text' => 'nullable|string',
        ]);

        $validated['coupon_codes'] = $this->parseCouponCodes($validated['coupon_codes_text'] ?? '');

        $user = User::with('zones')->findOrFail($validated['user_id']);
        $zone = $user->zones()->first();
        if (!$zone) {
            return back()->with('error', 'El usuario no tiene zonas asignadas. Asigna una zona para poder generar el XML.');
        }

        // Build cart array (same structure as session cart)
        $cart = [];
        foreach ($validated['products'] as $row) {
            $cart[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
                'variation_id' => $row['variation_id'] ?? null,
            ];
        }

        $diagnostic = $this->buildMockDiagnostic(
            $user,
            $zone,
            $cart,
            $validated['coupon_codes'] ?? [],
            false
        );

        return view('admin.coupon-tests.preview-xml', [
            'order' => $diagnostic['order'],
            'xml' => $diagnostic['xml'],
            'productSummary' => $diagnostic['productSummary'],
            'isMockTest' => true,
            'couponResult' => $diagnostic['couponResult'],
            'assertions' => $diagnostic['assertions'],
        ]);
    }

    public function showScenarioSuiteForm()
    {
        $users = User::whereHas('zones')->orderBy('name')->get(['id', 'name', 'document']);
        $products = Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sku', 'price', 'brand_id']);
        $coupons = Coupon::active()->orderBy('code')->get(['id', 'code', 'name', 'type', 'applies_to', 'applies_to_ids']);
        $seededScenarios = $this->buildSeededScenarios($products, $coupons);
        $seededScenariosJson = json_encode($seededScenarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return view('admin.coupon-tests.suite-form', compact('users', 'products', 'coupons', 'seededScenariosJson'));
    }

    public function runScenarioSuite(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'scenarios_json' => 'required|string',
        ]);

        $user = User::with('zones')->findOrFail((int) $validated['user_id']);
        $zone = $user->zones()->first();
        if (!$zone) {
            return back()->with('error', 'El usuario no tiene zonas asignadas.');
        }

        try {
            $scenarios = json_decode($validated['scenarios_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return back()->with('error', 'JSON inválido en escenarios: ' . $e->getMessage())->withInput();
        }

        if (!is_array($scenarios) || empty($scenarios)) {
            return back()->with('error', 'Define al menos un escenario.')->withInput();
        }

        $results = [];
        foreach ($scenarios as $index => $scenario) {
            $scenarioName = $scenario['name'] ?? ('Escenario #' . ($index + 1));
            $products = is_array($scenario['products'] ?? null) ? $scenario['products'] : [];
            $couponCodes = $scenario['coupon_codes'] ?? [];
            $couponCodes = is_array($couponCodes)
                ? array_values(array_filter(array_map('trim', $couponCodes)))
                : $this->parseCouponCodes((string) $couponCodes);

            if (empty($products)) {
                $results[] = [
                    'name' => $scenarioName,
                    'passed' => false,
                    'error' => 'Sin productos en el escenario.',
                    'assertions' => [],
                    'coupon_codes' => $couponCodes,
                ];
                continue;
            }

            $cart = collect($products)->map(function ($row) {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                    'variation_id' => $row['variation_id'] ?? null,
                ];
            })->values()->all();

            $diagnostic = $this->buildMockDiagnostic($user, $zone, $cart, $couponCodes, false);
            $failedAssertions = collect($diagnostic['assertions'])->where('passed', false)->count();

            $results[] = [
                'name' => $scenarioName,
                'passed' => $failedAssertions === 0,
                'coupon_codes' => $couponCodes,
                'coupon_total_discount' => (float) (($diagnostic['couponResult']['total_coupon_discount'] ?? 0)),
                'assertions' => $diagnostic['assertions'],
                'product_summary' => $diagnostic['productSummary']->toArray(),
                'xml' => $diagnostic['xml'],
            ];
        }

        $summary = [
            'total' => count($results),
            'passed' => collect($results)->where('passed', true)->count(),
            'failed' => collect($results)->where('passed', false)->count(),
            'ran_at' => now()->toDateTimeString(),
            'actor' => auth()->user()->email ?? auth()->id(),
            'transmission' => 'diagnostic_only',
        ];

        $payload = [
            'summary' => $summary,
            'results' => $results,
        ];
        $runId = $this->persistScenarioSuitePayload($payload);
        session()->put('coupon_test_suite_last_run_id', $runId);

        return view('admin.coupon-tests.suite-results', [
            'summary' => $summary,
            'results' => $results,
        ]);
    }

    public function exportScenarioSuite(Request $request)
    {
        $format = $request->query('format', 'json');
        $runId = (string) session('coupon_test_suite_last_run_id', '');
        $payload = $this->loadScenarioSuitePayload($runId);
        if (!$payload) {
            return back()->with('error', 'No hay resultados para exportar. Ejecuta primero una corrida.');
        }

        if ($format === 'csv') {
            $rows = ['scenario,passed,coupon_codes,coupon_total_discount,failed_assertions'];
            foreach ($payload['results'] as $result) {
                $failed = collect($result['assertions'] ?? [])->where('passed', false)->count();
                $rows[] = sprintf(
                    '"%s",%s,"%s",%s,%d',
                    str_replace('"', '""', (string) $result['name']),
                    $result['passed'] ? 'true' : 'false',
                    str_replace('"', '""', implode(',', $result['coupon_codes'] ?? [])),
                    number_format((float) ($result['coupon_total_discount'] ?? 0), 4, '.', ''),
                    $failed
                );
            }
            return Response::make(implode("\n", $rows), 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="coupon-suite-results.csv"',
            ]);
        }

        return Response::make(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="coupon-suite-results.json"',
            ]
        );
    }

    private function persistScenarioSuitePayload(array $payload): string
    {
        $runId = now()->format('YmdHis') . '_' . Str::random(12);
        $relativePath = 'coupon-tests/suites/' . $runId . '.json';
        Storage::disk('local')->put($relativePath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $runId;
    }

    private function loadScenarioSuitePayload(string $runId): ?array
    {
        if ($runId === '') {
            return null;
        }

        $relativePath = 'coupon-tests/suites/' . $runId . '.json';
        if (!Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        $contents = Storage::disk('local')->get($relativePath);
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function parseCouponCodes(string $couponCodesText): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $couponCodesText))));
    }

    private function buildMockDiagnostic(User $user, $zone, array $cart, array $couponCodes, bool $includeBonifications = false): array
    {
        $hasOrders = $user->orders()->exists();
        $modifiedProductsLookup = [];
        $couponResult = ['success' => true, 'total_coupon_discount' => 0, 'modified_products' => []];

        if (!empty($couponCodes)) {
            $coupons = Coupon::whereIn('code', $couponCodes)->get();
            $couponDiscountService = app(CouponDiscountService::class);
            $couponResult = $couponDiscountService->applyMultipleCouponsToProducts(
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

    private function buildXmlAssertions(Collection $orderProducts, string $xml): array
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

    private function buildSeededScenarios(Collection $products, Collection $coupons): array
    {
        $firstProductId = (int) ($products->first()->id ?? 0);
        $secondProductId = (int) ($products->skip(1)->first()->id ?? $firstProductId);
        $thirdProductId = (int) ($products->skip(2)->first()->id ?? $secondProductId);

        $scenarios = [];

        // Baseline (without coupon) for XML and discount control comparison.
        $scenarios[] = [
            'name' => 'BASELINE - Sin cupon',
            'coupon_codes' => [],
            'products' => [
                ['product_id' => $firstProductId, 'quantity' => 1],
                ['product_id' => $secondProductId, 'quantity' => 1],
            ],
        ];

        // One scenario per active coupon.
        foreach ($coupons as $coupon) {
            $scenarios[] = [
                'name' => sprintf(
                    'SINGLE - %s (%s, %s)',
                    (string) $coupon->code,
                    (string) $coupon->type,
                    (string) $coupon->applies_to
                ),
                'coupon_codes' => [(string) $coupon->code],
                'products' => $this->seedProductsForCoupon($coupon, $products, $firstProductId, $secondProductId),
            ];
        }

        // Pair combinations (best-per-line and stacking behavior checks).
        $codes = $coupons->pluck('code')->filter()->values()->all();
        for ($i = 0; $i < count($codes); $i++) {
            for ($j = $i + 1; $j < count($codes); $j++) {
                $scenarios[] = [
                    'name' => "PAIR - {$codes[$i]} + {$codes[$j]}",
                    'coupon_codes' => [$codes[$i], $codes[$j]],
                    'products' => [
                        ['product_id' => $firstProductId, 'quantity' => 2],
                        ['product_id' => $secondProductId, 'quantity' => 1],
                    ],
                ];
            }
        }

        // Triple combinations (stacking of several coupons in one run).
        for ($i = 0; $i < count($codes); $i++) {
            for ($j = $i + 1; $j < count($codes); $j++) {
                for ($k = $j + 1; $k < count($codes); $k++) {
                    $scenarios[] = [
                        'name' => "TRIPLE - {$codes[$i]} + {$codes[$j]} + {$codes[$k]}",
                        'coupon_codes' => [$codes[$i], $codes[$j], $codes[$k]],
                        'products' => [
                            ['product_id' => $firstProductId, 'quantity' => 2],
                            ['product_id' => $secondProductId, 'quantity' => 1],
                            ['product_id' => $thirdProductId, 'quantity' => 1],
                        ],
                    ];
                }
            }
        }

        // Explicit edge/use-case templates to cover known risk paths.
        $fixedCodes = $coupons->where('type', Coupon::TYPE_FIXED_AMOUNT)->pluck('code')->filter()->values()->all();
        $percentageCodes = $coupons->where('type', Coupon::TYPE_PERCENTAGE)->pluck('code')->filter()->values()->all();

        if (!empty($fixedCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Fijo distribuido proporcionalmente (2 lineas)',
                'coupon_codes' => [$fixedCodes[0]],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 3],
                    ['product_id' => $secondProductId, 'quantity' => 1],
                ],
            ];
        }

        if (!empty($fixedCodes) && !empty($percentageCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Competencia porcentaje vs fijo',
                'coupon_codes' => [$percentageCodes[0], $fixedCodes[0]],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 1],
                ],
            ];
        }

        $packageProduct = Product::where('active', true)
            ->where('calculate_package_price', true)
            ->first(['id']);
        if ($packageProduct && !empty($percentageCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Producto con calculate_package_price',
                'coupon_codes' => [$percentageCodes[0]],
                'products' => [
                    ['product_id' => (int) $packageProduct->id, 'quantity' => 1],
                ],
            ];
        }

        if (!empty($fixedCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Monto fijo alto (validar piso unitPrice 10%)',
                'coupon_codes' => [$fixedCodes[0]],
                'products' => [
                    ['product_id' => $thirdProductId, 'quantity' => 1],
                ],
            ];
        }

        // Remove scenarios with invalid product placeholders.
        return collect($scenarios)
            ->map(function ($scenario) {
                $scenario['products'] = collect($scenario['products'] ?? [])
                    ->filter(fn ($row) => (int) ($row['product_id'] ?? 0) > 0)
                    ->values()
                    ->all();
                return $scenario;
            })
            ->filter(fn ($scenario) => !empty($scenario['products']))
            ->values()
            ->all();
    }

    private function seedProductsForCoupon(Coupon $coupon, Collection $products, int $fallbackA, int $fallbackB): array
    {
        $appliesIds = collect($coupon->applies_to_ids ?? [])->map(fn ($v) => (int) $v)->filter()->values();
        $matchingId = null;

        switch ((string) $coupon->applies_to) {
            case Coupon::APPLIES_TO_PRODUCT:
                $matchingId = (int) ($appliesIds->first() ?? 0);
                break;
            case Coupon::APPLIES_TO_BRAND:
                $brandId = (int) ($appliesIds->first() ?? 0);
                if ($brandId > 0) {
                    $matchingId = (int) (Product::where('active', true)->where('brand_id', $brandId)->value('id') ?? 0);
                }
                break;
            case Coupon::APPLIES_TO_CATEGORY:
                $categoryId = (int) ($appliesIds->first() ?? 0);
                if ($categoryId > 0) {
                    $matchingId = (int) (Product::where('active', true)->whereHas('categories', function ($q) use ($categoryId) {
                        $q->where('categories.id', $categoryId);
                    })->value('id') ?? 0);
                }
                break;
            case Coupon::APPLIES_TO_VENDOR:
                $vendorId = (int) ($appliesIds->first() ?? 0);
                if ($vendorId > 0) {
                    $matchingId = (int) (Product::where('active', true)->whereHas('brand.vendor', function ($q) use ($vendorId) {
                        $q->where('vendors.id', $vendorId);
                    })->value('id') ?? 0);
                }
                break;
            default:
                $matchingId = $fallbackA;
                break;
        }

        $first = $matchingId > 0 ? $matchingId : $fallbackA;
        $second = $products->firstWhere('id', '!=', $first)->id ?? $fallbackB;

        return [
            ['product_id' => (int) $first, 'quantity' => 1],
            ['product_id' => (int) $second, 'quantity' => 1],
        ];
    }
}
