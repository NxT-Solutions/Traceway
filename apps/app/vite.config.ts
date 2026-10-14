import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig, lazyPlugins } from 'vite-plus';

// The browser talks to DDEV's HTTPS router. Nginx proxies /_app/ to this server.
const devOrigin = (
    process.env.DDEV_PRIMARY_URL ?? 'https://traceway.ddev.site'
).replace(/\/$/, '');
const devPublic = new URL(devOrigin);
const devBase = '/_app/';

export default defineConfig(({ command }) => ({
    plugins: lazyPlugins(() => [
        // laravel-vite-plugin writes dev font URLs under the base (/_app/),
        // but its font middleware runs before Vite strips the base, so every
        // font 404s. Drop the base from those requests first.
        {
            name: 'traceway:fonts-under-base',
            apply: 'serve',
            configureServer(server) {
                const fonts = `${devBase}__laravel_vite_plugin__/fonts/`;

                server.middlewares.use((request, _response, next) => {
                    if (request.url?.startsWith(fonts)) {
                        request.url = request.url.slice(devBase.length - 1);
                    }

                    next();
                });
            },
        },
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Outfit', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        // The map in a chat card loads MapLibre and the font from Traceway,
        // not from a CDN, so no third party sees who opens it. The build
        // keeps copies under fixed names; ShowChatCardFileController serves
        // them.
        {
            name: 'traceway:chat-card-files',
            apply: 'build',
            generateBundle(_options, bundle) {
                const maplibre = fileURLToPath(
                    new URL('./node_modules/maplibre-gl/dist/', import.meta.url),
                );

                for (const file of [
                    'maplibre-gl.mjs',
                    'maplibre-gl-worker.mjs',
                    'maplibre-gl.css',
                ]) {
                    this.emitFile({
                        type: 'asset',
                        fileName: `chat/${file}`,
                        source: readFileSync(`${maplibre}${file}`),
                    });
                }

                for (const weight of ['400', '600']) {
                    const font = Object.values(bundle).find(
                        (item) =>
                            item.type === 'asset' &&
                            item.fileName.includes(`outfit-${weight}-normal`) &&
                            item.fileName.endsWith('.woff2'),
                    );

                    if (font?.type === 'asset') {
                        this.emitFile({
                            type: 'asset',
                            fileName: `chat/outfit-${weight}.woff2`,
                            source: font.source,
                        });
                    }
                }
            },
        },
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
    // Only the DDEV dev server sits behind nginx's /_app/ proxy path.
    // A production build is served from Laravel's own /build/ directory,
    // so baking /_app/ into it breaks every dynamically-imported chunk.
    base: command === 'serve' ? devBase : undefined,
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
}));
