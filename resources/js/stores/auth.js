import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { clearApolloCache } from '@/apollo/client.js';

export const useAuthStore = defineStore('auth', () => {
    const accessToken = ref(localStorage.getItem('fluxa_access_token'));
    const refreshToken = ref(localStorage.getItem('fluxa_refresh_token'));
    const storedUser = localStorage.getItem('fluxa_user');
    const user = ref(storedUser ? JSON.parse(storedUser) : null);
    const loading = ref(false);
    const error = ref(null);
    const validationErrors = ref({});

    const isAuthenticated = computed(() => !!accessToken.value);
    const isAdmin = computed(() => !!user.value?.is_admin);

    function setUser(value) {
        user.value = value;

        if (value) {
            localStorage.setItem('fluxa_user', JSON.stringify(value));
        } else {
            localStorage.removeItem('fluxa_user');
        }
    }

    function setTokens(access, refresh = null, authenticatedUser = null) {
        accessToken.value = access;
        localStorage.setItem('fluxa_access_token', access);

        if (refresh) {
            refreshToken.value = refresh;
            localStorage.setItem('fluxa_refresh_token', refresh);
        }

        if (authenticatedUser) {
            setUser(authenticatedUser);
        }

        void clearApolloCache();
    }

    function clearTokens() {
        accessToken.value = null;
        refreshToken.value = null;
        setUser(null);
        localStorage.removeItem('fluxa_access_token');
        localStorage.removeItem('fluxa_refresh_token');
        void clearApolloCache();
    }

    function clearFeedback() {
        error.value = null;
        validationErrors.value = {};
    }

    function setRequestError(data, fallbackMessage) {
        error.value = data.message || fallbackMessage;
        validationErrors.value = data.errors ?? {};
    }

    function updateUserSettings(settings) {
        if (!user.value) {
            return;
        }

        setUser({
            ...user.value,
            settings: {
                ...(user.value.settings ?? {}),
                ...settings,
            },
        });
    }

    async function fetchCurrentUser() {
        if (!accessToken.value) {
            setUser(null);
            return null;
        }

        const response = await fetch('/api/v1/auth/me', {
            headers: {
                Authorization: `Bearer ${accessToken.value}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            if (response.status === 401) {
                clearTokens();
            }

            return null;
        }

        const data = await response.json();
        setUser(data.user ?? null);

        return data.user ?? null;
    }

    async function login(email, password) {
        loading.value = true;
        clearFeedback();

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
                setRequestError(data, 'Invalid credentials.');
                return false;
            }

            setTokens(data.access_token, data.refresh_token, data.user ?? null);
            return true;
        } catch {
            error.value = 'Network error. Please try again.';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function register(payload) {
        loading.value = true;
        clearFeedback();

        try {
            const response = await fetch('/api/v1/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                setRequestError(data, 'Unable to create your account.');
                return false;
            }

            setTokens(data.access_token, data.refresh_token, data.user ?? null);
            return true;
        } catch {
            error.value = 'Network error. Please try again.';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function requestPasswordReset(email) {
        loading.value = true;
        clearFeedback();

        try {
            const response = await fetch('/api/v1/auth/forgot-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();

            if (!response.ok) {
                setRequestError(data, 'Unable to send password reset link.');
                return { ok: false, message: null };
            }

            return { ok: true, message: data.message };
        } catch {
            error.value = 'Network error. Please try again.';
            return { ok: false, message: null };
        } finally {
            loading.value = false;
        }
    }

    async function resetPassword(payload) {
        loading.value = true;
        clearFeedback();

        try {
            const response = await fetch('/api/v1/auth/reset-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                setRequestError(data, 'Unable to reset password.');
                return { ok: false, message: null };
            }

            return { ok: true, message: data.message };
        } catch {
            error.value = 'Network error. Please try again.';
            return { ok: false, message: null };
        } finally {
            loading.value = false;
        }
    }

    async function updateProfile(payload) {
        loading.value = true;
        clearFeedback();

        try {
            const response = await fetch('/api/v1/auth/profile', {
                method: 'PUT',
                headers: {
                    Authorization: `Bearer ${accessToken.value}`,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                setRequestError(data, 'Unable to update your profile.');
                return false;
            }

            setUser(data.user ?? null);
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
        validationErrors,
        isAuthenticated,
        isAdmin,
        login,
        register,
        requestPasswordReset,
        resetPassword,
        updateProfile,
        logout,
        setTokens,
        setUser,
        clearTokens,
        clearFeedback,
        fetchCurrentUser,
        updateUserSettings,
    };
});
