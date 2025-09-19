import "./bootstrap";

// Import Quill styles
import "quill/dist/quill.snow.css";

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
import RichTextEditor from "./components/RichTextEditor.vue";

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
    // Handle content decoding for product details
    let description = accordionEl.dataset.description || "";
    let technicalSpecifications =
        accordionEl.dataset.technicalSpecifications || "";
    let warranty = accordionEl.dataset.warranty || "";
    let otherInformation = accordionEl.dataset.otherInformation || "";

    if (accordionEl.dataset.contentEncoding === "json") {
        try {
            description = description ? JSON.parse(description) : "";
            technicalSpecifications = technicalSpecifications
                ? JSON.parse(technicalSpecifications)
                : "";
            warranty = warranty ? JSON.parse(warranty) : "";
            otherInformation = otherInformation
                ? JSON.parse(otherInformation)
                : "";
            console.log(
                "Decoded JSON content for product details with UTF-8 support"
            );
        } catch (e) {
            console.error(
                "Failed to decode JSON content for product details:",
                e
            );
        }
    }

    const props = {
        description,
        technicalSpecifications,
        warranty,
        otherInformation,
    };
    const accordionApp = createApp(ProductDetailsAccordion, props);
    accordionApp.mount("#product-details-accordion");
}

// Mount RichTextEditor components
document.querySelectorAll(".rich-text-editor-mount").forEach((element) => {
    console.log("Mounting RichTextEditor on element:", element);

    // Handle content decoding if needed
    let initialContent = element.dataset.content || "";
    if (element.dataset.contentEncoding === "json" && initialContent) {
        try {
            initialContent = JSON.parse(initialContent);
            console.log("Decoded JSON content for editor with UTF-8 support");
        } catch (e) {
            console.error("Failed to decode JSON content:", e);
            initialContent = element.dataset.content || "";
        }
    }

    console.log("Initial content:", initialContent.substring(0, 100) + "...");
    console.log("Field name:", element.dataset.name);

    const editorApp = createApp(RichTextEditor, {
        modelValue: initialContent,
        name: element.dataset.name || "",
        placeholder: element.dataset.placeholder || "Escribe aquí...",
        height: element.dataset.height || "300px",
    });

    editorApp.mount(element);
    console.log("RichTextEditor mounted successfully");
});

// Global event listener for rich editor changes
window.addEventListener("rich-editor-change", function (e) {
    const { name, content } = e.detail;

    console.log(
        "Rich editor change event received:",
        name,
        content.substring(0, 100) + "..."
    );

    // Update any existing hidden input with the same name
    let hiddenInput = document.querySelector(`input[name="${name}"]`);
    if (!hiddenInput) {
        console.log("Creating new hidden input for:", name);
        // Create hidden input if it doesn't exist
        hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = name;

        // Find the parent form and append the input
        const editorElement = document.querySelector(`[data-name="${name}"]`);
        const form = editorElement
            ? editorElement.closest("form")
            : document.querySelector("form");
        if (form) {
            form.appendChild(hiddenInput);
            console.log("Hidden input added to form");
        } else {
            console.error("No form found to add hidden input");
        }
    } else {
        console.log("Updating existing hidden input for:", name);
    }
    hiddenInput.value = content;

    // Emit event for content management page
    window.dispatchEvent(
        new CustomEvent("editor-content-change", {
            detail: { content: content, name: name },
        })
    );
});

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
