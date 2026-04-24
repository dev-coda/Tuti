@extends('layouts.admin')

@section('head')
    <style>
        [data-doc-index] .doc-prose{max-width:none}
        [data-doc-index] .doc-prose h1{display:none}
        [data-doc-index] .doc-prose table{min-width:100%;display:block;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
        [data-doc-index] .doc-prose th,[data-doc-index] .doc-prose td{white-space:normal;min-width:7rem;vertical-align:top}
    </style>
@endsection

@section('content')
    <div class="px-4 py-6 max-w-7xl mx-auto" data-doc-index>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Documentación</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 max-w-3xl">
                Manuales de uso y administración (español), tomados de <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">/docs/guias</code> del proyecto.
                Los enlaces Markdown a archivos o rutas de código son referencia; la navegación de la app es el menú lateral.
            </p>
        </div>

        @if($sections)
            <nav class="mb-8 flex flex-wrap gap-2" aria-label="Secciones">
                @foreach($sections as $section)
                    <a href="#seccion-{{ $section['id'] }}"
                       class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-3 py-1 text-sm font-medium text-gray-800 dark:text-gray-200 ring-1 ring-inset ring-gray-200 dark:ring-gray-600 hover:bg-gray-200 dark:hover:bg-gray-700">
                        {{ $section['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            @forelse($sections as $section)
                <section id="seccion-{{ $section['id'] }}"
                         class="flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 shadow-sm overflow-hidden scroll-mt-24 min-h-0">
                    <header class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800 px-4 py-3">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $section['label'] }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">{{ $section['description'] }}</p>
                    </header>
                    <ul class="flex-1 list-none p-0 m-0 divide-y divide-gray-100 dark:divide-gray-700" role="list">
                        @foreach($section['files'] as $f)
                            <li>
                                <a href="{{ route('admin.documentation.show', ['f' => $f['path']]) }}"
                                   class="group block px-4 py-3 hover:bg-blue-50/60 dark:hover:bg-gray-800 transition-colors">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300 leading-snug">
                                        {{ $f['title'] }}
                                    </span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-500 font-mono break-all">
                                        {{ $f['name'] }}
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-gray-500 col-span-full lg:col-span-1">No hay guías en subcarpetas. Comprobá que <code>docs/guias</code> contenga <code>b2b-tienda</code>, <code>admin</code> o <code>roles</code>.</p>
            @endforelse
        </div>

        @if($readmeFull)
            <div class="mt-10 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800/30 overflow-hidden">
                <details class="group">
                    <summary class="cursor-pointer list-none select-none flex items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800/80 [&::-webkit-details-marker]:hidden">
                        <span>Índice ampliado (README completo)</span>
                        <span class="text-gray-500 group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
                    </summary>
                    <div class="px-2 py-4 sm:px-4 border-t border-gray-200 dark:border-gray-700 max-h-[70vh] overflow-y-auto overflow-x-hidden">
                        <div class="doc-prose doc-readme
                            prose prose-slate max-w-none dark:prose-invert
                            prose-sm sm:prose-base
                            prose-headings:scroll-mt-20 prose-h2:text-lg prose-h2:mt-6
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
