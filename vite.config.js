import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react({
            jsxRuntime: 'automatic',
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            strategies: 'generateSW',
            filename: 'sw.js',
            manifestFilename: 'manifest.webmanifest',
            includeAssets: [
                'favicon.ico',
                'apple-touch-icon-180x180.png',
                'pwa-64x64.png',
                'pwa-192x192.png',
                'pwa-512x512.png',
                'maskable-icon-512x512.png',
            ],
            manifest: {
                name: 'InteTeam Garage',
                short_name: 'Garage',
                description: 'Repair work, documented — mechanic dashboard for InteTeam Garage.',
                theme_color: '#0f172a',
                background_color: '#f8fafc',
                display: 'standalone',
                orientation: 'portrait',
                scope: '/',
                start_url: '/dashboard',
                lang: 'en',
                icons: [
                    { src: '/pwa-64x64.png',  sizes: '64x64',   type: 'image/png' },
                    { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // App HTML is dynamic (Inertia) and behind auth — runtime cache only.
                navigateFallback: null,
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'garage-pages',
                            networkTimeoutSeconds: 4,
                            expiration: { maxEntries: 40, maxAgeSeconds: 60 * 60 * 24 },
                        },
                    },
                    {
                        urlPattern: ({ url }) => /\/(favicon\.ico|apple-touch-icon|pwa-|maskable-)/.test(url.pathname),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'garage-icons',
                            expiration: { maxEntries: 16, maxAgeSeconds: 60 * 60 * 24 * 30 },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/storage\.googleapis\.com\//,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'garage-gcs-media',
                            expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 7 },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: false,
        hmr: {
            host: 'localhost',
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
