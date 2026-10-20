import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// Storybook builds the components on their own, without Laravel, Inertia's SSR
// or Wayfinder's generator. Wayfinder's routes are generated before a build
// (`php artisan wayfinder:generate --with-form`).
export default defineConfig({
    // Laravel's public/ holds index.php and the app's build. Storybook's own
    // static files are listed in main.ts.
    publicDir: false,
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
        },
    },
    define: {
        'import.meta.env.VITE_APP_NAME': JSON.stringify('Traceway'),
    },
    optimizeDeps: {
        exclude: ['maplibre-gl'],
        // Found late otherwise, so the first test run re-bundles and fails.
        include: ['@storybook/addon-links'],
    },
    worker: {
        format: 'es',
    },
});
