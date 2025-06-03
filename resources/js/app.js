import "./bootstrap";

import c from "currency.js";

window.currency = function (value) {
    console.log(value);
    return c(value, { symbol: "$", precision: 0, separator: "." }).format();
};

import { createApp } from "vue";
import combinedProducts from "./vue/combinedProducts.vue";
import HelloWorld from "./vue/HelloWorld.vue";
import MobileMenu from "./vue/MobileMenu.vue";
import CartWidget from "./vue/CartWidget.vue";
import FeaturedProducts from "./components/FeaturedProducts.vue";
import FilterSortDropdowns from "./components/FilterSortDropdowns.vue";

// Only initialize Alpine if it hasn't been initialized yet
import Alpine from "alpinejs";
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

// Create a single Vue app instance
const app = createApp({});

// Register all components globally
app.component("combined-products", combinedProducts);
app.component("hello-world", HelloWorld);
app.component("mobile-menu", MobileMenu);
app.component("cart-widget", CartWidget);
app.component("featured-products", FeaturedProducts);
app.component("filter-sort-dropdowns", FilterSortDropdowns);

// Mount the app to the root element
if (document.getElementById("app")) {
    app.mount("#app");
}

// Sidebar code
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
