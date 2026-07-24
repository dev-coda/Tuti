@php
    /** Rows preference: old input (validation errors) > existing assignments > one empty row. */
    $existing = collect($assignments ?? [])->map(fn ($row) => [
        'zone' => (string) ($row['zone'] ?? $row->zone ?? ''),
        'route' => (string) ($row['route'] ?? $row->route ?? ''),
    ]);
    $rows = collect(old('assignments', $existing->all()))
        ->map(fn ($row) => [
            'zone' => (string) ($row['zone'] ?? ''),
            'route' => (string) ($row['route'] ?? ''),
        ])
        ->values();
    if ($rows->isEmpty()) {
        $rows = collect([['zone' => '', 'route' => '']]);
    }
@endphp

<div class="col-span-2 mt-2">
    <h4 class="text-sm font-semibold text-gray-700 mb-1">Rutas asignadas</h4>
    <p class="text-xs text-gray-500 mb-3">
        Zonas y rutas que el supervisor podrá consultar en la pestaña "Mis Rutas" de Mi Cuenta.
    </p>

    @error('assignments')
        <p class="text-sm text-red-600 mb-2">{{ $message }}</p>
    @enderror

    <div id="supervisor-assignments" class="space-y-2">
        @foreach($rows as $index => $row)
            <div class="flex items-center gap-3" data-assignment-row>
                <input type="number" name="assignments[{{ $index }}][zone]" value="{{ $row['zone'] }}"
                       placeholder="Zona" min="0"
                       class="w-32 border-gray-300 rounded-lg text-sm px-3 py-2">
                <input type="number" name="assignments[{{ $index }}][route]" value="{{ $row['route'] }}"
                       placeholder="Ruta" min="0"
                       class="w-32 border-gray-300 rounded-lg text-sm px-3 py-2">
                <button type="button" data-assignment-remove
                        class="text-sm text-red-600 hover:text-red-800 font-medium">
                    Quitar
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" id="supervisor-assignments-add"
            class="mt-3 px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
        + Agregar ruta
    </button>
</div>

<template id="supervisor-assignment-template">
    <div class="flex items-center gap-3" data-assignment-row>
        <input type="number" name="assignments[__IDX__][zone]" placeholder="Zona" min="0"
               class="w-32 border-gray-300 rounded-lg text-sm px-3 py-2">
        <input type="number" name="assignments[__IDX__][route]" placeholder="Ruta" min="0"
               class="w-32 border-gray-300 rounded-lg text-sm px-3 py-2">
        <button type="button" data-assignment-remove
                class="text-sm text-red-600 hover:text-red-800 font-medium">
            Quitar
        </button>
    </div>
</template>

<script>
    (function () {
        const container = document.getElementById('supervisor-assignments');
        const template = document.getElementById('supervisor-assignment-template');
        const addButton = document.getElementById('supervisor-assignments-add');
        let nextIndex = {{ $rows->count() }};

        addButton.addEventListener('click', function () {
            const html = template.innerHTML.replaceAll('__IDX__', String(nextIndex++));
            container.insertAdjacentHTML('beforeend', html);
        });

        container.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-assignment-remove]');
            if (!remove) return;
            remove.closest('[data-assignment-row]').remove();
        });
    })();
</script>
