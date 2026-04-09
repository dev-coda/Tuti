@extends('layouts.admin')

@section('content')
<div class="p-4 bg-white">
    <div class="mb-6">
        <a href="{{ route('coupon-tests.suite') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium mb-2 inline-block">← Volver a suite</a>
        <h1 class="text-2xl font-bold text-gray-900">Ejecutando suite de cupones/XML</h1>
        <p class="text-gray-600 mt-1">Los escenarios se procesan en segundo plano. Esta página se actualiza automáticamente.</p>
    </div>

    <div id="progress-container" class="max-w-2xl">
        <div class="p-6 bg-gray-50 rounded-lg border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span id="status-label" class="text-sm font-medium text-gray-700">Iniciando...</span>
                <span id="progress-text" class="text-sm text-gray-500">0 / 0</span>
            </div>

            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div id="progress-bar" class="bg-indigo-600 h-4 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
            </div>

            <div class="mt-4 flex gap-6 text-sm text-gray-600" id="stats-row" style="display: none;">
                <span>OK: <strong id="stat-passed" class="text-emerald-700">0</strong></span>
                <span>Fallidos: <strong id="stat-failed" class="text-amber-700">0</strong></span>
            </div>

            <div id="error-message" class="mt-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800" style="display: none;"></div>
        </div>

        <div id="completed-actions" class="mt-6" style="display: none;">
            <a id="results-link" href="#" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Ver resultados
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const runId = @json($runId);
    const statusUrl = @json(route('coupon-tests.suite.status'));
    const resultsBaseUrl = @json(route('coupon-tests.suite.results'));

    const statusLabel = document.getElementById('status-label');
    const progressText = document.getElementById('progress-text');
    const progressBar = document.getElementById('progress-bar');
    const statsRow = document.getElementById('stats-row');
    const statPassed = document.getElementById('stat-passed');
    const statFailed = document.getElementById('stat-failed');
    const errorMessage = document.getElementById('error-message');
    const completedActions = document.getElementById('completed-actions');
    const resultsLink = document.getElementById('results-link');

    let pollInterval = 2000;
    let polling = true;
    let consecutiveErrors = 0;
    const maxConsecutiveErrors = 30;

    function poll() {
        if (!polling) return;

        fetch(statusUrl + '?run_id=' + encodeURIComponent(runId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(data => {
            consecutiveErrors = 0;
            const percent = data.percent || 0;
            const processed = data.processed || 0;
            const total = data.total || 0;

            progressBar.style.width = percent + '%';
            progressText.textContent = processed + ' / ' + total;

            if (data.passed !== undefined || data.failed !== undefined) {
                statsRow.style.display = '';
                statPassed.textContent = data.passed || 0;
                statFailed.textContent = data.failed || 0;
            }

            if (data.status === 'running') {
                statusLabel.textContent = 'Procesando escenarios...';
                pollInterval = Math.min(pollInterval, 3000);
            } else if (data.status === 'completed') {
                statusLabel.textContent = 'Suite completada. Redirigiendo…';
                progressBar.classList.remove('bg-indigo-600');
                progressBar.classList.add('bg-emerald-600');
                var url = resultsBaseUrl + '?run_id=' + encodeURIComponent(runId);
                completedActions.style.display = '';
                resultsLink.href = url;
                polling = false;
                setTimeout(function () { window.location.href = url; }, 800);
                return;
            } else if (data.status === 'failed') {
                statusLabel.textContent = 'La suite falló';
                progressBar.classList.remove('bg-indigo-600');
                progressBar.classList.add('bg-red-500');
                if (data.error) {
                    errorMessage.textContent = data.error;
                    errorMessage.style.display = '';
                }
                polling = false;
                return;
            } else if (data.status === 'pending') {
                statusLabel.textContent = 'En cola, esperando procesamiento...';
            }

            setTimeout(poll, pollInterval);
        })
        .catch(() => {
            consecutiveErrors++;
            if (consecutiveErrors >= maxConsecutiveErrors) {
                statusLabel.textContent = 'No se pudo contactar al servidor. Recarga la página para reintentar.';
                polling = false;
                return;
            }
            setTimeout(poll, Math.min(pollInterval * 2, 15000));
        });
    }

    poll();
});
</script>
@endsection
