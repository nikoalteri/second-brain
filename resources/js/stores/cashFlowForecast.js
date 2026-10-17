import { ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';

export const useCashFlowForecastStore = defineStore('cashFlowForecast', () => {
    const forecast = ref(null);
    const loading = ref(false);
    const error = ref(false);
    // Bumped on reset(): responses that began under an earlier session are discarded.
    let session = 0;

    async function fetchForecast() {
        const auth = useAuthStore();
        const startedIn = session;

        if (!auth.accessToken) {
            forecast.value = null;
            return;
        }

        loading.value = true;
        error.value = false;

        try {
            const response = await fetch('/api/v1/dashboard/cash-flow-forecast', {
                headers: {
                    Authorization: `Bearer ${auth.accessToken}`,
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const body = await response.json();

            if (startedIn !== session) {
                return;
            }

            forecast.value = body.data ?? null;
        } catch {
            if (startedIn === session) {
                forecast.value = null;
                error.value = true;
            }
        } finally {
            if (startedIn === session) {
                loading.value = false;
            }
        }
    }

    function reset() {
        session += 1;
        forecast.value = null;
        loading.value = false;
        error.value = false;
    }

    return { forecast, loading, error, fetchForecast, reset };
});
