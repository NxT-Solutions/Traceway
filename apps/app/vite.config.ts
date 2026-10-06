import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// The browser talks to DDEV's HTTPS router. Nginx proxies /_app/ to this server.
const devOrigin = (
    process.env.DDEV_PRIMARY_URL ?? 'https://traceway.ddev.site'
).replace(/\/$/, '');
const devPublic = new URL(devOrigin);

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Outfit', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    base: '/_app/',
    optimizeDeps: {
        exclude: ['maplibre-gl'],
    },
    worker: {
        format: 'es',
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: devOrigin,
        cors: true,
        ws: {
            host: devPublic.hostname,
            protocol: devPublic.protocol === 'https:' ? 'wss' : 'ws',
            clientPort: devPublic.port
                ? Number(devPublic.port)
                : devPublic.protocol === 'https:'
                  ? 443
                  : 80,
        },
        watch: {
            usePolling: true,
            interval: 300,
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
