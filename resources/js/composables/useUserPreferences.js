import { computed } from 'vue';
import { resolveBrowserLocale } from '@/i18n/index.js';
import { useAuthStore } from '@/stores/auth.js';

const defaults = {
    theme: 'system',
    notifications: 'all',
    privacy: 'visible',
    display_currency: 'EUR',
};

export function useUserPreferences() {
    const auth = useAuthStore();

    const settings = computed(() => ({
        ...defaults,
        ...(auth.user?.settings ?? {}),
    }));

    const themeMode = computed(() => settings.value.theme);
    const locale = computed(() => resolveBrowserLocale());
    const notificationsMode = computed(() => settings.value.notifications);
    const profileIsPrivate = computed(() => settings.value.privacy === 'private');
    const displayCurrency = computed(() => settings.value.display_currency);

    function allowsToast(type = 'success') {
        return notificationsMode.value === 'all' || type === 'error';
    }

    return {
        settings,
        themeMode,
        locale,
        notificationsMode,
        profileIsPrivate,
        displayCurrency,
        allowsToast,
    };
}
