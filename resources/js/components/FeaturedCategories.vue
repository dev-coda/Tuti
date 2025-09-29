<template>
    <div class="xl:col-span-12 col-span-12">
        <h4
            class="col-span-12 text-slate-700 text-3xl font-semibold mb-2 md:mb-3 mt-4 md:mt-12 flex justify-center"
        >
            {{ sectionTitle }}
        </h4>
        <div class="xl:col-span-12 col-span-12 pb-2 md:pb-6">
            <div v-if="error" class="text-red-500 text-center mb-4">
                {{ error }}
            </div>
            <div v-else-if="loading" class="text-center mb-4">
                Cargando categorías...
            </div>
            <template v-else>
                <div
                    class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 md:gap-5"
                >
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="border border-gray-100 rounded-lg overflow-hidden hover:scale-105 transition duration-300 cursor-pointer relative bg-gray-100"
                    >
                        <!-- Image Container -->
                        <div class="relative h-40 w-full max-w-sm mx-auto">
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
                                    class="w-12 h-12 text-gray-400"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                    />
                                </svg>
                            </div>
                        </div>

                        <!-- Category Name Overlay -->
                        <div
                            class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4"
                        >
                            <a :href="category.url" class="block">
                                <div
                                    class="bg-orange-500 text-white font-semibold text-sm sm:text-base p-3 rounded-lg text-center"
                                >
                                    <span class="block truncate">
                                        {{ category.name }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
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
