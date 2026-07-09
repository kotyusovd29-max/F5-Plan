import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/canvas.css', 'resources/js/project-canvas.js', 'resources/js/funnel-canvas.js', 'resources/js/project-ai.js', 'resources/js/project-ai-chat.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
