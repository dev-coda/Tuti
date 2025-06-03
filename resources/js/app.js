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

// app.component('test-component', testcomponent);

// app.mount('#app');

// import { createApp } from 'vue'
// import combinedProducts from './vue/combinedProducts.vue'

// const app = createApp(root)
// app.component('combinedProducts', combinedProducts)
// app.mount('#combinedProducts')
// app

// app.mount('#app')

// import { createApp } from 'vue';

// import Test from './vue/ExampleComponent.vue';
// import App from './vue/App.vue';
// const app = createApp({})
// app.mount('#app');

// app.component('app', App);

//

// // // const app = createApp({});
// // // app.component('mi-componente', MiComponente);
// // // app.mount('#app');

// Object.entries(import.meta.glob('./**/*.vue', { eager: true })).forEach(([path, definition]) => {
//     console.log(path.split('/').pop().replace(/\.\w+$/, ''))
//     app.component(path.split('/').pop().replace(/\.\w+$/, ''), definition.default);
// });

//import { createApp } from 'vue'
//import MyComponent from './vue/ExampleComponent.vue'
// createApp(MyComponent).mount('#app')

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
