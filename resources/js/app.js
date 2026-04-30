import { createApp, watchEffect } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import { DefaultApolloClient } from '@vue/apollo-composable';
import { apolloClient } from '@/apollo/client.js';
import { createAppI18n, defaultLocale } from '@/i18n/index.js';
import { routes } from '@/router/index.js';
import { useAuthStore } from '@/stores/auth.js';
import App from './App.vue';

const pinia = createPinia();

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { path: '/login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { path: '/dashboard' };
    }

    return true;
});

const app = createApp(App);
const i18n = createAppI18n();
app.use(pinia);
app.use(i18n);
app.use(router);
app.provide(DefaultApolloClient, apolloClient);

const auth = useAuthStore(pinia);
const themeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme() {
    const theme = auth.user?.settings?.theme ?? 'system';
    const prefersDark = themeMediaQuery.matches;
    const useDarkTheme = theme === 'dark' || (theme === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', useDarkTheme);
    document.documentElement.style.colorScheme = useDarkTheme ? 'dark' : 'light';
}

watchEffect(() => {
    i18n.global.locale.value = defaultLocale;
    document.documentElement.lang = defaultLocale;
    applyTheme();
});

if (typeof themeMediaQuery.addEventListener === 'function') {
    themeMediaQuery.addEventListener('change', applyTheme);
} else if (typeof themeMediaQuery.addListener === 'function') {
    themeMediaQuery.addListener(applyTheme);
}

if (auth.isAuthenticated) {
    auth.fetchCurrentUser().finally(() => {
        app.mount('#app');
    });
} else {
    app.mount('#app');
}
