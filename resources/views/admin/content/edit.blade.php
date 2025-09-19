@extends('layouts.admin')

@section('content')
<div class="grid grid-cols-1 p-4 xl:grid-cols-1 xl:gap-4">
    <div class="mb-4 col-span-full xl:mb-2 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">Editar: {{ $setting->name }}</h1>
            <p class="text-gray-600 mt-1">Edita el contenido usando el editor enriquecido</p>
        </div>
        <a 
            href="{{ route('admin.content.index') }}" 
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors"
        >
            ← Volver al listado
        </a>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <form id="content-form" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Contenido de {{ $setting->name }}
                </label>
                
                <!-- Vue Rich Text Editor Component -->
                <div 
                    class="rich-text-editor-mount" 
                    data-content="{{ json_encode($setting->value ?? '', JSON_UNESCAPED_UNICODE) }}"
                    data-content-encoding="json"
                    data-name="content"
                    data-placeholder="Escribe el contenido aquí..."
                    data-height="500px"
                ></div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                <div class="flex items-center gap-4">
                    <button 
                        type="button" 
                        onclick="
                            console.log('Save button clicked!'); 
                            console.log('window.saveContent type:', typeof window.saveContent);
                            console.log('saveContent type:', typeof saveContent);
                            if(typeof window.saveContent === 'function') { 
                                console.log('Calling window.saveContent()'); 
                                window.saveContent(); 
                            } else if(typeof saveContent === 'function') { 
                                console.log('Calling saveContent()'); 
                                saveContent(); 
                            } else { 
                                console.error('❌ No saveContent function available anywhere'); 
                                console.error('Check console logs above for function loading details'); 
                            } 
                            return false;
                        "
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors"
                    >
                        Guardar Cambios
                    </button>
                    
                    <button 
                        type="button" 
                        onclick="console.log('Preview button clicked!'); if(typeof window.previewContent === 'function') { window.previewContent(); } else { console.error('previewContent function not available'); }" 
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
                    >
                        Vista Previa
                    </button>
                    
                    <button 
                        type="button" 
                        onclick="console.log('Debug button clicked!'); if(typeof window.debugContent === 'function') { window.debugContent(); } else { console.error('debugContent function not available'); }" 
                        class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 font-medium py-2 px-4 rounded-lg transition-colors text-sm"
                    >
                        Debug Content
                    </button>
                    
                    <button 
                        type="button" 
                        onclick="if(typeof window.testFunction === 'function') { window.testFunction(); } else { console.error('❌ Test function not available'); }" 
                        class="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-2 px-4 rounded-lg transition-colors text-sm"
                    >
                        Test Script
                    </button>
                </div>
                
                <div class="text-sm text-gray-500">
                    <span id="save-status" class="hidden"></span>
                    <span id="word-count">0 palabras</span>
                </div>
            </div>
        </form>
    </div>

    <!-- Preview Modal -->
    <div id="preview-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Vista Previa - {{ $setting->name }}</h3>
                    <button 
                        onclick="closePreview()" 
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div id="preview-content" class="prose max-w-none">
                        <!-- Preview content will be injected here -->
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 bg-gray-50">
                    <div class="flex justify-end gap-3">
                        @if($setting->key === 'terms_conditions_content')
                            <a 
                                href="{{ route('content.terms') }}" 
                                target="_blank"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
                            >
                                Ver Página Pública
                            </a>
                        @elseif($setting->key === 'privacy_policy_content')
                            <a 
                                href="{{ route('content.privacy') }}" 
                                target="_blank"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
                            >
                                Ver Página Pública
                            </a>
                        @elseif($setting->key === 'faq_content')
                            <a 
                                href="{{ route('content.faq') }}" 
                                target="_blank"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
                            >
                                Ver Página Pública
                            </a>
                        @endif
                        <button 
                            onclick="closePreview()" 
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let currentContent = '';

console.log('🚀 Content edit script loading...');

// Simple test function
window.testFunction = function() {
    console.log('✅ Test function is working! Scripts are loaded.');
    return true;
};

console.log('✅ Test function declared:', typeof window.testFunction);

