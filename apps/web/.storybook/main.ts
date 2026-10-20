import { fileURLToPath } from 'node:url';
import type { StorybookConfig } from '@storybook-astro/framework';
import { astroComponentScripts } from './astro-scripts.ts';

// The content site's own Storybook. Astro renders every story: live, on each
// change of a Control, in `npm run storybook`, and once per story in
// `npm run build-storybook` (renderMode 'static'), where the Controls are fixed.
// The planner's Storybook (apps/app/.storybook) shows this one under Site.
const config: StorybookConfig = {
    stories: ['../src/**/*.stories.ts'],
    addons: [
        '@storybook/addon-docs',
        '@storybook/addon-a11y',
        '@storybook/addon-vitest',
    ],
    framework: {
        name: '@storybook-astro/framework',
        options: {},
    },
    staticDirs: ['../public', './public'],
    core: { disableTelemetry: true },
    docs: { defaultName: 'Overview' },
    viteFinal(config) {
        config.plugins = [
            ...(config.plugins ?? []),
            astroComponentScripts({
                src: fileURLToPath(new URL('../src', import.meta.url)),
            }),
        ];

        return config;
    },
};

export default config;
