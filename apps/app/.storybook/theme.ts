import { create } from 'storybook/theming';

const font = "Outfit, 'Avenir Next', 'Segoe UI', system-ui, sans-serif";

// Storybook's own chrome and docs pages, in the brand's two appearances. The
// hex values are the tokens' oklch values (paper, ink, card, muted text,
// border, the marker) as sRGB, since Storybook's theming wants hex.

/** Paper: the manager, and docs pages while the Appearance toolbar says Paper. */
export const paper = create({
    base: 'light',
    brandTitle: 'Traceway',
    brandUrl: '../',
    brandImage: './traceway-wordmark.svg',
    brandTarget: '_self',
    colorPrimary: '#97cb46',
    colorSecondary: '#3a6b0c',
    appBg: '#f7f5ec',
    appContentBg: '#fbfaf5',
    appPreviewBg: '#f7f5ec',
    appBorderColor: '#d9dccb',
    textColor: '#071f20',
    textMutedColor: '#4c5f60',
    barSelectedColor: '#3a6b0c',
    fontBase: font,
});

/** Ink: docs pages while the Appearance toolbar says Ink, so the stories on them sit on their own ground. */
export const ink = create({
    base: 'dark',
    brandTitle: 'Traceway',
    brandUrl: '../',
    brandImage: './traceway-wordmark-paper.svg',
    brandTarget: '_self',
    colorPrimary: '#97cb46',
    colorSecondary: '#b7e36a',
    appBg: '#0b2224',
    appContentBg: '#071f20',
    appPreviewBg: '#071f20',
    appBorderColor: '#2a4244',
    textColor: '#f7f5ec',
    textMutedColor: '#b3c0c0',
    barSelectedColor: '#b7e36a',
    fontBase: font,
});
