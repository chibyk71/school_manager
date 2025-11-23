import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        resolve: {
            alias: [
                // In a Laravel Inertia project, the `@` symbol is used to reference the `resources` directory.
                // This alias is necessary because Vite needs to know where to find the resources for the application.
                // In this case, we're telling Vite to look in the `resources` directory.
                { find: "$ui", replacement: path.resolve(__dirname, "resources/js/components/ui/") }
            ]
        }
    ],
});
