<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { BellIcon } from '@heroicons/vue/24/outline';
import { isInternalPath, useNotificationsStore } from '@/stores/notifications.js';

const router = useRouter();
const store = useNotificationsStore();
const { t } = useI18n();
const open = ref(false);
const root = ref(null);

function handleDocumentClick(event) {
    if (!root.value?.contains(event.target)) {
        open.value = false;
    }
}

async function toggle() {
    open.value = !open.value;

    if (open.value) {
        await store.fetchNotifications();
    }
}

async function openNotification(notification) {
    await store.markRead(notification);

    // Follow only a path the router actually knows; anything else just marks read.
    if (isInternalPath(notification.action_url) && router.resolve(notification.action_url).matched.length > 0) {
        open.value = false;
        router.push(notification.action_url);
    }
}

function formatDate(value) {
    return new Date(value).toLocaleString();
}

onMounted(() => {
    // Capture phase: sibling menus stop click propagation, which would hide outside clicks from a bubbling listener.
    document.addEventListener('click', handleDocumentClick, true);
    void store.fetchNotifications();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick, true);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :aria-label="t('notifications.open')"
            :aria-expanded="open"
            aria-haspopup="true"
            class="relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-300"
            @click.stop="toggle"
        >
            <BellIcon class="h-5 w-5" />
            <span
                v-if="store.hasUnread"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"
                :aria-label="t('notifications.unreadCount', { count: store.unreadCount })"
            >
                {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
            </span>
        </button>

        <div
            v-if="open"
            role="region"
            :aria-label="t('notifications.title')"
            class="fixed inset-x-2 top-16 z-50 max-h-[70vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl md:absolute md:inset-x-auto md:right-0 md:top-12 md:w-96"
        >
            <p class="px-3 py-2 text-sm font-semibold text-gray-900">{{ t('notifications.title') }}</p>

            <p v-if="store.loading && !store.items.length" class="px-3 py-4 text-sm text-gray-500" role="status">
                {{ t('notifications.loading') }}
            </p>
            <p v-else-if="store.error" class="px-3 py-4 text-sm text-red-700" role="alert">
                {{ t('notifications.error') }}
                <button type="button" class="ml-1 underline" @click="store.fetchNotifications()">{{ t('notifications.retry') }}</button>
            </p>
            <p v-else-if="!store.items.length" class="px-3 py-4 text-sm text-gray-500">
                {{ t('notifications.empty') }}
            </p>

            <ul v-else class="flex flex-col gap-1">
                <li v-for="item in store.items" :key="item.id">
                    <button
                        type="button"
                        class="w-full rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-300"
                        :class="item.read_at ? 'text-gray-500' : 'bg-amber-50 text-gray-900'"
                        @click="openNotification(item)"
                    >
                        <span class="flex items-center gap-2 text-sm" :class="item.read_at ? 'font-normal' : 'font-semibold'">
                            <span v-if="!item.read_at" class="h-2 w-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                            <span class="sr-only" v-if="!item.read_at">{{ t('notifications.unread') }}</span>
                            {{ item.title }}
                        </span>
                        <span class="mt-0.5 block text-xs">{{ item.message }}</span>
                        <span class="mt-1 block text-[11px] text-gray-400">{{ formatDate(item.created_at) }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
