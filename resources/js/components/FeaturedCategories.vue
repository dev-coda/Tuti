<template>
    <div class="xl:col-span-12 col-span-12">
        <h4
            class="col-span-12 text-slate-700 text-3xl font-semibold mb-3 mt-12 flex justify-center"
        >
            {{ sectionTitle }}
        </h4>
        <div class="xl:col-span-12 col-span-12 pb-6">
            <div v-if="error" class="text-red-500 text-center mb-4">
                {{ error }}
            </div>
            <div v-else-if="loading" class="text-center mb-4">
                Cargando categorías...
            </div>
            <template v-else>
                <div
                    class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"
                >
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="border border-gray-100 rounded bg-cover bg-center hover:scale-110 transition duration-500 cursor-pointer relative"
                        :style="{ backgroundImage: `url(${category.image})` }"
                    >
                        <div
                            class="text-gray-400 h-40 flex items-end justify-center"
                        >
                            <a
                                :href="category.url"
                                class="w-full h-full flex items-end justify-center"
                            >
                                <div
                                    class="bg-orange-500 text-white font-semibold text-lg p-4 flex flex-col h-12 w-64 mx-auto justify-center items-center rounded-3xl mb-6"
                                >
                                    <span class="mx-auto">
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
