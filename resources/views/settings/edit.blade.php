@extends('layouts.admin')


@section('content')
{{ Aire::open()->route('settings.update', $setting)->bind($setting)}}
<div class="grid grid-cols-1 p-4 xl:grid-cols-3 xl:gap-4 ">
    <div class="mb-4 col-span-full xl:mb-2">
        <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar {{$setting->name}}</h1>
    </div>

    <div class="col-span-2">
        <div class="p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm 2xl:col-span-2 ">
            <h3 class="mb-4 text-xl font-semibold ">Información</h3>

            <div class="grid grid-cols-6 gap-6">
                @php
                    $help = '';
                    if($setting->id == 4){
                        $help = 'Hora militar';
                    }
                @endphp

                @if($setting->key === 'auto_updater_enabled')
                    <div class="col-span-6 sm:col-span-3">
                        {{ Aire::hidden('value')->value(0) }}
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="value" value="1" class="sr-only peer" @checked($setting->value == '1')>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-900">Habilitar</span>
                        </label>
                    </div>
                @elseif(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
                    <div class="col-span-6">
                        <label for="value" class="block text-sm font-medium text-gray-700 mb-2">{{ $setting->name }}</label>
                        <div class="border border-gray-300 rounded-lg overflow-hidden bg-white">
                            <div id="quill-editor" style="height: 400px;"></div>
                            <textarea 
                                id="fallback-textarea" 
                                class="w-full h-96 p-3 border-0 focus:ring-0 hidden"
                                placeholder="Editor de texto (modo de compatibilidad)"
                            >{{ old('value', $setting->value) }}</textarea>
                        </div>
                        <textarea 
                            id="content-textarea" 
                            name="value" 
                            class="hidden"
                        >{{ old('value', $setting->value) }}</textarea>
                        <p class="mt-1 text-sm text-gray-500">Use este editor para dar formato al contenido. Los cambios se guardan automáticamente.</p>
                        <div id="editor-status" class="mt-1 text-sm text-amber-600 hidden">
                            <span id="status-message">Cargando editor...</span>
                        </div>
                    </div>
                @elseif($setting->id == 5)
                    {{ Aire::textarea('value')->rows(10)->groupClass('col-span-6 sm:col-span-3') }}
                @else
                    {{ Aire::input('value')->helpText($help)->groupClass('col-span-6 sm:col-span-3') }}
                @endif
                
                <div class="col-span-6 justify-between  items-center mt-5 space-x-2 flex">

                    <p class="flex space-x-2 items-center">
                        {{ Aire::submit('Actualizar')->variant()->submit() }}
                        <a href="{{ route('settings.index') }}">Cancelar</a>
                    </p>

                               
                </div>
            </div>


        </div>
    </div>

   
</div>
{{ Aire::close() }}





@endsection

@push('head')
@if(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
<!-- Quill.js CSS with fallback -->
<link href="https://cdn.quilljs.com/2.0.2/quill.snow.css" rel="stylesheet" onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.snow.css';">
<noscript>
    <style>
        #quill-editor { 
            display: none; 
        }
        #fallback-textarea { 
            display: block !important; 
        }
    </style>
</noscript>
<style>
/* Fix Quill.js styling conflicts with Tailwind */
.ql-editor {
    font-family: inherit !important;
    font-size: 14px !important;
    line-height: 1.5 !important;
    color: #374151 !important;
    padding: 12px 15px !important;
}

.ql-container {
    font-family: inherit !important;
    border: 1px solid #d1d5db !important;
    border-top: none !important;
    border-radius: 0 0 6px 6px !important;
}

.ql-toolbar {
    border: 1px solid #d1d5db !important;
    border-bottom: none !important;
    border-radius: 6px 6px 0 0 !important;
    background: #f9fafb !important;
    padding: 8px !important;
}

.ql-toolbar .ql-stroke {
    fill: none !important;
    stroke: #6b7280 !important;
    stroke-linecap: round !important;
    stroke-linejoin: round !important;
    stroke-width: 2 !important;
}

.ql-toolbar .ql-stroke.ql-thin {
    stroke-width: 1 !important;
}

.ql-toolbar .ql-fill {
    fill: #6b7280 !important;
    stroke: none !important;
}

