import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        // Vue/JS components use Tailwind too; without scanning them, utilities
        // used only in .vue files get purged from the build (this broke the
        // featured-products images when bg-contain left the last Blade file).
        "./resources/js/**/*.{js,vue}",
        "./node_modules/flowbite/**/*.js",
    ],
    safelist: ["resize-none", "owl-nav"],
    theme: {
        fontFamily: {
            inter: ["Inter", "sans-serif"],
            dm: ["DM Sans", "sans-serif"],
        },
        extend: {
            colors: {
                gray2: "#D9D9D9",
                gray3: "#f3f2f1",
                gray4: "#e7e6e4",
                blue1: "#4a84ed",
                blue2: "#c1d9f9",
                blue3: "#edf2f9",
                offert: "#e69e46",
                primary: "#180F09",
                secondary: "#EE4E34",
                info: "#4a84ed",
                footer: "#003E51",
                footer2: "#09244B",
            },
        },
    },

    plugins: [require("flowbite/plugin")],
};
