import { fileURLToPath } from 'node:url';
import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import { playwright } from '@vitest/browser-playwright';
import { defineConfig } from 'vitest/config';

// Every story is a test, in Chromium: Astro renders it as `storybook dev` does, its play function
// must pass, and axe must find no accessibility violation (`a11y.test: 'error'` in preview.ts).
// Run with `npm run test-storybook`, or from Storybook's Testing panel.
export default defineConfig({
    plugins: [
        storybookTest({
            configDir: fileURLToPath(new URL('.storybook', import.meta.url)),
        }),
    ],
    test: {
        name: 'storybook',
        browser: {
            enabled: true,
            headless: true,
            provider: playwright({
                launchOptions: {
                    args: [
                        // Containers (DDEV, CI) give Chromium a small /dev/shm,
                        // and it crashes partway through the run without this.
                        '--disable-dev-shm-usage',
                        // The pages' text rises in unless the visitor asks for
                        // less motion. axe checks the colours of the end state.
                        '--force-prefers-reduced-motion',
                    ],
                },
            }),
            instances: [{ browser: 'chromium' }],
        },
    },
});