.ql-toolbar button:hover .ql-stroke {
    stroke: #374151 !important;
}

.ql-toolbar button:hover .ql-fill {
    fill: #374151 !important;
}

.ql-toolbar button.ql-active .ql-stroke {
    stroke: #2563eb !important;
}

.ql-toolbar button.ql-active .ql-fill {
    fill: #2563eb !important;
}

.ql-toolbar button {
    height: 28px !important;
    width: 28px !important;
    border-radius: 4px !important;
    margin: 2px !important;
    padding: 3px !important;
    border: none !important;
    background: transparent !important;
}

.ql-toolbar button:hover {
    background: #e5e7eb !important;
}

.ql-toolbar button.ql-active {
    background: #dbeafe !important;
}

.ql-picker {
    color: #6b7280 !important;
}

.ql-picker-label {
    border: none !important;
    padding: 2px 8px !important;
    border-radius: 4px !important;
}

.ql-picker-label:hover {
    background: #e5e7eb !important;
}

.ql-picker-options {
    background: white !important;
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    padding: 4px !important;
}

.ql-picker-item {
    padding: 4px 8px !important;
    border-radius: 4px !important;
}

.ql-picker-item:hover {
    background: #f3f4f6 !important;
}

/* Hide problematic icons that might be huge */
.ql-toolbar .ql-video, .ql-toolbar .ql-formula {
    display: none !important;
}

/* Ensure proper height */
#quill-editor .ql-container {
    height: calc(400px - 42px) !important;
}

#quill-editor .ql-editor {
    height: 100% !important;
    overflow-y: auto !important;
}
</style>
@endif
@endpush

@section('scripts')
@if(in_array($setting->key, ['terms_conditions_content', 'privacy_policy_content', 'faq_content']))
<!-- Quill.js JavaScript with fallback -->
<script>
// Set up automatic fallback timeout (3 seconds max)
window.quillLoadTimeout = setTimeout(function() {
    if (typeof Quill === 'undefined') {
        console.warn('Quill loading timeout reached, enabling fallback editor');
        enableFallbackEditor();
    }
}, 3000);

// Fallback function if primary CDN fails
function loadQuillFallback() {
    console.log('Primary Quill CDN failed, trying fallback...');
    var script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.min.js';
    script.onload = function() {
        console.log('Quill loaded from fallback CDN');
        clearTimeout(window.quillLoadTimeout);
        initializeQuillEditor();
    };
    script.onerror = function() {
        console.error('Both Quill CDNs failed to load, falling back to textarea');
        clearTimeout(window.quillLoadTimeout);
        enableFallbackEditor();
    };
    document.head.appendChild(script);
}

// Check if Quill loads successfully from primary CDN
function checkQuillLoaded() {
    if (typeof Quill !== 'undefined') {
        console.log('Quill loaded successfully from primary CDN');
        clearTimeout(window.quillLoadTimeout);
        
        // Update status immediately
        var statusDiv = document.getElementById('editor-status');
        var statusMessage = document.getElementById('status-message');
        if (statusDiv && statusMessage) {
            statusMessage.textContent = 'Editor cargado correctamente';
            statusDiv.className = 'mt-1 text-sm text-green-600';
        }
        
        initializeQuillEditor();
    } else {
        console.warn('checkQuillLoaded called but Quill is undefined');
    }
}
</script>
<script src="https://cdn.quilljs.com/2.0.2/quill.min.js" onload="checkQuillLoaded()" onerror="loadQuillFallback()"></script>
<script>

