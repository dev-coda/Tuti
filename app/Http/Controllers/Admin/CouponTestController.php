<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunCouponTestSuite;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\OrderRepository;
use App\Services\CouponTestDiagnosticService;
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

        $diagnosticService = app(CouponTestDiagnosticService::class);
        $diagnostic = $diagnosticService->buildMockDiagnostic(
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

        $runId = now()->format('YmdHis') . '_' . Str::random(12);
        $actorEmail = auth()->user()->email ?? (string) auth()->id();

        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'redis';
        }

        RunCouponTestSuite::dispatch($user->id, $scenarios, $runId, $actorEmail)
            ->onConnection($queueConnection)
            ->onQueue('coupon-tests');

        session()->put('coupon_test_suite_last_run_id', $runId);

        return redirect()->route('coupon-tests.suite.progress', ['run_id' => $runId]);
    }

    public function suiteProgress(Request $request)
    {
        $runId = $this->resolveRunId($request);
        if ($runId === '') {
            return redirect()->route('coupon-tests.suite')->with('error', 'No hay corrida en progreso.');
        }

        return view('admin.coupon-tests.suite-progress', compact('runId'));
    }

    public function suiteStatus(Request $request)
    {
        $runId = $this->resolveRunId($request);
        if ($runId === '') {
            return response()->json(['status' => 'not_found']);
        }

        $statusPath = "coupon-tests/suites/{$runId}/status.json";
        if (!Storage::disk('local')->exists($statusPath)) {
            return response()->json(['status' => 'pending', 'processed' => 0, 'total' => 0, 'percent' => 0]);
        }

        $status = json_decode(Storage::disk('local')->get($statusPath), true);
        return response()->json($status ?? ['status' => 'pending']);
    }

    public function suiteResults(Request $request)
    {
        $runId = $this->resolveRunId($request);
        if ($runId === '') {
            return redirect()->route('coupon-tests.suite')->with('error', 'No hay resultados para mostrar.');
        }

        $summaryPath = "coupon-tests/suites/{$runId}/summary.json";
        if (!Storage::disk('local')->exists($summaryPath)) {
            return redirect()->route('coupon-tests.suite.progress', ['run_id' => $runId]);
        }

        $summary = json_decode(Storage::disk('local')->get($summaryPath), true);
        $total = $summary['total'] ?? 0;

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $results = [];
        for ($i = $offset; $i < min($offset + $perPage, $total); $i++) {
            $resultPath = "coupon-tests/suites/{$runId}/result-{$i}.json";
            if (Storage::disk('local')->exists($resultPath)) {
                $results[] = json_decode(Storage::disk('local')->get($resultPath), true);
            }
        }

        $totalPages = (int) ceil($total / $perPage);

        return view('admin.coupon-tests.suite-results', [
            'summary' => $summary,
            'results' => $results,
            'runId' => $runId,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
        ]);
    }

    public function exportScenarioSuite(Request $request)
    {
        $format = $request->query('format', 'json');
        $runId = $this->resolveRunId($request);

        if ($runId === '') {
            return back()->with('error', 'No hay resultados para exportar. Ejecuta primero una corrida.');
        }

        $summaryPath = "coupon-tests/suites/{$runId}/summary.json";
        if (!Storage::disk('local')->exists($summaryPath)) {
            return back()->with('error', 'No hay resultados para exportar. Ejecuta primero una corrida.');
        }

        $summary = json_decode(Storage::disk('local')->get($summaryPath), true);
        $total = $summary['total'] ?? 0;

        if ($format === 'csv') {
            $rows = ['scenario,passed,coupon_codes,coupon_total_discount,failed_assertions'];
            for ($i = 0; $i < $total; $i++) {
                $resultPath = "coupon-tests/suites/{$runId}/result-{$i}.json";
                if (!Storage::disk('local')->exists($resultPath)) {
                    continue;
                }
                $result = json_decode(Storage::disk('local')->get($resultPath), true);
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

        return response()->stream(function () use ($summary, $runId, $total) {
            echo '{"summary":' . json_encode($summary, JSON_UNESCAPED_UNICODE) . ',"results":[';
            $first = true;
            for ($i = 0; $i < $total; $i++) {
                $resultPath = "coupon-tests/suites/{$runId}/result-{$i}.json";
                if (!Storage::disk('local')->exists($resultPath)) {
                    continue;
                }
                if (!$first) {
                    echo ',';
                }
                $first = false;
                echo Storage::disk('local')->get($resultPath);
                flush();
            }
            echo ']}';
        }, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="coupon-suite-results.json"',
        ]);
    }

    private function resolveRunId(Request $request): string
    {
        $runId = $request->query('run_id', (string) session('coupon_test_suite_last_run_id', ''));

        if ($runId !== '' && !preg_match('/^[\w-]+$/', $runId)) {
            return '';
        }

        return $runId;
    }

    private function parseCouponCodes(string $couponCodesText): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $couponCodesText))));
    }

    private function buildSeededScenarios(Collection $products, Collection $coupons): array
    {
        $firstProductId = (int) ($products->first()->id ?? 0);
        $secondProductId = (int) ($products->skip(1)->first()->id ?? $firstProductId);
        $thirdProductId = (int) ($products->skip(2)->first()->id ?? $secondProductId);

        $scenarios = [];

        // ── BASELINES (coupon-independent, always generated) ───────────────

        $scenarios[] = [
            'name' => 'BASELINE - Sin cupon',
            'coupon_codes' => [],
            'products' => [
                ['product_id' => $firstProductId, 'quantity' => 1],
                ['product_id' => $secondProductId, 'quantity' => 1],
            ],
        ];

        $scenarios[] = [
            'name' => 'BASELINE - Cantidad alta sin cupon',
            'coupon_codes' => [],
            'products' => [
                ['product_id' => $firstProductId, 'quantity' => 5],
            ],
        ];

        $scenarios[] = [
            'name' => 'BASELINE - Tres productos cantidades variadas',
            'coupon_codes' => [],
            'products' => [
                ['product_id' => $firstProductId, 'quantity' => 3],
                ['product_id' => $secondProductId, 'quantity' => 1],
                ['product_id' => $thirdProductId, 'quantity' => 2],
            ],
        ];

        $variationProduct = Product::where('active', true)
            ->whereHas('items')
            ->with('items')
            ->first();
        if ($variationProduct) {
            $variationItem = $variationProduct->items->first();
            $scenarios[] = [
                'name' => 'BASELINE - Producto con variacion',
                'coupon_codes' => [],
                'products' => [
                    ['product_id' => (int) $variationProduct->id, 'quantity' => 1, 'variation_id' => $variationItem ? (int) $variationItem->id : null],
                    ['product_id' => $firstProductId, 'quantity' => 1],
                ],
            ];
        }

        $packageProduct = Product::where('active', true)
            ->where('calculate_package_price', true)
            ->first();
        if ($packageProduct) {
            $scenarios[] = [
                'name' => 'BASELINE - Producto package_price sin cupon',
                'coupon_codes' => [],
                'products' => [
                    ['product_id' => (int) $packageProduct->id, 'quantity' => 2],
                    ['product_id' => $firstProductId, 'quantity' => 1],
                ],
            ];
        }

        // ── SINGLES (one per active coupon, smart product matching) ────────

        $couponSeedProducts = [];
        foreach ($coupons as $coupon) {
            $seedProducts = $this->seedProductsForCoupon($coupon, $products, $firstProductId, $secondProductId);
            $couponSeedProducts[$coupon->code] = $seedProducts;

            $scenarios[] = [
                'name' => sprintf(
                    'SINGLE - %s (%s, %s)',
                    (string) $coupon->code,
                    (string) $coupon->type,
                    (string) $coupon->applies_to
                ),
                'coupon_codes' => [(string) $coupon->code],
                'products' => $seedProducts,
            ];
        }

        // ── PAIRS (merged products from both coupons for realistic carts) ──

        $codes = $coupons->pluck('code')->filter()->values()->all();
        $maxPairs = 50;
        $pairCount = 0;
        for ($i = 0; $i < count($codes) && $pairCount < $maxPairs; $i++) {
            for ($j = $i + 1; $j < count($codes) && $pairCount < $maxPairs; $j++) {
                $scenarios[] = [
                    'name' => "PAIR - {$codes[$i]} + {$codes[$j]}",
                    'coupon_codes' => [$codes[$i], $codes[$j]],
                    'products' => $this->mergeProductSets(
                        $couponSeedProducts[$codes[$i]] ?? [],
                        $couponSeedProducts[$codes[$j]] ?? []
                    ),
                ];
                $pairCount++;
            }
        }

        // ── TRIPLES ────────────────────────────────────────────────────────

        $maxTriples = 20;
        $tripleCount = 0;
        for ($i = 0; $i < count($codes) && $tripleCount < $maxTriples; $i++) {
            for ($j = $i + 1; $j < count($codes) && $tripleCount < $maxTriples; $j++) {
                for ($k = $j + 1; $k < count($codes) && $tripleCount < $maxTriples; $k++) {
                    $scenarios[] = [
                        'name' => "TRIPLE - {$codes[$i]} + {$codes[$j]} + {$codes[$k]}",
                        'coupon_codes' => [$codes[$i], $codes[$j], $codes[$k]],
                        'products' => $this->mergeProductSets(
                            $couponSeedProducts[$codes[$i]] ?? [],
                            $couponSeedProducts[$codes[$j]] ?? [],
                            $couponSeedProducts[$codes[$k]] ?? []
                        ),
                    ];
                    $tripleCount++;
                }
            }
        }

        // ── EDGE CASES ─────────────────────────────────────────────────────

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

        if ($packageProduct && !empty($percentageCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Producto con calculate_package_price + cupon',
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

        // Cart-scoped coupon with multi-line cart.
        $cartCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_CART);
        if ($cartCoupons->isNotEmpty()) {
            $cartCoupon = $cartCoupons->first();
            $scenarios[] = [
                'name' => sprintf('EDGE - Cupon de carrito con 3 lineas (%s)', $cartCoupon->code),
                'coupon_codes' => [(string) $cartCoupon->code],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 2],
                    ['product_id' => $secondProductId, 'quantity' => 1],
                    ['product_id' => $thirdProductId, 'quantity' => 3],
                ],
            ];
        }

        // High quantity + percentage coupon (per-unit math).
        if (!empty($percentageCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Cantidad alta + cupon porcentaje',
                'coupon_codes' => [$percentageCodes[0]],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 10],
                ],
            ];
        }

        // High quantity + fixed amount coupon (proportional distribution per unit).
        if (!empty($fixedCodes)) {
            $scenarios[] = [
                'name' => 'EDGE - Cantidad alta + cupon fijo',
                'coupon_codes' => [$fixedCodes[0]],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 10],
                ],
            ];
        }

        // Brand coupon with non-matching product in cart (mixed applicability).
        $brandCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_BRAND);
        if ($brandCoupons->isNotEmpty()) {
            $brandCoupon = $brandCoupons->first();
            $seedProds = $couponSeedProducts[$brandCoupon->code] ?? [];
            $matchingId = !empty($seedProds) ? (int) $seedProds[0]['product_id'] : $firstProductId;
            $brandId = (int) (collect($brandCoupon->applies_to_ids ?? [])->first() ?? 0);
            $nonMatchingProduct = $brandId > 0
                ? Product::where('active', true)->where(fn ($q) => $q->where('brand_id', '!=', $brandId)->orWhereNull('brand_id'))->first(['id'])
                : null;
            $nonMatchingId = $nonMatchingProduct ? (int) $nonMatchingProduct->id : $secondProductId;
            $scenarios[] = [
                'name' => sprintf('EDGE - Marca cupon + producto no-aplicable (%s)', $brandCoupon->code),
                'coupon_codes' => [(string) $brandCoupon->code],
                'products' => [
                    ['product_id' => $matchingId, 'quantity' => 1],
                    ['product_id' => $nonMatchingId, 'quantity' => 1],
                ],
            ];
        }

        // Category coupon with non-matching product.
        $categoryCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_CATEGORY);
        if ($categoryCoupons->isNotEmpty()) {
            $catCoupon = $categoryCoupons->first();
            $seedProds = $couponSeedProducts[$catCoupon->code] ?? [];
            $matchingId = !empty($seedProds) ? (int) $seedProds[0]['product_id'] : $firstProductId;
            $categoryId = (int) (collect($catCoupon->applies_to_ids ?? [])->first() ?? 0);
            $nonMatchCat = $categoryId > 0
                ? Product::where('active', true)->whereDoesntHave('categories', fn ($q) => $q->where('categories.id', $categoryId))->first(['id'])
                : null;
            $nonMatchCatId = $nonMatchCat ? (int) $nonMatchCat->id : $secondProductId;
            $scenarios[] = [
                'name' => sprintf('EDGE - Categoria cupon + producto no-aplicable (%s)', $catCoupon->code),
                'coupon_codes' => [(string) $catCoupon->code],
                'products' => [
                    ['product_id' => $matchingId, 'quantity' => 1],
                    ['product_id' => $nonMatchCatId, 'quantity' => 1],
                ],
            ];
        }

        // Variation product + coupon.
        if ($variationProduct && !empty($percentageCodes)) {
            $variationItem = $variationProduct->items->first();
            $scenarios[] = [
                'name' => 'EDGE - Variacion + cupon porcentaje',
                'coupon_codes' => [$percentageCodes[0]],
                'products' => [
                    ['product_id' => (int) $variationProduct->id, 'quantity' => 1, 'variation_id' => $variationItem ? (int) $variationItem->id : null],
                    ['product_id' => $firstProductId, 'quantity' => 1],
                ],
            ];
        }

        // Customer-scoped coupon.
        $customerCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_CUSTOMER);
        if ($customerCoupons->isNotEmpty()) {
            $custCoupon = $customerCoupons->first();
            $scenarios[] = [
                'name' => sprintf('EDGE - Cupon por cliente (%s)', $custCoupon->code),
                'coupon_codes' => [(string) $custCoupon->code],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 2],
                    ['product_id' => $secondProductId, 'quantity' => 1],
                ],
            ];
        }

        // Customer-type-scoped coupon.
        $customerTypeCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_CUSTOMER_TYPE);
        if ($customerTypeCoupons->isNotEmpty()) {
            $ctCoupon = $customerTypeCoupons->first();
            $scenarios[] = [
                'name' => sprintf('EDGE - Cupon por tipo de cliente (%s)', $ctCoupon->code),
                'coupon_codes' => [(string) $ctCoupon->code],
                'products' => [
                    ['product_id' => $firstProductId, 'quantity' => 2],
                    ['product_id' => $secondProductId, 'quantity' => 1],
                ],
            ];
        }

        // Vendor coupon with non-matching product.
        $vendorCoupons = $coupons->where('applies_to', Coupon::APPLIES_TO_VENDOR);
        if ($vendorCoupons->isNotEmpty()) {
            $vendorCoupon = $vendorCoupons->first();
            $seedProds = $couponSeedProducts[$vendorCoupon->code] ?? [];
            $matchingId = !empty($seedProds) ? (int) $seedProds[0]['product_id'] : $firstProductId;
            $vendorId = (int) (collect($vendorCoupon->applies_to_ids ?? [])->first() ?? 0);
            $nonMatchVendor = $vendorId > 0
                ? Product::where('active', true)->whereHas('brand.vendor', fn ($q) => $q->where('vendors.id', '!=', $vendorId))->first(['id'])
                : null;
            $nonMatchVendorId = $nonMatchVendor ? (int) $nonMatchVendor->id : $secondProductId;
            $scenarios[] = [
                'name' => sprintf('EDGE - Vendor cupon + producto no-aplicable (%s)', $vendorCoupon->code),
                'coupon_codes' => [(string) $vendorCoupon->code],
                'products' => [
                    ['product_id' => $matchingId, 'quantity' => 1],
                    ['product_id' => $nonMatchVendorId, 'quantity' => 1],
                ],
            ];
        }

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

    private function mergeProductSets(array ...$sets): array
    {
        $merged = [];
        foreach ($sets as $set) {
            foreach ($set as $row) {
                $pid = (int) ($row['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($merged[$pid])) {
                    $merged[$pid] = $row;
                } else {
                    $merged[$pid]['quantity'] = max(
                        (int) ($merged[$pid]['quantity'] ?? 1),
                        (int) ($row['quantity'] ?? 1)
                    );
                }
            }
        }
        return array_values(array_slice($merged, 0, 4));
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
