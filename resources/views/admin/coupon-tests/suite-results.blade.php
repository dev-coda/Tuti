@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupon-tests.suite') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a suite</a>
        <h1 class="text-2xl font-bold text-gray-900">Resultados de suite de cupones/XML</h1>
        <p class="text-gray-600 mt-1">Ejecución diagnóstica en admin, sin transmisión de XML.</p>
    </div>

    <div class="mb-6 p-4 rounded-lg border {{ ($summary['failed'] ?? 0) === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }}">
        <div class="flex flex-wrap gap-4 text-sm">
            <span><strong>Total:</strong> {{ $summary['total'] ?? 0 }}</span>
            <span><strong>OK:</strong> {{ $summary['passed'] ?? 0 }}</span>
            <span><strong>Fallidos:</strong> {{ $summary['failed'] ?? 0 }}</span>
            <span><strong>Ejecutado:</strong> {{ $summary['ran_at'] ?? '' }}</span>
            <span><strong>Actor:</strong> {{ $summary['actor'] ?? '' }}</span>
        </div>
        <div class="mt-3">
            <a href="{{ route('coupon-tests.suite.export', ['format' => 'json', 'run_id' => $runId ?? '']) }}" class="inline-flex px-3 py-2 bg-gray-800 text-white rounded text-xs">Exportar JSON</a>
            <a href="{{ route('coupon-tests.suite.export', ['format' => 'csv', 'run_id' => $runId ?? '']) }}" class="inline-flex px-3 py-2 bg-gray-600 text-white rounded text-xs ml-2">Exportar CSV</a>
        </div>
    </div>

    <div class="space-y-6">
        @foreach($results as $result)
            <div class="border rounded-lg {{ ($result['passed'] ?? false) ? 'border-emerald-200' : 'border-amber-200' }}">
                <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $result['name'] }}</h3>
                        <p class="text-xs text-gray-600">
                            Cupones: {{ implode(', ', $result['coupon_codes'] ?? []) ?: 'N/A' }} |
                            Total cupón: ${{ number_format($result['coupon_total_discount'] ?? 0, 2) }}
                        </p>
                    </div>
                    <span class="text-sm font-semibold {{ ($result['passed'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ ($result['passed'] ?? false) ? 'PASS' : 'FAIL' }}
                    </span>
                </div>
                <div class="p-4">
                    @if(!empty($result['error']))
                        <div class="text-sm text-red-700">{{ $result['error'] }}</div>
                    @else
                        <ul class="text-sm space-y-1 mb-4">
                            @foreach($result['assertions'] ?? [] as $assertion)
                                <li class="{{ ($assertion['passed'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ ($assertion['passed'] ?? false) ? '✓' : '✗' }} {{ $assertion['message'] ?? '' }}
                                </li>
                            @endforeach
                        </ul>
                        <details>
                            <summary class="cursor-pointer text-sm text-blue-700 font-medium">Ver XML</summary>
                            <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs whitespace-pre-wrap max-h-[380px] overflow-y-auto mt-2"><code>{{ $result['xml'] ?? '' }}</code></pre>
                        </details>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if(($totalPages ?? 1) > 1)
        <div class="mt-8 flex items-center justify-center gap-2">
            @if($page > 1)
                <a href="{{ route('coupon-tests.suite.results', ['run_id' => $runId ?? '', 'page' => $page - 1]) }}"
                   class="px-3 py-2 bg-gray-100 border border-gray-300 rounded text-sm hover:bg-gray-200">
                    ← Anterior
                </a>
            @endif

            <span class="px-3 py-2 text-sm text-gray-600">
                Página {{ $page }} de {{ $totalPages }}
            </span>

            @if($page < $totalPages)
                <a href="{{ route('coupon-tests.suite.results', ['run_id' => $runId ?? '', 'page' => $page + 1]) }}"
                   class="px-3 py-2 bg-gray-100 border border-gray-300 rounded text-sm hover:bg-gray-200">
                    Siguiente →
                </a>
            @endif
        </div>
    @endif
</div>
@endsection
