@extends('layouts.page')

@section('head')
<title>{{ $title }} - Tuti</title>
<meta name="description" content="{{ $title }}">
@endsection

@section('content')
<div class="bg-white">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $title }}</h1>
        </div>
        
        <div class="content-area">
            {!! $content !!}
        </div>
        
        <div class="mt-8 pt-8 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <a href="{{ route('home') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                    ← Volver al inicio
                </a>
                <p class="text-sm text-gray-500">
                    ¿Tienes preguntas? <a href="{{ route('content.faq') }}" class="text-blue-600 hover:text-blue-800">Consulta nuestras FAQ</a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.content-area {
    color: #374151;
    line-height: 1.75;
}

.content-area h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: #111827;
}

.content-area h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #111827;
}

.content-area h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: #111827;
}

.content-area h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #111827;
}

.content-area p {
    margin-bottom: 1rem;
}

.content-area ul, .content-area ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.content-area li {
    margin-bottom: 0.5rem;
}

.content-area strong {
    font-weight: 600;
    color: #111827;
}

.content-area a {
    color: #2563eb;
    text-decoration: underline;
}

.content-area a:hover {
    color: #1d4ed8;
}

.content-area blockquote {
    border-left: 4px solid #e5e7eb;
    padding-left: 1rem;
    margin: 1rem 0;
    font-style: italic;
    color: #6b7280;
}

.content-area hr {
    margin: 2rem 0;
    border: 0;
    border-top: 1px solid #e5e7eb;
}

.content-area img {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
    border-radius: 0.5rem;
}

.content-area table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.content-area table th,
.content-area table td {
    border: 1px solid #e5e7eb;
    padding: 0.75rem;
    text-align: left;
}

.content-area table th {
    background-color: #f9fafb;
    font-weight: 600;
}
</style>
@endsection

