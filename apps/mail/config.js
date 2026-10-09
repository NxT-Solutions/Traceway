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
};
