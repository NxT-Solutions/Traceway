import { addons } from 'storybook/manager-api';
import { create } from 'storybook/theming';

// The same look as the planner's Storybook (apps/app/.storybook/manager.ts).
addons.setConfig({
    theme: create({
        base: 'light',
        brandTitle: 'Traceway site',
        brandUrl: '../',
        brandImage: './traceway-wordmark.svg',
        brandTarget: '_self',
        colorPrimary: '#97cb46',
        colorSecondary: '#3a6b0c',
        appBg: '#f7f5ec',
        appContentBg: '#fbfaf5',
        appBorderColor: '#d9dccb',
        textColor: '#071f20',
        textMutedColor: '#4c5f60',
        barSelectedColor: '#3a6b0c',
        fontBase: "Outfit, 'Avenir Next', 'Segoe UI', system-ui, sans-serif",
    }),
});
