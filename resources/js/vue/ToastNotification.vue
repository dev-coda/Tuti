<template>
    <Teleport to="body">
        <div
            class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none"
        >
            <TransitionGroup
                name="toast"
                tag="div"
                class="flex flex-col gap-3"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'pointer-events-auto min-w-[300px] max-w-md rounded-lg shadow-lg p-4 flex items-start gap-3',
                        getToastClasses(toast.type)
                    ]"
                >
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <svg
                        v-if="toast.type === 'success'"
                        class="w-6 h-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else-if="toast.type === 'error'"
                        class="w-6 h-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="w-6 h-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <!-- Message -->
                <div class="flex-1">
                    <p class="font-medium" v-html="toast.message"></p>
                </div>

                <!-- Close button -->
                <button
                    @click="removeToast(toast.id)"
                    class="flex-shrink-0 text-current opacity-70 hover:opacity-100 transition-opacity"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";

const toasts = ref([]);
let toastIdCounter = 0;

const getToastClasses = (type) => {
    const baseClasses = "bg-white border-l-4";
    const typeClasses = {
        success: "bg-green-50 border-green-500 text-green-800",
        error: "bg-red-50 border-red-500 text-red-800",
        info: "bg-blue-50 border-blue-500 text-blue-800",
        warning: "bg-yellow-50 border-yellow-500 text-yellow-800",
    };
    return `${baseClasses} ${typeClasses[type] || typeClasses.info}`;
};

const showToast = (message, type = "info", duration = 5000) => {
    console.log('ToastNotification.showToast called:', { message, type, duration, toastsLength: toasts.value.length });
    const id = ++toastIdCounter;
    const toast = {
        id,
        message,
        type,
    };

    console.log('Pushing toast to array:', toast);
    toasts.value.push(toast);
    console.log('Toasts array after push:', toasts.value.length, toasts.value);

    // Auto remove after duration
    if (duration > 0) {
        setTimeout(() => {
            removeToast(id);
        }, duration);
    }

    return id;
};

const removeToast = (id) => {
    const index = toasts.value.findIndex((t) => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Expose methods globally
const handleToastEvent = (event) => {
    const { message, type, duration } = event.detail;
    showToast(message, type, duration);
};

onMounted(() => {
    console.log('ToastNotification component mounted');
    console.log('Initial toasts:', toasts.value);
    console.log('Component element:', document.querySelector('#toast-container'));
    
    // Listen for toast events
    window.addEventListener("toast:show", handleToastEvent);
    
    // Expose global function for easy access
    window.showToast = showToast;
    console.log('window.showToast exposed:', typeof window.showToast);
    
    // Test toast to verify component is working
    setTimeout(() => {
        console.log('Testing toast component...');
        showToast('Toast component initialized', 'info', 3000);
        // Check DOM after a moment
        setTimeout(() => {
            const teleported = document.body.querySelector('.fixed.bottom-4.right-4');
            console.log('Teleported element found:', teleported);
            console.log('Toasts in DOM:', document.querySelectorAll('[class*="rounded-lg shadow-lg"]').length);
        }, 100);
    }, 500);
});

onUnmounted(() => {
    window.removeEventListener("toast:show", handleToastEvent);
    if (window.showToast === showToast) {
        delete window.showToast;
    }
});

// Expose methods for parent components
defineExpose({
    showToast,
    removeToast,
});
</script>

<style scoped>
.toast-enter-active {
    transition: all 0.3s ease-out;
}

.toast-leave-active {
    transition: all 0.3s ease-in;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-move {
    transition: transform 0.3s ease;
}

@keyframes slide-in-right {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.animate-slide-in-right {
    animation: slide-in-right 0.3s ease-out;
}
</style>

