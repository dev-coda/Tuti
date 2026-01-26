<template>
    <div class="w-full">
        <h4
            class="text-center text-slate-600 text-xl md:text-2xl font-semibold mb-4 md:mb-6 mt-8 md:mt-12"
        >
            {{ sectionTitle }}
        </h4>
        <div class="pb-6">
            <div v-if="error" class="text-red-500 text-center mb-4">
                {{ error }}
            </div>
            <div v-else-if="loading" class="text-center mb-4">
                Cargando productos...
            </div>
            <template v-else>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
                    <template
                        v-for="product in visibleProducts"
                        :key="product.id"
                    >
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 flex flex-col">
                            <div
                                class="flex w-full items-center justify-center py-2 text-gray-400 flex-grow relative"
                            >
                                <a
                                    :href="product.url"
                                    class="h-36 md:h-44 block w-full bg-contain bg-center bg-no-repeat hover:scale-105 transition duration-300 cursor-pointer"
                                    :style="{
                                        backgroundImage: `url(${product.image})`,
                                    }"
                                >
                                </a>
                                <div
                                    v-if="product.tag"
                                    class="absolute top-2 right-2 z-10 px-2 py-1 text-[10px] font-semibold text-white bg-orange-600 rounded-full shadow"
                                >
                                    {{ product.tag.content }}
                                </div>
                            </div>

                            <div class="mt-2 flex flex-col">
                                <a
                                    :href="product.url"
                                    class="text-gray-800 font-medium text-sm md:text-base leading-tight"
                                    >{{ product.name }}</a
                                >
                                <p
                                    v-if="product.sku"
                                    class="text-xs text-blue-600 mt-1"
                                >
                                    {{ product.sku }}
                                </p>
                                <div class="flex items-baseline gap-2 mt-2">
                                    <span
                                        class="text-orange-500 font-bold text-lg md:text-xl"
                                    >
                                        ${{
                                            formatPrice(
                                                product.final_price.price
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="product.final_price.has_discount"
                                        class="line-through text-gray-400 text-xs md:text-sm font-semibold"
                                    >
                                        ${{
                                            formatPrice(product.final_price.old)
                                        }}
                                    </span>
                                </div>
                                <p v-if="product.final_price.perItemPrice" class="text-xs text-gray-500 mt-1">
                                    (Und. x) ${{
                                        formatPrice(
                                            product.final_price.perItemPrice
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-col items-center">
                                <button
                                    @click="addToCart(product)"
                                    :disabled="addingToCart === product.id || vacationMode.enabled"
                                    class="bg-orange-500 hover:bg-orange-600 text-white text-sm md:text-base font-semibold rounded-full px-4 py-2 w-full mt-4 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span v-if="addingToCart !== product.id">¡Lo quiero!</span>
                                    <span v-else class="animate-spin">⏳</span>
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        class="w-5 h-5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"
                                        />
                                    </svg>
                                </button>
                                <p v-if="vacationMode.enabled" class="text-xs text-gray-600 text-center mt-2 max-w-xs">
                                    {{ vacationMode.message }}
                                </p>
                            </div>
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
                        class="w-3 h-3 rounded-full transition-all duration-300 border border-gray-300"
                        :class="
                            currentPage === page - 1
                                ? 'bg-gray-500'
                                : 'bg-gray-200'
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
            sectionTitle: "Productos Destacados", // Default title
            addingToCart: null,
            vacationMode: {
                enabled: false,
                message: null,
            },
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
        async fetchVacationMode() {
            try {
                const response = await fetch("/api/vacation-mode");
                if (response.ok) {
                    const data = await response.json();
                    // Use 'active' to check if vacation mode is currently in effect (within date range)
                    this.vacationMode = {
                        enabled: data.active || false,
                        message: data.message || null,
                    };
                }
            } catch (error) {
                console.error("Error fetching vacation mode:", error);
            }
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
                const response = await fetch("/api/products/latest");
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
        async addToCart(product) {
            console.log('[FeaturedProducts] addToCart called for product:', product.id, product.name);
            
            if (this.addingToCart === product.id) {
                console.log('[FeaturedProducts] Already adding this product, ignoring');
                return;
            }
            
            // Check vacation mode
            if (this.vacationMode.enabled) {
                console.log('[FeaturedProducts] Vacation mode is enabled, showing message');
                if (window.showToast && typeof window.showToast === 'function') {
                    window.showToast(this.vacationMode.message, 'error', 5000);
                } else {
                    console.error('[FeaturedProducts] window.showToast not available for vacation message');
                    alert(this.vacationMode.message);
                }
                return;
            }
            
            this.addingToCart = product.id;
            console.log('[FeaturedProducts] Starting add to cart request');
            
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (!csrfToken) {
                    throw new Error('CSRF token not found');
                }
                
                const formData = new FormData();
                formData.append('quantity', product.step || 1);
                
                console.log('[FeaturedProducts] Sending POST to /carrito/agregrar/' + product.id);
                
                const response = await fetch(`/carrito/agregrar/${product.id}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken.content,
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                console.log('[FeaturedProducts] Response status:', response.status);
                
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    console.error('[FeaturedProducts] Non-JSON response:', text.substring(0, 200));
                    throw new Error('Error al procesar la solicitud. Por favor intenta de nuevo.');
                }
                
                const data = await response.json();
                console.log('[FeaturedProducts] Response data:', data);
                
                if (response.ok && data.success) {
                    console.log('[FeaturedProducts] Product added successfully');
                    // Show success toast
                    if (window.showToast && typeof window.showToast === 'function') {
                        window.showToast('Producto agregado', 'success', 3000);
                    } else {
                        console.error('[FeaturedProducts] window.showToast not available');
                    }
                    
                    // Open cart modal if it exists
                    setTimeout(() => {
                        if (window.openCart) {
                            window.openCart();
                        }
                    }, 100);
                    
                    // Dispatch cart update event
                    window.dispatchEvent(new CustomEvent('cart:updated'));
                } else {
                    // Show error toast
                    const errorMessage = data.message || data.error || 'Error al agregar el producto';
                    console.log('[FeaturedProducts] Error from server:', errorMessage);
                    if (window.showToast && typeof window.showToast === 'function') {
                        window.showToast(errorMessage, 'error', 5000);
                    } else {
                        console.error('[FeaturedProducts] window.showToast not available for error');
                        alert(errorMessage);
                    }
                }
            } catch (error) {
                console.error('[FeaturedProducts] Error adding to cart:', error);
                if (window.showToast && typeof window.showToast === 'function') {
                    window.showToast(error.message || 'Error al agregar el producto', 'error', 5000);
                } else {
                    console.error('[FeaturedProducts] window.showToast not available');
                    alert(error.message || 'Error al agregar el producto');
                }
            } finally {
                this.addingToCart = null;
            }
        },
    },
    async mounted() {
        await this.fetchVacationMode();
        await this.fetchSectionTitle();
        await this.fetchProducts();
    },
};
</script>
