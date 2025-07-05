<template>
    <button
        type="submit"
        :disabled="isLoading || disabled"
        :class="buttonClasses"
    >
        <span v-if="!isLoading" class="flex items-center justify-center">
            {{ text }}
        </span>
        <span v-else class="flex items-center justify-center">
            <svg
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
            Procesando...
        </span>
    </button>
</template>

<script>
export default {
    name: "SubmitOrderButton",
    props: {
        text: {
            type: String,
            default: "Realizar Pedido",
        },
        disabled: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            isLoading: false,
            hasSubmitted: false,
        };
    },
    computed: {
        buttonClasses() {
            const baseClasses =
                "w-full rounded py-3 px-5 mt-5 block text-center transition-all duration-200";

            if (this.isLoading || this.disabled) {
                return `${baseClasses} bg-gray-400 text-gray-200 cursor-not-allowed`;
            }

            return `${baseClasses} bg-orange-600 text-white hover:bg-orange-900`;
        },
    },
    mounted() {
        // Find the parent form and listen for submit event
        const form = this.$el.closest("form");
        if (form) {
            // Add submit event listener to the form
            form.addEventListener("submit", (e) => {
                // If already loading or disabled, prevent submission
                if (this.isLoading || this.hasSubmitted || this.disabled) {
                    e.preventDefault();
                    return false;
                }

                // Set loading state
                this.isLoading = true;
                this.hasSubmitted = true;

                // Allow the form to submit
                return true;
            });
        }
    },
};
</script>
