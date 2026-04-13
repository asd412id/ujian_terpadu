import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { copyMathJax } from './vite-plugin-mathjax.js';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        copyMathJax(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    ckeditor: ['ckeditor5'],
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/frontend/setup-vitest.js'],
        include: ['tests/frontend/**/*.test.js'],
    },
});
