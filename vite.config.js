import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/inertia.css',
                'resources/js/app.js',
                'resources/css/app.css',
                'resources/js/legacy.js',
                'resources/css/canvas.css',
                'resources/js/project-canvas.js',
                'resources/js/funnel-canvas.js',
                'resources/js/project-ai.js',
                'resources/js/project-ai-chat.js',
            ],
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
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
