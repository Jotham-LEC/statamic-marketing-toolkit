import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        statamic(),
        tailwindcss(),
        laravel({
            input: ['resources/js/addon.js', 'resources/css/addon.css'],
            publicDirectory: 'resources/dist',
        }),
    ],
});
