<template>
    <div class="relative">
        <button
            @click="toggleCart"
            class="text-white flex items-center hover:text-gray-200 transition-colors duration-200 relative"
            ref="cartButton"
        >
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
                    d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"
                />
            </svg>

            <!-- Badge -->
            <div
                v-if="cartItemCount > 0"
                class="absolute bottom-0 left-0 bg-orange-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center transition-all duration-300 transform translate-x-[-50%] translate-y-[25%]"
                :class="{ 'scale-110': isAnimating }"
            >
                {{ cartItemCount }}
            </div>
        </button>

        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 bg-black bg-opacity-50 z-40"
                @click="closeCart"
            ></div>
        </Transition>

        <!-- Cart Dropdown -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-72 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                @click.stop
            >
                <div class="p-4">
                    <div v-if="loading" class="flex justify-center py-8">
                        <div
                            class="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"
                        ></div>
                    </div>
                    <div
                        v-else-if="cartItems.length === 0"
                        class="text-center py-8 text-gray-500"
                    >
                        Tu carrito está vacío
                    </div>
                    <div v-else>
                        <h3 class="text-lg font-semibold mb-4">Tu Carrito</h3>
                        <div class="space-y-4">
                            <div
                                v-for="item in cartItems"
                                :key="item.id"
                                class="flex items-center space-x-4"
                            >
                                <img
                                    :src="item.image"
                                    :alt="item.name"
                                    class="w-16 h-16 object-cover rounded"
                                />
                                <div class="flex-1">
                                    <h4 class="font-medium">{{ item.name }}</h4>
                                    <p class="text-sm text-gray-500">
                                        Cantidad: {{ item.quantity }}
                                    </p>
                                    <p class="text-orange-500 font-semibold">
                                        $ {{ item.price }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t">
                            <a
                                href="/carrito"
                                class="block w-full bg-orange-500 text-white text-center py-2 px-4 rounded-md hover:bg-orange-600 transition-colors duration-200"
                            >
                                Ver Carrito
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";

const isOpen = ref(false);
const loading = ref(true);
const cartButton = ref(null);
const cartItems = ref([]);
const cartItemCount = ref(0);
const isAnimating = ref(false);

// Toggle cart dropdown
const toggleCart = async () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        await fetchCartItems();
    }
};

// Close cart dropdown
const closeCart = () => {
    isOpen.value = false;
};

// Fetch cart items
const fetchCartItems = async () => {
    loading.value = true;
    try {
        const response = await fetch("/api/cart");
        const data = await response.json();
        cartItems.value = data.items;
        cartItemCount.value = data.total_items;
        animateBadge();
    } catch (error) {
        console.error("Error fetching cart:", error);
    } finally {
        loading.value = false;
    }
};

// Animate badge when count changes
const animateBadge = () => {
    isAnimating.value = true;
    setTimeout(() => {
        isAnimating.value = false;
    }, 300);
};

// Close cart when pressing escape
const handleEscape = (event) => {
    if (event.key === "Escape") {
        closeCart();
    }
};

// Listen for cart updates
const handleCartUpdate = () => {
    fetchCartItems();
};

// Initial cart fetch and event listeners setup
onMounted(async () => {
    await fetchCartItems();
    document.addEventListener("keydown", handleEscape);

    // Listen for cart updates
    document.addEventListener("cart:updated", handleCartUpdate);

    // Set up polling to check for cart updates (every 30 seconds)
    const pollInterval = setInterval(fetchCartItems, 30000);

    // Store interval for cleanup
    const cleanup = () => {
        clearInterval(pollInterval);
        document.removeEventListener("cart:updated", handleCartUpdate);
        document.removeEventListener("keydown", handleEscape);
    };

    // Ensure cleanup runs on component unmount
    onUnmounted(cleanup);
});
</script>

<style scoped>
.menu-enter-active,
.menu-leave-active {
    transition: all 0.3s ease;
}

.menu-enter-from,
.menu-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
