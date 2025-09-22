@extends('layouts.page')

@section('content')
<div class="container mx-auto max-w-4xl px-4 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $title }}</h1>
        
        <div class="prose prose-lg max-w-none">
            <div class="content-area">
                {!! $content !!}
            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <a href="{{ route('home') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver al inicio
            </a>
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
</style>
@endsection
