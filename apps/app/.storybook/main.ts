import { fileURLToPath } from 'node:url';
import type { StorybookConfig } from '@storybook/react-vite';

const path = (relative: string) =>
    fileURLToPath(new URL(relative, import.meta.url));

// The content site and the Garmin app are not React. The site has its own
// Storybook, where Astro renders each story (apps/web/.storybook); this one
// shows it under Site (Astro), from `npm run storybook` in dev (port 6007, or
// STORYBOOK_SITE_URL). Published, the two sit side by side on traceway.online,
// this one at /storybook/app/ and the site's at /storybook/web/, so a build
// finds it at ../web. The Garmin stories show the watch screens captured in
// the Connect IQ simulator (apps/garmin/store/screens).
const garmin = path('../../garmin/store');
const garminDrawables = path('../../garmin/resources/drawables');

const config: StorybookConfig = {
    stories: [
        '../resources/js/stories/**/*.mdx',
        '../resources/js/stories/**/*.stories.tsx',
    ],
    addons: [
        '@storybook/addon-docs',
        '@storybook/addon-a11y',
        '@storybook/addon-vitest',
        '@storybook/addon-links',
        // An MCP server at http://localhost:6006/mcp while `storybook dev`
        // runs: agents list components, read their docs, preview and test
        // stories (.mcp.json connects Claude Code to it).
        '@storybook/addon-mcp',
    ],
    // The component manifest the MCP server's docs tools read.
    features: { componentsManifest: true },
    framework: {
        name: '@storybook/react-vite',
        options: {
            builder: { viteConfigPath: '.storybook/vite.config.ts' },
        },
    },
    refs: (_config, { configType }) => ({
        site: {
            title: 'Site (Astro)',
            url:
                configType === 'DEVELOPMENT'
                    ? (process.env.STORYBOOK_SITE_URL ?? 'http://localhost:6007')
                    : '../web',
            expanded: true,
        },
    }),
    staticDirs: [
        { from: './public', to: '/' },
        // mockServiceWorker.js, written by `msw init` before each run.
        { from: './msw', to: '/' },
        { from: garmin, to: '/garmin' },
        { from: garminDrawables, to: '/garmin/drawables' },
        { from: '../resources/design-system/assets', to: '/brand' },
    ],
    core: { disableTelemetry: true },
    docs: { defaultName: 'Overview' },
};

export default config;
