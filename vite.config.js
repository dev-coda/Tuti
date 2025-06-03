import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    build: {
        manifest: true,
        outDir: "public/build",
    },
    plugins: [
        vue(),
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/seller.css",
                "resources/js/app.js",
            ],
            refresh: false,
        }),
    ],
});
