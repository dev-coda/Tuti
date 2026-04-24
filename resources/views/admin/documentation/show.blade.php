@extends('layouts.admin')

@section('head')
    <style>
        [data-doc-body] .doc-content table{display:table;width:100%;border-collapse:collapse}
        [data-doc-body] .doc-content .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:1em 0;border:1px solid rgba(0,0,0,.12);border-radius:.5rem}
        [data-doc-body] .doc-content th,[data-doc-body] .doc-content td{padding:.5rem .75rem;border:1px solid rgba(0,0,0,.1);vertical-align:top}
        /* Título arriba ya se muestra en <header> */
        [data-doc-body] #md-root > h1:first-of-type{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    </style>
@endsection

@section('content')
    <div class="px-4 py-6 max-w-5xl mx-auto" data-doc-body>
        <nav class="mb-6 flex flex-wrap items-center gap-3 text-sm" aria-label="Migas">
            <a href="{{ route('admin.documentation.index') }}"
               class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400">
                <span aria-hidden="true">←</span> Todas las guías
            </a>
        </nav>

        <header class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white leading-tight tracking-tight">
                {{ $pageTitle }}
            </h1>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-mono break-all" title="Ruta en el repositorio">
                docs/guias/{{ $slug }}
            </p>
        </header>

        <div class="doc-content
            prose prose-slate max-w-none dark:prose-invert
            prose-p:leading-relaxed prose-p:text-gray-700 dark:prose-p:text-gray-300
            prose-sm sm:prose-base
            prose-h1:sr-only
            prose-h2:text-xl prose-h2:font-bold prose-h2:mt-10 prose-h2:mb-3 prose-h2:scroll-mt-24
            prose-h3:text-lg prose-h3:font-semibold prose-h3:mt-6 prose-h3:mb-2
            prose-h4:text-base
            prose-ul:my-3 prose-ol:my-3
            prose-li:my-0.5
            prose-blockquote:border-l-4 prose-blockquote:border-amber-400/80 prose-blockquote:bg-amber-50/30 dark:prose-blockquote:bg-amber-900/10 prose-blockquote:pl-4 prose-blockquote:py-1
            prose-code:before:content-none prose-code:after:content-none
            prose-code:rounded prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-1.5 prose-code:py-0.5 prose-code:font-mono prose-code:text-[0.9em] prose-code:text-rose-700 dark:prose-code:text-amber-200
            prose-pre:rounded-lg prose-pre:border prose-pre:border-gray-200 dark:prose-pre:border-gray-700 prose-pre:shadow-sm
            prose-hr:my-8
            prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:font-medium
            prose-strong:text-gray-900 dark:prose-strong:text-white
        ">
            <div class="table-wrap-outer" id="md-root">
                {!! $html !!}
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
    (function () {
        const root = document.getElementById('md-root');
        if (!root) return;
        const tables = root.querySelectorAll('table');
        tables.forEach(function (t) {
            if (t.closest('.table-wrap')) return;
            const w = document.createElement('div');
            w.className = 'table-wrap';
            t.parentNode.insertBefore(w, t);
            w.appendChild(t);
        });
    })();
    </script>
@endsection