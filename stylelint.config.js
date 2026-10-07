/** @type {import('stylelint').Config} */
export default {
    extends: ['stylelint-config-standard'],
    ignoreFiles: [
        '**/tokens.css',
        '**/print.css',
        '**/animations.css',
        '**/primitives.css',
    ],
    overrides: [
        {
            files: ['resources/css/gelia/features/**/*.css'],
            rules: {
                'color-no-hex': true,
                'alpha-value-notation': null,
            },
        },
    ],
};
