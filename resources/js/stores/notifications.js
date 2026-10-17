import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';

// Application-relative paths only: never follow a URL the server could not vouch for.
export function isInternalPath(url) {
    return typeof url === 'string'
        && url.startsWith('/')
        && !url.startsWith('//')
        && !url.includes('\\');
}

export const useNotificationsStore = defineStore('notifications', () => {
    const items = ref([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const error = ref(false);

    const hasUnread = computed(() => unreadCount.value > 0);

    function headers() {
        const auth = useAuthStore();

        return {
            Authorization: `Bearer ${auth.accessToken}`,
            Accept: 'application/json',
        };
    }

    async function fetchNotifications() {
        loading.value = true;
        error.value = false;

        try {
            const response = await fetch('/api/v1/notifications?per_page=20', { headers: headers() });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const body = await response.json();
            items.value = body.data ?? [];
            unreadCount.value = body.unread_count ?? 0;
        } catch {
            error.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function markRead(notification) {
        if (notification.read_at) {
            return;
        }

        try {
            const response = await fetch(`/api/v1/notifications/${notification.id}/read`, {
                method: 'PATCH',
                headers: headers(),
            });

            if (!response.ok) {
                return;
            }

            const body = await response.json();
            const index = items.value.findIndex((item) => item.id === notification.id);

            if (index !== -1) {
                items.value[index] = body.data;
            }

            unreadCount.value = Math.max(0, unreadCount.value - 1);
        } catch {
            // Keep the list as-is; the next refresh reconciles the count.
        }
    }

    function reset() {
        items.value = [];
        unreadCount.value = 0;
        error.value = false;
    }

    return { items, unreadCount, loading, error, hasUnread, fetchNotifications, markRead, reset };
});
