<template>
    <div class="relative">
        <button
            @click="toggleMenu"
            class="text-white flex items-center space-x-2 hover:text-gray-200 transition-colors duration-200"
            ref="menuButton"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="w-8 h-8 transition-transform duration-300"
                :class="{ 'rotate-90': isOpen }"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5"
                />
            </svg>
            <span class="text-xl pt-1 pb-1 hidden lg:block">Menú</span>
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
                @click="closeMenu"
            ></div>
        </Transition>

        <!-- Menu -->
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
                class="absolute left-0 mt-2 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 max-h-[80vh] overflow-y-auto"
                @click.stop
            >
                <div class="py-1" role="menu" aria-orientation="vertical">
                    <!-- Categories Accordion -->
                    <div>
                        <div
                            v-for="(category, index) in categories"
                            :key="'cat-' + category.id"
                        >
                            <h2 :id="'accordion-heading-' + category.id">
                                <button
                                    type="button"
                                    class="flex items-center justify-between w-full py-2 px-4 font-medium text-gray-500 hover:bg-gray-50 transition-colors duration-200"
                                    :class="{
                                        'rounded-t': index === 0,
                                        'rounded-b':
                                            index === categories.length - 1,
                                        'text-orange-500 font-semibold':
                                            openCategories[category.id],
                                    }"
                                    @click="toggleCategory(category.id)"
                                >
                                    <div
                                        class="flex items-center space-x-2 uppercase"
                                    >
                                        <span>{{ category.name }}</span>
                                    </div>
                                    <svg
                                        class="w-3 h-3 transition-transform duration-200"
                                        :class="{
                                            'rotate-0':
                                                openCategories[category.id],
                                            'rotate-180':
                                                !openCategories[category.id],
                                        }"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 10 6"
                                    >
                                        <path
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5 5 1 1 5"
                                        />
                                    </svg>
                                </button>
                            </h2>
                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                enter-from-class="transform scale-y-95 opacity-0"
                                enter-to-class="transform scale-y-100 opacity-100"
                                leave-active-class="transition duration-200 ease-in"
                                leave-from-class="transform scale-y-100 opacity-100"
                                leave-to-class="transform scale-y-95 opacity-0"
                            >
                                <div
                                    v-show="openCategories[category.id]"
                                    class="px-3 py-3 bg-gray-50"
                                >
                                    <ul class="pl-7 text-sm space-y-2">
                                        <li>
                                            <a
                                                :href="
                                                    route(
                                                        'category',
                                                        category.slug
                                                    )
                                                "
                                                class="text-gray-600 hover:text-gray-900 transition-colors duration-200"
                                            >
                                                {{ category.name }}
                                            </a>
                                        </li>
                                        <li
                                            v-for="subcategory in category.children"
                                            :key="subcategory.id"
                                        >
                                            <a
                                                :href="
                                                    route(
                                                        'category2',
                                                        subcategory.slug
                                                    )
                                                "
                                                class="text-gray-600 hover:text-gray-900 transition-colors duration-200"
                                            >
                                                {{ subcategory.name }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </Transition>
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
const menuButton = ref(null);
const openCategories = ref({});

// Categories will be populated from Laravel
const categories = ref([]);

// Toggle menu and collapse all categories when closing
const toggleMenu = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        // Reset all open categories when menu closes
        openCategories.value = {};
    }
};

// Close menu and collapse all categories
const closeMenu = () => {
    isOpen.value = false;
    openCategories.value = {};
};

// Toggle category accordion
const toggleCategory = (categoryId) => {
    openCategories.value[categoryId] = !openCategories.value[categoryId];
};

// Close menu when pressing escape
const handleEscape = (event) => {
    if (event.key === "Escape") {
        closeMenu();
    }
};

// Helper function to generate Laravel routes
const route = (name, param) => {
    switch (name) {
        case "category":
            return `/categoria-producto/${param}`;
        case "category2":
            return `/categoria-producto/${param}`;
        default:
            return "/";
    }
};

// Fetch categories when component mounts
onMounted(async () => {
    try {
        const response = await fetch("/api/categories");
        const data = await response.json();
        categories.value = data;
    } catch (error) {
        console.error("Error fetching categories:", error);
    }

    document.addEventListener("keydown", handleEscape);
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleEscape);
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
