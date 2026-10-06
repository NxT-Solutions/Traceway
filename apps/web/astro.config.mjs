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
    server: {
        host: true,
        port: 4321,
    },
    vite: {
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
