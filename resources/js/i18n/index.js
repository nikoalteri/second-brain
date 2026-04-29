import { createI18n } from 'vue-i18n';
import en from './messages/en.js';

export const defaultLocale = 'en';

export function resolveAppLocale(value) {
    return defaultLocale;
}

export function resolveBrowserLocale(value) {
    return 'en-US';
}

export const messages = {
    en,
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
