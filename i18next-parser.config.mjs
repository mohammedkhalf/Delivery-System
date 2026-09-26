/**
 * i18next-parser config.
 * Scans .jsx / .js sources for t('key', 'fallback') calls and merges the
 * extracted keys into Modules/Builder/resources/js/locales/{lng}.json.
 *
 * - English keys take their fallback string as the default value.
 * - Other locales (ar, fr) get the key with an empty string for translators.
 * - Additive only: existing translations are preserved AND keys whose t()
 *   call has been removed from source stay in the JSON (keepRemoved: true).
 *
 * Run:
 *   npm run i18n:extract        # one-shot
 *   npm run i18n:watch          # continuous
 */
export default {
    locales: ['en', 'ar', 'fr'],
    output: 'Modules/Builder/resources/js/locales/$LOCALE.json',
    input: [
        'Modules/Builder/resources/js/**/*.{js,jsx}',
        'resources/js/**/*.{js,jsx}',
        '!**/node_modules/**',
    ],
    defaultNamespace: 'translation',
    namespaceSeparator: false,
    keySeparator: '.',
    sort: true,
    keepRemoved: true,
    createOldCatalogs: false,
    indentation: 4,
    lineEnding: 'lf',
    failOnUpdate: false,
    defaultValue: (locale, _ns, _key, value) =>
        locale === 'en' ? (value ?? '') : '',
};
