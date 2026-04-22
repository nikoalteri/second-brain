import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', () => {
    const accessToken = ref(localStorage.getItem('fluxa_access_token'));
    const refreshToken = ref(localStorage.getItem('fluxa_refresh_token'));
    const user = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => !!accessToken.value);

    function setTokens(access, refresh = null) {
        accessToken.value = access;
        localStorage.setItem('fluxa_access_token', access);

        if (refresh) {
            refreshToken.value = refresh;
            localStorage.setItem('fluxa_refresh_token', refresh);
        }
    }

    function clearTokens() {
        accessToken.value = null;
        refreshToken.value = null;
        user.value = null;
        localStorage.removeItem('fluxa_access_token');
        localStorage.removeItem('fluxa_refresh_token');
    }

    async function login(email, password) {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch('/api/v1/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (!response.ok) {
                error.value = data.message || 'Invalid credentials.';
                return false;
            }

            setTokens(data.access_token, data.refresh_token);
            return true;
        } catch {
            error.value = 'Network error. Please try again.';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${accessToken.value}`,
                    Accept: 'application/json',
                },
            });
        } finally {
            clearTokens();
        }
    }

    return {
        accessToken,
        refreshToken,
        user,
        loading,
        error,
        isAuthenticated,
        login,
        logout,
        setTokens,
        clearTokens,
    };
});