// Fallback to regular textarea if Quill fails
function enableFallbackEditor() {
    // Prevent multiple calls
    if (window.fallbackEditorEnabled) {
        console.log('Fallback editor already enabled');
        return;
    }
    window.fallbackEditorEnabled = true;
    
    // Clear any existing timeouts
    if (window.quillLoadTimeout) {
        clearTimeout(window.quillLoadTimeout);
    }
    
    document.getElementById('quill-editor').style.display = 'none';
    var fallbackTextarea = document.getElementById('fallback-textarea');
    var contentTextarea = document.getElementById('content-textarea');
    var statusDiv = document.getElementById('editor-status');
    var statusMessage = document.getElementById('status-message');
    
    fallbackTextarea.style.display = 'block';
    fallbackTextarea.classList.remove('hidden');
    fallbackTextarea.value = contentTextarea.value;
    
    // Show status message
    if (statusDiv && statusMessage) {
        statusDiv.classList.remove('hidden');
        statusMessage.textContent = '⚠️ Usando editor de texto básico (modo de compatibilidad)';
        statusDiv.className = 'mt-1 text-sm text-orange-600 bg-orange-50 p-2 rounded border-l-4 border-orange-200';
    }
    
    // Sync fallback textarea with hidden content textarea
    fallbackTextarea.addEventListener('input', function() {
        contentTextarea.value = fallbackTextarea.value;
    });
    
    console.log('Fallback textarea editor enabled');
}
</script>
<script>
// Global function to initialize Quill editor
function initializeQuillEditor() {
    // Prevent multiple initializations
    if (window.quillInitialized || window.fallbackEditorEnabled) {
        console.log('Editor already initialized or fallback enabled');
        return;
    }
    
    if (typeof Quill === 'undefined') {
        console.log('Quill not loaded yet, retrying...');
        var retryCount = (window.quillRetryCount || 0) + 1;
        window.quillRetryCount = retryCount;
        
        if (retryCount > 10) { // Stop after 1 second 
            console.error('Quill failed to load after ' + retryCount + ' attempts, using fallback');
            enableFallbackEditor();
            return;
        }
        
        setTimeout(initializeQuillEditor, 100);
        return;
    }
    
    console.log('Initializing Quill editor...');
    window.quillInitialized = true;
    
    // Clear any existing timeouts
    if (window.quillLoadTimeout) {
        clearTimeout(window.quillLoadTimeout);
    }
    
    // Hide status message once Quill is ready
    var statusDiv = document.getElementById('editor-status');
    if (statusDiv) {
        statusDiv.classList.add('hidden');
    }
    
    // Initialize Quill editor with simplified toolbar
    var quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                [{ 'align': [] }],
                ['link'],
                ['clean']
            ]
        },
        placeholder: 'Escribe aquí el contenido...'
    });

    // Get existing content and set it in Quill
    var existingContent = document.getElementById('content-textarea').value;
    if (existingContent && existingContent.trim() !== '') {
        try {
            quill.root.innerHTML = existingContent;
        } catch (e) {
            console.warn('Could not load existing content:', e);
        }
    }

    // Update hidden textarea when content changes
    quill.on('text-change', function() {
        var html = quill.root.innerHTML;
        // Only store content if it's not just empty paragraphs
        if (html === '<p><br></p>') {
            html = '';
        }
        document.getElementById('content-textarea').value = html;
    });

    // Update textarea before form submission
    document.querySelector('form').addEventListener('submit', function() {
        var html = quill.root.innerHTML;
        if (html === '<p><br></p>') {
            html = '';
        }
        document.getElementById('content-textarea').value = html;
    });

        // Ensure editor is visible and properly styled
        setTimeout(function() {
            quill.focus();
            quill.blur(); // Remove focus after ensuring it's properly initialized
        }, 100);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready, checking Quill status...');
    
    // Add a loading indicator
    var quillContainer = document.getElementById('quill-editor');
    if (quillContainer) {
        quillContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><div class="text-center"><div class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-blue-600 rounded-full" role="status"></div><p class="mt-2">Cargando editor...</p></div></div>';
    }
    
    // Show status
    var statusDiv = document.getElementById('editor-status');
    var statusMessage = document.getElementById('status-message');
    if (statusDiv && statusMessage) {
        statusDiv.classList.remove('hidden');
        statusMessage.textContent = 'Inicializando editor...';
        statusDiv.className = 'mt-1 text-sm text-blue-600';
    }
    
    // Try to initialize immediately if Quill is already loaded
    if (typeof Quill !== 'undefined') {
        console.log('Quill already loaded, initializing immediately');
        initializeQuillEditor();
    } else {
        console.log('Quill not yet loaded, waiting...');
        // Don't start retry loop here, let the script onload/onerror handle it
    }
});
</script>
@endif
@endsection
