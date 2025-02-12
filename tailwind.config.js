import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
    ],

    theme: {
        screens: {
            xs: "0px", // Extra small (Not in Bootstrap, but useful)
            sm: "576px", // Small
            md: "768px", // Medium
            lg: "992px", // Large
            xl: "1200px", // Extra Large
            xxl: "1400px", // Extra Extra Large
        },
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
        },
    },

    darkMode: "class",

    plugins: [forms, require("tailwindcss-primeui")],
};
