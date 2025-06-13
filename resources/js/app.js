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
import FilterSortDropdowns from "./components/FilterSortDropdowns.vue";
import SubmitOrderButton from "./components/SubmitOrderButton.vue";

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

// Mount FilterSortDropdowns component
const filterSortApp = createApp(FilterSortDropdowns);
filterSortApp.mount("#filter-sort-dropdowns");

// Mount SubmitOrderButton component
const submitOrderButtonApp = createApp(SubmitOrderButton);
const submitOrderButtonEl = document.getElementById("submit-order-button");
if (submitOrderButtonEl) {
    submitOrderButtonApp.mount("#submit-order-button");
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
