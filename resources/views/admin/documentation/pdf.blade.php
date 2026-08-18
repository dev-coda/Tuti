<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #1f2937;
        }
        .meta {
            margin: 0 0 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #d1d5db;
            font-size: 9pt;
            color: #6b7280;
        }
        .meta strong { color: #374151; }
        h1, h2, h3, h4, h5, h6 {
            color: #111827;
            line-height: 1.25;
            page-break-after: avoid;
        }
        h1 { font-size: 20pt; margin: 0 0 12px; }
        h2 { font-size: 14pt; margin: 22px 0 8px; }
        h3 { font-size: 12pt; margin: 16px 0 6px; }
        h4 { font-size: 11pt; margin: 14px 0 6px; }
        p, ul, ol { margin: 0 0 10px; }
        ul, ol { padding-left: 22px; }
        li { margin: 2px 0; }
        a { color: #1d4ed8; text-decoration: none; }
        code, pre {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9pt;
        }
        code {
            background: #f3f4f6;
            padding: 1px 4px;
            border-radius: 3px;
        }
        pre {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            page-break-inside: avoid;
        }
        pre code { background: transparent; padding: 0; }
        blockquote {
            margin: 0 0 12px;
            padding: 6px 12px;
            border-left: 3px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14px;
            font-size: 9.5pt;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
        }
        th { background: #f9fafb; font-weight: bold; }
        hr {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 18px 0;
        }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <p class="meta">
        <strong>Tuti — Documentación y guías</strong><br>
        docs/guias/{{ $slug }} · Generado {{ $generatedAt }}
    </p>

    <div class="doc-content">
        {!! $html !!}
    </div>
</body>
</html>
