import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import { DefaultApolloClient } from '@vue/apollo-composable';
import { apolloClient } from '@/apollo/client.js';
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

    if (to.path === '/login' && auth.isAuthenticated) {
        return { path: '/dashboard' };
    }

    return true;
});

const app = createApp(App);
app.use(pinia);
app.use(router);
app.provide(DefaultApolloClient, apolloClient);

const auth = useAuthStore(pinia);

if (auth.isAuthenticated) {
    auth.fetchCurrentUser().finally(() => {
        app.mount('#app');
    });
} else {
    app.mount('#app');
}
