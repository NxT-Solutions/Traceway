import { fileURLToPath } from 'node:url';
import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import { playwright } from '@vitest/browser-playwright';
import { defineConfig, mergeConfig } from 'vitest/config';
import storybookVite from './.storybook/vite.config';

const path = (relative: string) =>
    fileURLToPath(new URL(relative, import.meta.url));

/** One run of every story in a browser, in Paper (light) or Ink (dark). */
const storybook = (name: string, appearance: 'light' | 'dark') => ({
    extends: true,
    plugins: [storybookTest({ configDir: path('.storybook') })],
    define: {
        'import.meta.env.STORYBOOK_APPEARANCE': JSON.stringify(appearance),
    },
    test: {
        name,
        // The planner's map stories draw a route through the mocked routing engine. On main, Plan a loop already
        // takes about 10 s on a CI runner, so the 15 s default left little room on a slow one.
        testTimeout: 30_000,
        browser: {
            enabled: true,
            headless: true,
            // Containers (DDEV, CI) give Chromium a small /dev/shm, and it
            // crashes partway through the run without this.
            provider: playwright({
                launchOptions: { args: ['--disable-dev-shm-usage'] },
            }),
            instances: [{ browser: 'chromium' as const }],
        },
    },
});

// Every story is a test: it must render, its play function must pass, and axe
// must find no accessibility violation (`a11y.test: 'error'` in preview.tsx).
// Run with `bun run test:storybook`, or from Storybook's Testing panel.
export default mergeConfig(
    storybookVite,
    defineConfig({
        // Mock Service Worker's script, written by `msw init` (see
        // package.json), so stories that mock the server work in tests too.
        publicDir: path('.storybook/msw'),
        test: {
            projects: [
                storybook('storybook', 'light'),
                storybook('storybook-ink', 'dark'),
            ],
            coverage: {
                provider: 'v8',
                include: ['resources/js/components/**', 'resources/js/pages/**'],
                exclude: ['resources/js/stories/**'],
                reportsDirectory: 'storybook-coverage',
            },
        },
    }),
);
