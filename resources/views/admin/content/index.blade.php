@extends('layouts.admin')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-1 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Gestión de Contenido</h1>
        <p class="text-gray-600 mt-2">Administra el contenido de páginas estáticas del sitio web</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($contentSettings as $key => $setting)
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                {{ $setting->name }}
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                @switch($key)
                                    @case('terms_conditions_content')
                                        Términos legales y condiciones de uso de la plataforma
                                        @break
                                    @case('privacy_policy_content')
                                        Políticas de privacidad y manejo de datos
                                        @break
                                    @case('faq_content')
                                        Preguntas frecuentes y respuestas
                                        @break
                                    @default
                                        Contenido del sitio web
                                @endswitch
                            </p>
                            
                            <div class="text-xs text-gray-500 mb-4">
                                @php
                                    $wordCount = str_word_count(strip_tags($setting->value ?? ''));
                                    $charCount = strlen(strip_tags($setting->value ?? ''));
                                @endphp
                                <div class="flex gap-4">
                                    <span>{{ $wordCount }} palabras</span>
                                    <span>{{ $charCount }} caracteres</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-4">
                            @switch($key)
                                @case('terms_conditions_content')
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    @break
                                @case('privacy_policy_content')
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                    @break
                                @case('faq_content')
                                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    @break
                            @endswitch
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <a 
                            href="{{ route('admin.content.edit', $key) }}" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors text-center"
                        >
                            Editar Contenido
                        </a>
                        
                        @if($key === 'terms_conditions_content')
                            <a 
                                href="{{ route('content.terms') }}" 
                                target="_blank"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-lg transition-colors"
                                title="Ver página pública"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        @elseif($key === 'privacy_policy_content')
                            <a 
                                href="{{ route('content.privacy') }}" 
                                target="_blank"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-lg transition-colors"
                                title="Ver página pública"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        @elseif($key === 'faq_content')
                            <a 
                                href="{{ route('content.faq') }}" 
                                target="_blank"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-3 rounded-lg transition-colors"
                                title="Ver página pública"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Quick Stats -->
    <div class="mt-8 bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Estadísticas de Contenido</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600">
                    {{ $contentSettings->count() }}
                </div>
                <div class="text-sm text-gray-600">Páginas de contenido</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">
                    @php
                        $totalWords = $contentSettings->sum(function($setting) {
                            return str_word_count(strip_tags($setting->value ?? ''));
                        });
                    @endphp
                    {{ number_format($totalWords) }}
                </div>
                <div class="text-sm text-gray-600">Palabras totales</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600">
                    {{ $contentSettings->filter(function($setting) { return !empty(trim(strip_tags($setting->value ?? ''))); })->count() }}
                </div>
                <div class="text-sm text-gray-600">Páginas con contenido</div>
            </div>
        </div>
    </div>
</div>
@endsection
