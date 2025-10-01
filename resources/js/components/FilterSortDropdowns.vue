<template>
    <div class="md:col-span-12 pt-3 max-w-[90vw] uppercase font-semibold">
        <div
            class="flex md:justify-between md:items-center flex-row pr-3 space-x-2 md:space-x-0 md:space-y-0"
        >
            <!-- Sort Dropdown -->
            <div class="relative">
                <button
                    @click="toggleSort"
                    class="flex items-center justify-between w-full md:w-64 px-2 md:px-4 py-2 hover:bg-gray-50 rounded-md focus:outline-none text-sm md:text-base"
                >
                    <span class="text-gray-700 flex items-center gap-2">
                        <svg
                            class="w-4 h-4 md:w-5 md:h-5 ml-1 md:ml-2"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                            />
                        </svg>
                        ORDENAR
                    </span>
                    <svg
                        class="w-4 h-4 ml-2 transition-transform duration-200"
                        :class="{ 'rotate-180': isSortOpen }"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <div
                    v-show="isSortOpen"
                    class="absolute z-10 w-full mt-1 bg-white rounded-md shadow-lg"
                >
                    <div class="py-1">
                        <a
                            v-for="option in sortOptions"
                            :key="option.value"
                            :href="option.url"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                            :class="{
                                'bg-gray-50 text-orange-500':
                                    option.value === currentSort,
                            }"
                        >
                            {{ option.label }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filter Dropdown -->
            <div class="relative">
                <button
                    @click="toggleFilter"
                    class="flex items-center justify-between w-full md:w-64 px-2 md:px-4 py-2 hover:bg-gray-50 rounded-md focus:outline-none text-sm md:text-base"
                >
                    <span class="text-gray-700">FILTRAR</span>
                    <svg
                        class="w-4 h-4 ml-2 transition-transform duration-200"
                        :class="{ 'rotate-180': isFilterOpen }"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <div
                    v-show="isFilterOpen"
                    class="absolute z-10 w-full mt-1 bg-white rounded-md shadow-lg"
                >
                    <div class="py-1">
                        <div
                            class="px-3 py-2 text-xs font-semibold text-gray-400"
                        >
                            Marca
                        </div>
                        <a
                            v-for="brand in brands"
                            :key="'brand-' + brand.id"
                            :href="brand.url"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                            :class="{
                                'bg-gray-50 text-orange-500':
                                    brand.id === currentBrandId,
                            }"
                        >
                            {{ brand.name }}
                        </a>

                        <div class="border-t border-gray-100 mt-2"></div>

                        <div
                            class="px-3 py-2 text-xs font-semibold text-gray-400"
                        >
                            Categoría
                        </div>
                        <a
                            v-for="category in categories"
                            :key="'cat-' + category.id"
                            :href="category.url"
                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                            :class="{
                                'bg-gray-50 text-orange-500':
                                    category.id === currentCategoryId,
                            }"
                        >
                            {{ category.name }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";

// Get the root element
const el = document.getElementById("filter-sort-dropdowns");

// Parse data attributes
const currentSort = el.dataset.currentSort;
const currentBrandId =
    el.dataset.currentBrandId === "null"
        ? null
        : Number(el.dataset.currentBrandId);
const currentCategoryId =
    el.dataset.currentCategoryId === "null"
        ? null
        : Number(el.dataset.currentCategoryId);
const sortOptions = JSON.parse(el.dataset.sortOptions);
const brands = JSON.parse(el.dataset.brands);
const categories = JSON.parse(el.dataset.categories);

const isSortOpen = ref(false);
const isFilterOpen = ref(false);

const toggleSort = () => {
    isSortOpen.value = !isSortOpen.value;
    if (isSortOpen.value) isFilterOpen.value = false;
};

const toggleFilter = () => {
    isFilterOpen.value = !isFilterOpen.value;
    if (isFilterOpen.value) isSortOpen.value = false;
};

// Close dropdowns when clicking outside
const handleClickOutside = (event) => {
    const target = event.target;
    if (!target.closest(".relative")) {
        isSortOpen.value = false;
        isFilterOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>
