<template>
    <div class="w-full">
        <h4
            class="text-center text-slate-600 text-xl md:text-2xl font-semibold mb-4 md:mb-6 mt-6 md:mt-10"
        >
            {{ sectionTitle }}
        </h4>
        <div class="pb-2 md:pb-6">
            <div v-if="error" class="text-red-500 text-center mb-4">
                {{ error }}
            </div>
            <div v-else-if="loading" class="text-center mb-4">
                Cargando categorías...
            </div>
            <template v-else>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                    <a
                        v-for="category in categories"
                        :key="category.id"
                        :href="category.url"
                        class="group block rounded-2xl overflow-hidden border border-gray-200 bg-white shadow-sm transition-transform duration-300 hover:-translate-y-0.5"
                    >
                        <div class="relative h-36 md:h-44 w-full">
                            <img
                                v-if="category.image"
                                :src="category.image"
                                :alt="category.name"
                                class="w-full h-full object-cover"
                            />
                            <div
                                v-else
                                class="w-full h-full bg-gray-200 flex items-center justify-center"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-10 h-10 text-gray-400"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                    />
                                </svg>
                            </div>
                        </div>
                        <div class="bg-orange-500 text-white text-[11px] md:text-xs font-semibold text-center py-2 uppercase tracking-wide">
                            <span class="block truncate px-2">
                                {{ category.name }}
                            </span>
                        </div>
                    </a>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            categories: [],
            loading: true,
            error: null,
            sectionTitle: "Categorías", // Default title
        };
    },
    methods: {
        async fetchSectionTitle() {
            try {
                const response = await fetch("/api/categories/section-title");
                if (response.ok) {
                    const data = await response.json();
                    this.sectionTitle = data.title;
                }
            } catch (error) {
                console.error("Error fetching section title:", error);
                // Keep default title if fetch fails
            }
        },
        async fetchCategories() {
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch("/api/categories/featured");
                if (!response.ok) {
                    throw new Error("Error al cargar las categorías");
                }
                const data = await response.json();
                console.log("Categories API Response:", data); // Debug log
                this.categories = data.categories || [];
                if (this.categories.length === 0) {
                    this.error = "No hay categorías disponibles";
                }
            } catch (error) {
                console.error("Error fetching categories:", error);
                this.error = "Error al cargar las categorías";
            } finally {
                this.loading = false;
            }
        },
    },
    async mounted() {
        await this.fetchSectionTitle();
        await this.fetchCategories();
    },
};
</script>
