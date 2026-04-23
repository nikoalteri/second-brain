import { createI18n } from 'vue-i18n';
import en from './messages/en.js';
import it from './messages/it.js';

export const defaultLocale = 'en';

export function resolveAppLocale(value) {
    return value === 'it' ? 'it' : defaultLocale;
}

export function resolveBrowserLocale(value) {
    return resolveAppLocale(value) === 'it' ? 'it-IT' : 'en-US';
}

export const messages = {
    en,
    it,
};

export function createAppI18n(locale = defaultLocale, options = {}) {
    return createI18n({
        legacy: false,
        locale: resolveAppLocale(locale),
        fallbackLocale: defaultLocale,
        messages,
        ...options,
    });
}