// Declare functions immediately at global scope
(function() {
    console.log('🔧 Starting function declarations...');
    
    // Standalone save function
    window.saveContent = async function() {
        console.log('=== SAVE FUNCTION CALLED ===');
    
    // Get the latest content from all possible sources
    let contentToSave = currentContent;
    
    // Check hidden input first
    const hiddenInput = document.querySelector('input[name="content"]');
    if (hiddenInput && hiddenInput.value) {
        contentToSave = hiddenInput.value;
        console.log('Saving from hidden input');
    }
    
    // If no hidden input content, try to get from Vue component directly
    if (!contentToSave) {
        try {
            const editorElement = document.querySelector('.rich-text-editor-mount .ql-editor');
            if (editorElement) {
                contentToSave = editorElement.innerHTML;
                console.log('Saving from direct editor content');
            }
        } catch (e) {
            console.log('Could not get direct editor content for saving:', e);
        }
    }
    
    console.log('Content to save (first 200 chars):', contentToSave.substring(0, 200) + '...');
    
    const formData = new FormData();
    formData.append('content', contentToSave || '');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    formData.append('_method', 'PUT');
    
    const saveStatus = document.getElementById('save-status');
    saveStatus.textContent = 'Guardando...';
    saveStatus.className = 'text-yellow-600';
    saveStatus.classList.remove('hidden');
    
    try {
        const response = await fetch('{{ route("admin.content.update", $setting->key) }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            saveStatus.textContent = '✓ Guardado correctamente';
            saveStatus.className = 'text-green-600';
            
            setTimeout(() => {
                saveStatus.classList.add('hidden');
            }, 3000);
        } else {
            throw new Error(result.message || 'Error al guardar');
        }
    } catch (error) {
        saveStatus.textContent = '✗ Error al guardar';
        saveStatus.className = 'text-red-600';
        console.error('Save error:', error);
    }
};

// Preview functions
window.previewContent = function() {
    console.log('=== PREVIEW FUNCTION CALLED ===');
    
    // Always get the latest content from multiple sources
    let latestContent = currentContent;
    
    // Check hidden input first
    const hiddenInput = document.querySelector('input[name="content"]');
    if (hiddenInput && hiddenInput.value) {
        latestContent = hiddenInput.value;
        console.log('Preview using hidden input content');
    }
    
    // If no hidden input content, try to get from Vue component directly
    if (!latestContent) {
        try {
            // Try to get content from Quill editor directly
            const editorElement = document.querySelector('.rich-text-editor-mount .ql-editor');
            if (editorElement) {
                latestContent = editorElement.innerHTML;
                console.log('Preview using direct editor content');
            }
        } catch (e) {
            console.log('Could not get direct editor content:', e);
        }
    }
    
    // Fallback to original data
    if (!latestContent) {
        const contentElement = document.querySelector('[data-name="content"]');
        if (contentElement) {
            latestContent = contentElement.dataset.content || '';
            console.log('Preview using original data content');
        }
    }
    
    console.log('Preview content (first 200 chars):', latestContent.substring(0, 200) + '...');
    
    // Update currentContent for consistency
    currentContent = latestContent;
    
    document.getElementById('preview-content').innerHTML = latestContent || '<p class="text-gray-500">No hay contenido para mostrar</p>';
    document.getElementById('preview-modal').classList.remove('hidden');
};

window.closePreview = function() {
    document.getElementById('preview-modal').classList.add('hidden');
};

// Debug function to check content sources
window.debugContent = function() {
    console.log('=== CONTENT DEBUG ===');
    console.log('1. currentContent variable:', currentContent ? currentContent.substring(0, 200) + '...' : 'EMPTY');
    
    // Test UTF-8 encoding
    const testSpanish = 'Términos y Condiciones - Política de Privacidad - FAQ - Niño - Años';
    console.log('2. UTF-8 test string:', testSpanish);
    
    const hiddenInput = document.querySelector('input[name="content"]');
    console.log('3. Hidden input exists:', !!hiddenInput);
    if (hiddenInput) {
        console.log('   Hidden input value:', hiddenInput.value.substring(0, 200) + '...');
    }
    
    const editorElement = document.querySelector('.rich-text-editor-mount .ql-editor');
    console.log('4. Quill editor element exists:', !!editorElement);
    if (editorElement) {
        console.log('   Editor innerHTML:', editorElement.innerHTML.substring(0, 200) + '...');
    }
    
    const dataElement = document.querySelector('[data-name="content"]');
    console.log('5. Data element exists:', !!dataElement);
    if (dataElement) {
        console.log('   Data content encoding:', dataElement.dataset.contentEncoding);
        console.log('   Data content (raw):', dataElement.dataset.content.substring(0, 100) + '...');
    }
    
    // Show alert with quick summary
    const summary = `Current Content: ${currentContent ? 'YES' : 'NO'}
Hidden Input: ${hiddenInput ? 'YES' : 'NO'}
Quill Editor: ${editorElement ? 'YES' : 'NO'}`;
    
    console.log('Content Debug Summary:', summary);
    console.log('Check console logs above for detailed content source information.');
};

    // Update word count - also make global
    window.updateWordCount = function(content) {
        const text = content.replace(/<[^>]*>/g, '').trim();
        const words = text ? text.split(/\s+/).length : 0;
        const wordCountEl = document.getElementById('word-count');
        if (wordCountEl) {
            wordCountEl.textContent = `${words} palabras`;
        }
    };

    console.log('✅ All global functions declared successfully');
    
    // Test that functions are actually available
    console.log('Function availability check:');
    console.log('- window.saveContent:', typeof window.saveContent);
    console.log('- window.previewContent:', typeof window.previewContent);
    console.log('- window.debugContent:', typeof window.debugContent);
    console.log('- window.closePreview:', typeof window.closePreview);
    console.log('- window.updateWordCount:', typeof window.updateWordCount);
})();

