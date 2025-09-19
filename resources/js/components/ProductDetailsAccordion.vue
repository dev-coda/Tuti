<template>
    <div class="w-full">
        <!-- Accordion Item 1: Descripción -->
        <div
            v-if="description"
            class="border border-gray-200 rounded-lg mb-2 overflow-hidden"
        >
            <button
                @click="toggleSection('description')"
                class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-inset transition-colors duration-200"
                :class="{ 'bg-gray-100': openSections.description }"
            >
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Descripción
                    </h3>
                    <svg
                        class="w-5 h-5 text-gray-500 transform transition-transform duration-200"
                        :class="{ 'rotate-180': openSections.description }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </div>
            </button>
            <transition
                name="accordion"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @before-leave="onBeforeLeave"
                @leave="onLeave"
            >
                <div
                    v-show="openSections.description"
                    class="accordion-content"
                >
                    <div
                        class="px-6 py-4 text-gray-700 leading-relaxed content-area"
                    >
                        <div v-html="formatText(description)"></div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Accordion Item 2: Ficha técnica -->
        <div
            v-if="technicalSpecifications"
            class="border border-gray-200 rounded-lg mb-2 overflow-hidden"
        >
            <button
                @click="toggleSection('technical')"
                class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-inset transition-colors duration-200"
                :class="{ 'bg-gray-100': openSections.technical }"
            >
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Ficha técnica
                    </h3>
                    <svg
                        class="w-5 h-5 text-gray-500 transform transition-transform duration-200"
                        :class="{ 'rotate-180': openSections.technical }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </div>
            </button>
            <transition
                name="accordion"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @before-leave="onBeforeLeave"
                @leave="onLeave"
            >
                <div v-show="openSections.technical" class="accordion-content">
                    <div
                        class="px-6 py-4 text-gray-700 leading-relaxed content-area"
                    >
                        <div v-html="formatText(technicalSpecifications)"></div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Accordion Item 3: Garantía -->
        <div
            v-if="warranty"
            class="border border-gray-200 rounded-lg mb-2 overflow-hidden"
        >
            <button
                @click="toggleSection('warranty')"
                class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-inset transition-colors duration-200"
                :class="{ 'bg-gray-100': openSections.warranty }"
            >
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Garantía
                    </h3>
                    <svg
                        class="w-5 h-5 text-gray-500 transform transition-transform duration-200"
                        :class="{ 'rotate-180': openSections.warranty }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </div>
            </button>
            <transition
                name="accordion"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @before-leave="onBeforeLeave"
                @leave="onLeave"
            >
                <div v-show="openSections.warranty" class="accordion-content">
                    <div
                        class="px-6 py-4 text-gray-700 leading-relaxed content-area"
                    >
                        <div v-html="formatText(warranty)"></div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Accordion Item 4: Otra información -->
        <div
            v-if="otherInformation"
            class="border border-gray-200 rounded-lg mb-2 overflow-hidden"
        >
            <button
                @click="toggleSection('other')"
                class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-inset transition-colors duration-200"
                :class="{ 'bg-gray-100': openSections.other }"
            >
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Otra información
                    </h3>
                    <svg
                        class="w-5 h-5 text-gray-500 transform transition-transform duration-200"
                        :class="{ 'rotate-180': openSections.other }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </div>
            </button>
            <transition
                name="accordion"
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @before-leave="onBeforeLeave"
                @leave="onLeave"
            >
                <div v-show="openSections.other" class="accordion-content">
                    <div
                        class="px-6 py-4 text-gray-700 leading-relaxed content-area"
                    >
                        <div v-html="formatText(otherInformation)"></div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script>
import { ref, reactive, onMounted } from "vue";

export default {
    name: "ProductDetailsAccordion",
    props: {
        description: {
            type: String,
            default: "",
        },
        technicalSpecifications: {
            type: String,
            default: "",
        },
        warranty: {
            type: String,
            default: "",
        },
        otherInformation: {
            type: String,
            default: "",
        },
    },
    setup(props) {
        const openSections = reactive({
            description: true, // First section open by default
            technical: false,
            warranty: false,
            other: false,
        });

        const toggleSection = (section) => {
            openSections[section] = !openSections[section];
        };

        const formatText = (text) => {
            if (!text || text.trim() === "") return "";

            // If text already contains HTML tags, return as-is (rich text content)
            if (text.includes("<") && text.includes(">")) {
                return text;
            }

            // Convert line breaks to <br> tags and preserve formatting
            return text.replace(/\n/g, "<br>").replace(/\r\n/g, "<br>");
        };

        // Animation methods for smooth accordion transitions
        const onEnter = (el) => {
            el.style.height = "0";
        };

        const onAfterEnter = (el) => {
            el.style.height = "auto";
        };

        const onBeforeLeave = (el) => {
            el.style.height = el.scrollHeight + "px";
        };

        const onLeave = (el) => {
            // Force a reflow
            el.offsetHeight;
            el.style.height = "0";
        };

        return {
            openSections,
            toggleSection,
            formatText,
            onEnter,
            onAfterEnter,
            onBeforeLeave,
            onLeave,
        };
    },
};
</script>

<style scoped>
.accordion-content {
    overflow: hidden;
    transition: height 0.3s ease;
}

.accordion-enter-active,
.accordion-leave-active {
    transition: height 0.3s ease;
}

.accordion-enter-from,
.accordion-leave-to {
    height: 0;
}

/* Smooth focus transitions */
button:focus {
    outline: none;
}

/* Content styling for rich text */
.content-area h1 {
    font-size: 1.875rem;
    font-weight: 700;
    margin-top: 1.5rem;
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
    line-height: 1.75;
}

.content-area ul,
.content-area ol {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.content-area li {
    margin-bottom: 0.5rem;
    line-height: 1.6;
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

/* Responsive adjustments */
@media (max-width: 640px) {
    .accordion-content {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>
