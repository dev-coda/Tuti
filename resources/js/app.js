import "./bootstrap";

import c from "currency.js";

window.currency = function (value) {
    console.log(value);
    return c(value, { symbol: "$", precision: 0, separator: "." }).format();
};

import Alpine from "alpinejs";

window.Alpine = Alpine;

Alpine.start();

import { createApp } from "vue";
import combinedProducts from "./vue/combinedProducts.vue";
import HelloWorld from "./vue/HelloWorld.vue";
import MobileMenu from "./vue/MobileMenu.vue";
import CartWidget from "./vue/CartWidget.vue";
import FeaturedProducts from "./components/FeaturedProducts.vue";
import MostSoldProducts from "./components/MostSoldProducts.vue";
import FeaturedCategories from "./components/FeaturedCategories.vue";
import MostPopularCategories from "./components/MostPopularCategories.vue";
import FilterSortDropdowns from "./components/FilterSortDropdowns.vue";
import SubmitOrderButton from "./components/SubmitOrderButton.vue";
import ProductImageReorder from "./components/ProductImageReorder.vue";
import ProductDetailsAccordion from "./components/ProductDetailsAccordion.vue";

// Mount combinedProducts component
const productsApp = createApp(combinedProducts);
productsApp.mount("#combinedProducts");

// Mount HelloWorld component
const helloApp = createApp(HelloWorld);
helloApp.mount("#hello-world");

// Mount MobileMenu component
const menuApp = createApp(MobileMenu);
menuApp.mount("#mobile-menu");

// Mount CartWidget component
const cartApp = createApp(CartWidget);
cartApp.mount("#cart-widget");

// Mount FeaturedProducts component
const featuredProductsApp = createApp(FeaturedProducts);
featuredProductsApp.mount("#featured-products");

// Mount MostSoldProducts component
const mostSoldProductsApp = createApp(MostSoldProducts);
mostSoldProductsApp.mount("#most-sold-products");

// Mount FeaturedCategories component
const featuredCategoriesApp = createApp(FeaturedCategories);
featuredCategoriesApp.mount("#featured-categories");

// Mount MostPopularCategories component
const mostPopularCategoriesApp = createApp(MostPopularCategories);
mostPopularCategoriesApp.mount("#most-popular-categories");

// Mount FilterSortDropdowns component
const filterSortApp = createApp(FilterSortDropdowns);
filterSortApp.mount("#filter-sort-dropdowns");

// Mount SubmitOrderButton component
const submitOrderButtonApp = createApp(SubmitOrderButton);
const submitOrderButtonEl = document.getElementById("submit-order-button");
if (submitOrderButtonEl) {
    submitOrderButtonApp.mount("#submit-order-button");
}

// Mount ProductImageReorder when container exists
const imageReorderEl = document.getElementById("product-image-reorder");
if (imageReorderEl) {
    const props = {
        images: JSON.parse(imageReorderEl.dataset.images || "[]"),
        reorderUrl: imageReorderEl.dataset.reorderUrl,
        csrf: document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute("content"),
    };
    const imageReorderApp = createApp(ProductImageReorder, props);
    imageReorderApp.mount("#product-image-reorder");
}

// Mount ProductDetailsAccordion when container exists
const accordionEl = document.getElementById("product-details-accordion");
if (accordionEl) {
    const props = {
        description: accordionEl.dataset.description || "",
        technicalSpecifications:
            accordionEl.dataset.technicalSpecifications || "",
        warranty: accordionEl.dataset.warranty || "",
        otherInformation: accordionEl.dataset.otherInformation || "",
    };
    const accordionApp = createApp(ProductDetailsAccordion, props);
    accordionApp.mount("#product-details-accordion");
}

const sidebar = document.getElementById("sidebar");

if (sidebar) {
    const toggleSidebarMobile = (
        sidebar,
        sidebarBackdrop,
        toggleSidebarMobileHamburger,
        toggleSidebarMobileClose
    ) => {
        sidebar.classList.toggle("hidden");
        sidebarBackdrop.classList.toggle("hidden");
        toggleSidebarMobileHamburger.classList.toggle("hidden");
        toggleSidebarMobileClose.classList.toggle("hidden");
    };

    const toggleSidebarMobileEl = document.getElementById(
        "toggleSidebarMobile"
    );
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");
    const toggleSidebarMobileHamburger = document.getElementById(
        "toggleSidebarMobileHamburger"
    );
    const toggleSidebarMobileClose = document.getElementById(
        "toggleSidebarMobileClose"
    );

    toggleSidebarMobileEl.addEventListener("click", () => {
        toggleSidebarMobile(
            sidebar,
            sidebarBackdrop,
            toggleSidebarMobileHamburger,
            toggleSidebarMobileClose
        );
    });

    sidebarBackdrop.addEventListener("click", () => {
        toggleSidebarMobile(
            sidebar,
            sidebarBackdrop,
            toggleSidebarMobileHamburger,
            toggleSidebarMobileClose
        );
    });
}
