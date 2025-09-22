<template>
    <div class="rich-text-editor-wrapper">
        <!-- Quill.js container -->
        <div
            ref="quillElement"
            class="quill-editor"
            :style="{ minHeight: height }"
        ></div>

        <!-- Hidden input for form submission -->
        <input type="hidden" :name="name" :value="content" v-if="name" />

        <!-- Status indicator -->
        <div v-if="showStatus" class="editor-status" :class="statusClass">
            {{ statusMessage }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from "vue";
import Quill from "quill";

// Props
const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    name: {
        type: String,
        default: "",
    },
    placeholder: {
        type: String,
        default: "Escribe aquí...",
    },
    height: {
        type: String,
        default: "300px",
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

// Emits
const emit = defineEmits(["update:modelValue", "change", "focus", "blur"]);

// Reactive data
const quillElement = ref(null);
const content = ref(props.modelValue);
const showStatus = ref(false);
const statusMessage = ref("");
const statusClass = ref("");
let quill = null;

// Quill configuration
const quillConfig = {
    theme: "snow",
    placeholder: props.placeholder,
    readOnly: props.readonly,
    modules: {
        toolbar: [
            [{ header: [2, 3, 4, false] }],
            ["bold", "italic", "underline"],
            [{ list: "ordered" }, { list: "bullet" }],
            ["link"],
            [{ align: [] }],
            ["clean"],
        ],
    },
    formats: [
        "header",
        "bold",
        "italic",
        "underline",
        "list",
        "link",
        "align",
    ],
};

// Initialize Quill editor
const initQuill = async () => {
    if (!quillElement.value) return;

    try {
        // Create Quill instance
        quill = new Quill(quillElement.value, quillConfig);

        // Set initial content
        if (props.modelValue) {
            quill.root.innerHTML = props.modelValue;
        }

        // Listen for text changes
        quill.on("text-change", () => {
            const html = quill.root.innerHTML;
            const isEmpty = html === "<p><br></p>" || html.trim() === "";
            const finalContent = isEmpty ? "" : html;

            content.value = finalContent;
            emit("update:modelValue", finalContent);
            emit("change", finalContent);

            // Dispatch global event for form integration
            window.dispatchEvent(
                new CustomEvent("rich-editor-change", {
                    detail: {
                        name: props.name,
                        content: finalContent,
                    },
                })
            );
        });

        // Listen for focus events
        quill.on("focus", () => {
            emit("focus");
        });

        // Listen for blur events
        quill.on("blur", () => {
            emit("blur");
        });

        showStatusMessage("Editor cargado correctamente", "success");
    } catch (error) {
        console.error("Error initializing Quill:", error);
        showStatusMessage("Error cargando editor", "error");
    }
};

// Watch for external changes
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue !== content.value && quill) {
            const currentContent = quill.root.innerHTML;
            const isEmpty =
                currentContent === "<p><br></p>" ||
                currentContent.trim() === "";
            const currentClean = isEmpty ? "" : currentContent;

            if (newValue !== currentClean) {
                quill.root.innerHTML = newValue || "";
                content.value = newValue;
            }
        }
    }
);

// Watch readonly prop
watch(
    () => props.readonly,
    (newReadonly) => {
        if (quill) {
            quill.enable(!newReadonly);
        }
    }
);

// Status message helper
const showStatusMessage = (message, type = "info") => {
    statusMessage.value = message;
    statusClass.value = `status-${type}`;
    showStatus.value = true;

    setTimeout(() => {
        showStatus.value = false;
    }, 3000);
};

// Lifecycle
onMounted(async () => {
    await nextTick();
    await initQuill();
});

onUnmounted(() => {
    if (quill) {
        quill = null;
    }
});
</script>

<style scoped>
.rich-text-editor-wrapper {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    overflow: hidden;
    background-color: white;
}

.quill-editor {
    background-color: white;
}

.editor-status {
    font-size: 0.875rem;
    padding: 0.5rem;
    border-top: 1px solid #e5e7eb;
}

.status-info {
    color: #2563eb;
    background-color: #eff6ff;
}

.status-success {
    color: #16a34a;
    background-color: #f0fdf4;
}

.status-error {
    color: #dc2626;
    background-color: #fef2f2;
}

/* Quill.js styling overrides */
.rich-text-editor-wrapper :deep(.ql-toolbar) {
    border: 0;
    border-bottom: 1px solid #e5e7eb;
    background-color: #f9fafb;
    padding: 8px 12px;
}

.rich-text-editor-wrapper :deep(.ql-container) {
    border: 0;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont,
        "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    font-size: 14px;
}

.rich-text-editor-wrapper :deep(.ql-editor) {
    padding: 1rem;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.6;
    color: #374151;
}

.rich-text-editor-wrapper :deep(.ql-editor.ql-blank::before) {
    color: #9ca3af;
    font-style: italic;
}

.rich-text-editor-wrapper :deep(.ql-editor h2) {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.rich-text-editor-wrapper :deep(.ql-editor h3) {
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}

.rich-text-editor-wrapper :deep(.ql-editor h4) {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}

.rich-text-editor-wrapper :deep(.ql-editor p) {
    margin-bottom: 0.5rem;
}

.rich-text-editor-wrapper :deep(.ql-editor ul),
.rich-text-editor-wrapper :deep(.ql-editor ol) {
    margin-bottom: 0.5rem;
    padding-left: 1.5rem;
}

.rich-text-editor-wrapper :deep(.ql-editor li) {
    margin-bottom: 0.25rem;
}

.rich-text-editor-wrapper :deep(.ql-editor a) {
    color: #2563eb;
    text-decoration: underline;
}

.rich-text-editor-wrapper :deep(.ql-editor strong) {
    font-weight: 600;
}

/* Toolbar button styling */
.rich-text-editor-wrapper :deep(.ql-toolbar .ql-stroke) {
    stroke: #6b7280;
}

.rich-text-editor-wrapper :deep(.ql-toolbar .ql-fill) {
    fill: #6b7280;
}

.rich-text-editor-wrapper :deep(.ql-toolbar button:hover .ql-stroke) {
    stroke: #374151;
}

.rich-text-editor-wrapper :deep(.ql-toolbar button:hover .ql-fill) {
    fill: #374151;
}

.rich-text-editor-wrapper :deep(.ql-toolbar button.ql-active .ql-stroke) {
    stroke: #3b82f6;
}

.rich-text-editor-wrapper :deep(.ql-toolbar button.ql-active .ql-fill) {
    fill: #3b82f6;
}

/* Dropdown styling */
.rich-text-editor-wrapper :deep(.ql-picker) {
    color: #374151;
}

.rich-text-editor-wrapper :deep(.ql-picker-options) {
    background-color: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.375rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.rich-text-editor-wrapper :deep(.ql-picker-item:hover) {
    background-color: #f9fafb;
}
</style>
