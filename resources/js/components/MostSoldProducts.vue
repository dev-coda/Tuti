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
                Cargando productos...
            </div>
            <template v-else>
                <div class="grid grid-cols-1 xl:grid-cols-4 gap-0">
                    <template
                        v-for="product in visibleProducts"
                        :key="product.id"
                    >
                        <div class="rounded flex flex-col p-6 max-w-[90vw]">
                            <div
                                class="flex w-full items-center justify-center py-2 text-gray-400 flex-grow"
                            >
                                <a
                                    :href="product.url"
                                    class="flex-grow-1 h-40 block w-full bg-cover bg-center hover:scale-110 transition duration-500 cursor-pointer object-cover"
                                    :style="{
                                        backgroundImage: `url(${product.image})`,
                                    }"
                                >
                                </a>
                            </div>

                            <div class="p-2 flex flex-col">
                                <a
                                    :href="product.url"
                                    class="text-[#180F09] font-semibold text-lg"
                                    >{{ product.name }}</a
                                >
                                <p
                                    v-if="product.sku"
                                    class="text-slate-500 text-md"
                                >
                                    {{ product.sku }}
                                </p>
                                <span
                                    v-if="product.final_price.has_discount"
                                    class="text-slate-400 text-lg"
                                    ><small
                                        class="line-through text-lg text-slate-400 font-semibold"
                                        >${{
                                            formatPrice(product.final_price.old)
                                        }} </small
                                    >Antes</span
                                >
                                <span class="text-slate-400 text-lg"
                                    ><small
                                        class="text-lg text-orange-500 font-semibold"
                                        >${{
                                            formatPrice(
                                                product.final_price.price
                                            )
                                        }}
                                        Ahora
                                    </small></span
                                >
                                <p v-if="product.final_price.perItemPrice">
                                    (Und. x) ${{
                                        formatPrice(
                                            product.final_price.perItemPrice
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="product.brand"
                                    class="text-slate-500 text-md"
                                >
                                    {{ product.brand.name }}
                                </p>
                                <p
                                    v-if="product.category"
                                    class="text-[#180F09] text-xs"
                                >
                                    {{ product.category.name }}
                                </p>
                            </div>
                            <a
                                :href="product.url"
                                class="bg-secondary p-2 mt-4 text-white hover:bg-gray2 flex px-4 text-xl font-semibold rounded-full items-center justify-center w-52 mx-auto"
                            >
                                <span>¡Lo quiero!</span>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-8 h-8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
                                    />
                                </svg>
                            </a>
                        </div>
                    </template>
                </div>

                <!-- Orange Dot Navigation -->
                <div
                    v-if="products.length > productsPerPage"
                    class="flex justify-center mt-6 gap-2"
                >
                    <button
                        v-for="page in totalPages"
                        :key="page"
                        @click="currentPage = page - 1"
                        class="w-4 h-4 rounded-full transition-all duration-300 border-2 border-black"
                        style="border-color: darkgray; border-width: 2px"
                        :class="
                            currentPage === page - 1
                                ? 'bg-gray-500'
                                : 'bg-orange-200 '
                        "
                        :aria-label="'Go to page ' + page"
                    ></button>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            products: [],
            currentPage: 0,
            productsPerPage: 4,
            loading: true,
            error: null,
            sectionTitle: "Productos Más Vendidos", // Default title
        };
    },
    computed: {
        totalPages() {
            return Math.ceil(this.products.length / this.productsPerPage);
        },
        visibleProducts() {
            const start = this.currentPage * this.productsPerPage;
            return this.products.slice(start, start + this.productsPerPage);
        },
    },
    methods: {
        formatPrice(price) {
            return new Intl.NumberFormat("es-CO", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(price);
        },
        async fetchSectionTitle() {
            try {
                const response = await fetch("/api/products/section-title");
                if (response.ok) {
                    const data = await response.json();
                    this.sectionTitle = data.title;
                }
            } catch (error) {
                console.error("Error fetching section title:", error);
                // Keep default title if fetch fails
            }
        },
        async fetchProducts() {
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch("/api/products/most-sold");
                if (!response.ok) {
                    throw new Error("Error al cargar los productos");
                }
                const data = await response.json();
                console.log("API Response:", data); // Debug log
                this.products = data.products || [];
                if (this.products.length === 0) {
                    this.error = "No hay productos disponibles";
                }
            } catch (error) {
                console.error("Error fetching products:", error);
                this.error = "Error al cargar los productos";
            } finally {
                this.loading = false;
            }
        },
    },
    async mounted() {
        await this.fetchSectionTitle();
        await this.fetchProducts();
    },
};
</script>
