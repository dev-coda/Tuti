@extends('layouts.admin')

@section('head')
    <style>
        [data-doc-index] .doc-prose{max-width:none}
        [data-doc-index] .doc-readme h1{display:none}
        [data-doc-index] .doc-readme table{width:100%;min-width:760px;border-collapse:separate;border-spacing:0;font-size:.875rem;line-height:1.45}
        [data-doc-index] .doc-readme th{background:rgb(248 250 252);font-weight:700;color:rgb(51 65 85)}
        [data-doc-index] .doc-readme th,[data-doc-index] .doc-readme td{padding:.75rem 1rem;border-bottom:1px solid rgb(226 232 240);vertical-align:top}
        [data-doc-index] .doc-readme tr:last-child td{border-bottom:0}
        [data-doc-index] .doc-readme td:first-child{font-weight:600;color:rgb(30 41 59)}
        [data-doc-index] .doc-readme code{white-space:normal;word-break:break-word}
        @media (prefers-color-scheme: dark){
            [data-doc-index] .doc-readme th{background:rgb(31 41 55);color:rgb(226 232 240)}
            [data-doc-index] .doc-readme th,[data-doc-index] .doc-readme td{border-bottom-color:rgb(55 65 81)}
            [data-doc-index] .doc-readme td:first-child{color:rgb(241 245 249)}
        }
    </style>
@endsection

@section('content')
    @php
        $totalDocs = array_sum(array_map(fn ($section) => count($section['files']), $sections));
    @endphp

    <div class="px-4 py-6 max-w-7xl mx-auto" data-doc-index>
        <div class="mb-8 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">Centro de ayuda interno</p>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Documentación y guías</h1>
                </div>
                @if($totalDocs > 0)
                    <div class="inline-flex w-fit items-center rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-sm font-semibold text-blue-700 dark:text-blue-200 ring-1 ring-inset ring-blue-100 dark:ring-blue-800">
                        {{ $totalDocs }} guías activas
                    </div>
                @endif
            </div>
            <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300 max-w-4xl">
                Manuales de uso y administración (español), tomados de <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">/docs/guias</code> del proyecto.
                Los enlaces Markdown a archivos o rutas de código son referencia; la navegación de la app es el menú lateral.
            </p>
        </div>

        @if($sections)
            <nav class="mb-6 flex flex-wrap gap-2" aria-label="Secciones">
                @foreach($sections as $section)
                    <a href="#seccion-{{ $section['id'] }}"
                       class="inline-flex items-center gap-2 rounded-full bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-800 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-200 dark:ring-gray-600 hover:bg-blue-50 dark:hover:bg-gray-700">
                        {{ $section['label'] }}
                        <span class="rounded-full bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-xs text-gray-500 dark:text-gray-300">{{ count($section['files']) }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="space-y-6">
            @forelse($sections as $section)
                <section id="seccion-{{ $section['id'] }}"
                         class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 shadow-sm overflow-hidden scroll-mt-24">
                    <header class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800 px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $section['label'] }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 leading-snug">{{ $section['description'] }}</p>
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ count($section['files']) }} documentos</span>
                        </div>
                    </header>
                    <ul class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3 sm:p-5" role="list">
                        @foreach($section['files'] as $f)
                            @php
                                preg_match('/^(\d+)[\-_]/', $f['name'], $orderMatch);
                            @endphp
                            <li>
                                <a href="{{ route('admin.documentation.show', ['f' => $f['path']]) }}"
                                   class="group flex h-full gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/30 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:bg-blue-50/60 hover:shadow-md dark:hover:border-blue-700 dark:hover:bg-gray-800">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-300 group-hover:bg-blue-100 group-hover:text-blue-700 dark:group-hover:bg-blue-900/60 dark:group-hover:text-blue-200">
                                        {{ $orderMatch[1] ?? 'MD' }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-gray-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300 leading-5">
                                            {{ $f['title'] }}
                                        </span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400 font-mono break-words">
                                            {{ $f['name'] }}
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-gray-500">No hay guías en subcarpetas. Comprobá que <code>docs/guias</code> contenga <code>b2b-tienda</code>, <code>admin</code> o <code>roles</code>.</p>
            @endforelse
        </div>

        @if($readmeFull)
            <div class="mt-10 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800/30 overflow-hidden">
                <details class="group">
                    <summary class="cursor-pointer list-none select-none flex items-center justify-between gap-4 px-4 py-4 text-sm bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800/80 [&::-webkit-details-marker]:hidden">
                        <span>
                            <span class="block font-semibold text-gray-900 dark:text-white">Índice ampliado del README</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Referencia completa con mapas, tablas y enlaces cruzados.</span>
                        </span>
                        <span class="shrink-0 text-gray-500 group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
                    </summary>
                    <div class="border-t border-gray-200 dark:border-gray-700 max-h-[70vh] overflow-auto p-4">
                        <div class="doc-prose doc-readme
                            prose prose-slate max-w-none dark:prose-invert
                            prose-sm sm:prose-base
                            prose-headings:scroll-mt-20 prose-h2:text-xl prose-h2:mt-8 prose-h2:mb-3
                            prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:font-medium
                            prose-td:align-top prose-th:align-top
                        ">
                            {!! $readmeFull !!}
                        </div>
                    </div>
                </details>
            </div>
        @endif

        @if(empty($sections) && ! $readmeFull)
            <p class="text-gray-500">No se encontró la carpeta de guías en el servidor. Comprobá que <code>docs/guias</code> exista en el despliegue.</p>
        @endif
    </div>
@endsection
