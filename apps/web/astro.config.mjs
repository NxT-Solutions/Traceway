import { defineConfig } from 'astro/config';

// The browser talks to DDEV's HTTPS router. Nginx proxies the site root here.
const devOrigin = (
    process.env.PUBLIC_SITE_URL ??
    process.env.DDEV_PRIMARY_URL ??
    'https://traceway.ddev.site'
).replace(/\/$/, '');
const devPublic = new URL(devOrigin);

export default defineConfig({
    site: devOrigin,
    output: 'static',
    // The site is one page. Old page links land on their section.
    redirects: {
        '/how-it-works': '/#how',
        '/sports': '/#sports',
        '/devices': '/#devices',
        '/notes': '/',
        '/notes/from-the-driveway': '/#how',
        '/notes/one-plan-every-leg': '/#multisport',
        '/notes/onto-the-computer': '/#devices',
    },
    server: {
        host: true,
        port: 4321,
    },
    // nginx's Content-Security-Policy runs no inline script or style tag
    // (.docker/production/images/configs/nginx/nginx.conf), so every script
    // and stylesheet stays a file under /_astro.
    build: {
        inlineStylesheets: 'never',
    },
    vite: {
        build: {
            assetsInlineLimit: (file) => (/\.(js|css)$/.test(file) ? false : undefined),
        },
        server: {
            allowedHosts: true,
            watch: {
                usePolling: true,
                interval: 300,
            },
            ws: {
                host: devPublic.hostname,
                protocol: devPublic.protocol === 'https:' ? 'wss' : 'ws',
                clientPort: devPublic.port
                    ? Number(devPublic.port)
                    : devPublic.protocol === 'https:'
                      ? 443
                      : 80,
            },
        },
    },
});
