import { defineConfig } from 'vite';

/*
 * The front-end toolbar: one plain script, its CSS inlined, with nothing from
 * Statamic or Vue, for the pages of the site. Built next to the control
 * panel's assets (`npm run build` runs both), so it is published with them.
 */
export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'resources/dist/build',
        emptyOutDir: false,
        target: 'es2022',
        lib: {
            entry: 'resources/js/toolbar/index.js',
            formats: ['iife'],
            name: 'MarketingToolkitToolbar',
            fileName: () => 'toolbar.js',
        },
    },
});