// DOM ready event handler
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready, setting up content editor...');
    
    // Handle form submission
    const contentForm = document.getElementById('content-form');
    if (contentForm) {
        contentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
    
    // Get the latest content from all possible sources
    let contentToSave = currentContent;
    
    // Check hidden input first
    const hiddenInput = document.querySelector('input[name="content"]');
    if (hiddenInput && hiddenInput.value) {
        contentToSave = hiddenInput.value;
        console.log('Saving from hidden input');
    }
    
    // If no hidden input content, try to get from Vue component directly
    if (!contentToSave) {
        try {
            const editorElement = document.querySelector('.rich-text-editor-mount .ql-editor');
            if (editorElement) {
                contentToSave = editorElement.innerHTML;
                console.log('Saving from direct editor content');
            }
        } catch (e) {
            console.log('Could not get direct editor content for saving:', e);
        }
    }
    
    console.log('Content to save (first 200 chars):', contentToSave.substring(0, 200) + '...');
    
    const formData = new FormData();
    formData.append('content', contentToSave || '');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    formData.append('_method', 'PUT');
    
    const saveStatus = document.getElementById('save-status');
    saveStatus.textContent = 'Guardando...';
    saveStatus.className = 'text-yellow-600';
    saveStatus.classList.remove('hidden');
    
    console.log('Saving content:', currentContent); // Debug log
    
    try {
        const response = await fetch('{{ route("admin.content.update", $setting->key) }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            saveStatus.textContent = '✓ Guardado correctamente';
            saveStatus.className = 'text-green-600';
            
            setTimeout(() => {
                saveStatus.classList.add('hidden');
            }, 3000);
        } else {
            throw new Error(result.message || 'Error al guardar');
        }
    } catch (error) {
        saveStatus.textContent = '✗ Error al guardar';
        saveStatus.className = 'text-red-600';
            console.error('Save error:', error);
        }
        });
    }

    // Listen for editor changes
    // Set initial content
    const contentElement = document.querySelector('[data-name="content"]');
    if (contentElement) {
        currentContent = contentElement.dataset.content || '';
        if (typeof window.updateWordCount === 'function') {
            window.updateWordCount(currentContent);
        }
        console.log('Initial content loaded:', currentContent.substring(0, 100) + '...');
    }
    
    // We'll listen for custom events from the Vue component
    window.addEventListener('editor-content-change', function(e) {
        // Only update if this is the content field for this page
        if (e.detail.name === 'content') {
            currentContent = e.detail.content;
            if (typeof window.updateWordCount === 'function') {
                window.updateWordCount(currentContent);
            }
            console.log('Content updated via editor-content-change:', currentContent.substring(0, 100) + '...');
        }
    });
    
    // Also listen for rich-editor-change events (from Vue component)
    window.addEventListener('rich-editor-change', function(e) {
        if (e.detail.name === 'content') {
            currentContent = e.detail.content;
            if (typeof window.updateWordCount === 'function') {
                window.updateWordCount(currentContent);
            }
            console.log('Content updated via rich-editor-change:', currentContent.substring(0, 100) + '...');
        }
    });
    
    // Fallback: Also check for hidden input updates every second
    setInterval(function() {
        const hiddenInput = document.querySelector('input[name="content"]');
        if (hiddenInput && hiddenInput.value !== currentContent) {
            currentContent = hiddenInput.value;
            if (typeof window.updateWordCount === 'function') {
                window.updateWordCount(currentContent);
            }
            console.log('Content synced from hidden input:', currentContent.substring(0, 100) + '...');
        }
    }, 1000);

    // Close modal on outside click
    const previewModal = document.getElementById('preview-modal');
    if (previewModal) {
        previewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                window.closePreview();
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S or Cmd+S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (typeof window.saveContent === 'function') {
                window.saveContent();
            }
        }
        
        // Ctrl+P or Cmd+P to preview
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            if (typeof window.previewContent === 'function') {
                window.previewContent();
            }
        }
    });
    
    // Initial word count
    if (typeof window.updateWordCount === 'function') {
        const initialContent = @json(strip_tags($setting->value ?? ""));
        window.updateWordCount(initialContent);
    }
    
    // Test JSON encoding with Spanish characters
    const testContent = @json("Test: Términos y Condiciones - Niños - Año - Políticas");
    console.log('JSON encoding test with Spanish chars:', testContent);

    // Confirm all functions are loaded
    console.log('All functions loaded:');
    console.log('- saveContent:', typeof window.saveContent);
    console.log('- previewContent:', typeof window.previewContent);
    console.log('- debugContent:', typeof window.debugContent);
    console.log('- closePreview:', typeof window.closePreview);
    console.log('- updateWordCount:', typeof window.updateWordCount);
    
    if (typeof window.saveContent !== 'function') {
        console.error('❌ saveContent function failed to load!');
    } else {
        console.log('✅ saveContent function loaded successfully');
    }
});
</script>
@endsection
