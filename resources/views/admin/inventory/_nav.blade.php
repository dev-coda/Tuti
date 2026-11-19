@php
    $tabs = [
        'admin.inventory.index' => 'Control',
        'admin.inventory.intelligence' => 'Inteligencia',
        'admin.inventory.agent' => 'Agente',
    ];
@endphp

<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-800">Stage</span>
        </div>
        <p class="mt-1 max-w-3xl text-sm text-gray-600">{{ $subtitle }}</p>
    </div>
    <nav class="flex flex-wrap gap-2" aria-label="Módulo de inventario">
        @foreach ($tabs as $route => $label)
            <a href="{{ route($route) }}"
               class="rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs($route) ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
