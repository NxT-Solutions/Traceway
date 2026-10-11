/** @type {import('@maizzle/framework').Config} */
export default {
    build: {
        content: ['emails/**/*.html'],
        output: {
            path: '../app/resources/views/mail/compiled',
            extension: 'html',
            from: 'emails',
        },
    },
    components: {
        folders: ['layouts', 'components'],
    },
    css: {
        inline: {
            removeInlinedSelectors: false,
            preferUnitlessValues: false,
        },
        shorthand: true,
        safe: true,
    },
    prettify: true,
};
