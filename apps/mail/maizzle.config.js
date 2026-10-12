import { defineConfig } from '@maizzle/framework';

export default defineConfig({
    content: ['emails/**/*.vue'],
    output: {
        path: '../app/resources/views/mail/compiled',
        extension: 'html',
    },
    components: {
        source: [{ path: 'layouts', prefix: '' }],
    },
    css: {
        inline: {
            removeInlinedSelectors: false,
            preferUnitlessValues: false,
        },
        purge: false,
        media: false,
        preferUnitless: false,
        shorthand: true,
        safe: true,
    },
    html: {
        decodeEntities: { '●': '&#9679;' },
        attributes: {
            remove: ['embed'],
        },
        format: false,
    },
});
