import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';

export const useVaultStore = defineStore('vault', () => {
    const token = ref(null);
    const expiresAt = ref(null);

    const isUnlocked = computed(() => !!token.value && !!expiresAt.value && Date.now() < expiresAt.value);

    async function unlock(code) {
        const auth = useAuthStore();

        try {
            const response = await fetch('/api/v1/vault/unlock', {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${auth.accessToken}`,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code }),
            });

            const data = await response.json();

            if (!response.ok) {
                return { ok: false, message: data.message || 'Invalid code.' };
            }

            token.value = data.vault_token;
            expiresAt.value = Date.now() + data.expires_in * 1000;

            return { ok: true };
        } catch {
            return { ok: false, message: 'Network error. Please try again.' };
        }
    }

    function lock() {
        token.value = null;
        expiresAt.value = null;
    }

    function headers(includeJson = false) {
        const auth = useAuthStore();

        return {
            Authorization: `Bearer ${auth.accessToken}`,
            Accept: 'application/json',
            'X-Vault-Token': token.value ?? '',
            ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
        };
    }

    return { token, isUnlocked, unlock, lock, headers };
});
