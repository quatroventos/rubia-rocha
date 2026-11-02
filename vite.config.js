import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin.css',
                'resources/js/admin/main.tsx',
                'resources/css/site.css',
                'resources/js/site/main.tsx',
                'resources/js/site/behaviors.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        allowedHosts: ['host.docker.internal'],
        cors: {
            origin: [/^https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\]|quatroventos)(?::\d+)?$/],
        },
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**', '**/storage/app/prerender/**'],
        },
    },
});
