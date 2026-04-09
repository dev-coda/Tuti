<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CouponTestDiagnosticService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RunCouponTestSuite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'coupon-tests';
    public $tries = 1;
    public $timeout = 3600;

    protected int $userId;
    protected array $scenarios;
    protected string $runId;
    protected string $actorEmail;

    public function __construct(int $userId, array $scenarios, string $runId, string $actorEmail)
    {
        $this->userId = $userId;
        $this->scenarios = $scenarios;
        $this->runId = $runId;
        $this->actorEmail = $actorEmail;
    }

    public function handle(CouponTestDiagnosticService $diagnosticService): void
    {
        $user = User::with('zones')->findOrFail($this->userId);
        $zone = $user->zones()->first();

        if (!$zone) {
            $this->writeStatus('failed', 0, count($this->scenarios), 'El usuario no tiene zonas asignadas.');
            return;
        }

        $total = count($this->scenarios);
        $processed = 0;
        $passed = 0;
        $failed = 0;

        $this->writeStatus('running', 0, $total);

        $resultsDir = "coupon-tests/suites/{$this->runId}";
        Storage::disk('local')->makeDirectory($resultsDir);

        foreach ($this->scenarios as $index => $scenario) {
            $result = $this->processScenario($diagnosticService, $user, $zone, $scenario, $index);

            Storage::disk('local')->put(
                "{$resultsDir}/result-{$index}.json",
                json_encode($result, JSON_UNESCAPED_UNICODE)
            );

            $processed++;
            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
            }

            $this->writeStatus('running', $processed, $total, null, $passed, $failed);

            if ($processed % 5 === 0) {
                Log::info("Coupon test suite progress", [
                    'run_id' => $this->runId,
                    'processed' => $processed,
                    'total' => $total,
                ]);
            }
        }

        $summary = [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'ran_at' => now()->toDateTimeString(),
            'actor' => $this->actorEmail,
            'transmission' => 'diagnostic_only',
        ];

        Storage::disk('local')->put(
            "{$resultsDir}/summary.json",
            json_encode($summary, JSON_UNESCAPED_UNICODE)
        );

        $this->writeStatus('completed', $processed, $total, null, $passed, $failed);

        Log::info("Coupon test suite completed", [
            'run_id' => $this->runId,
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
        ]);
    }

    private function processScenario(CouponTestDiagnosticService $diagnosticService, User $user, $zone, array $scenario, int $index): array
    {
        $scenarioName = $scenario['name'] ?? ('Escenario #' . ($index + 1));
        $products = is_array($scenario['products'] ?? null) ? $scenario['products'] : [];
        $couponCodes = $scenario['coupon_codes'] ?? [];
        $couponCodes = is_array($couponCodes)
            ? array_values(array_filter(array_map('trim', $couponCodes)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $couponCodes))));

        if (empty($products)) {
            return [
                'name' => $scenarioName,
                'passed' => false,
                'error' => 'Sin productos en el escenario.',
                'assertions' => [],
                'coupon_codes' => $couponCodes,
            ];
        }

        $cart = collect($products)->map(function ($row) {
            return [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                'variation_id' => $row['variation_id'] ?? null,
            ];
        })->values()->all();

        try {
            $diagnostic = $diagnosticService->buildMockDiagnostic($user, $zone, $cart, $couponCodes);
            $failedAssertions = collect($diagnostic['assertions'])->where('passed', false)->count();

            return [
                'name' => $scenarioName,
                'passed' => $failedAssertions === 0,
                'coupon_codes' => $couponCodes,
                'coupon_total_discount' => (float) ($diagnostic['couponResult']['total_coupon_discount'] ?? 0),
                'assertions' => $diagnostic['assertions'],
                'product_summary' => $diagnostic['productSummary']->toArray(),
                'xml' => $diagnostic['xml'],
            ];
        } catch (\Throwable $e) {
            Log::error("Coupon test scenario failed", [
                'run_id' => $this->runId,
                'scenario' => $scenarioName,
                'error' => $e->getMessage(),
            ]);

            return [
                'name' => $scenarioName,
                'passed' => false,
                'error' => 'Error: ' . $e->getMessage(),
                'assertions' => [],
                'coupon_codes' => $couponCodes,
            ];
        }
    }

    private function writeStatus(string $status, int $processed, int $total, ?string $error = null, int $passed = 0, int $failed = 0): void
    {
        $data = [
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'percent' => $total > 0 ? round(($processed / $total) * 100) : 0,
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($error) {
            $data['error'] = $error;
        }

        Storage::disk('local')->put(
            "coupon-tests/suites/{$this->runId}/status.json",
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Coupon test suite job failed", [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->writeStatus('failed', 0, count($this->scenarios), $exception->getMessage());
    }
}
