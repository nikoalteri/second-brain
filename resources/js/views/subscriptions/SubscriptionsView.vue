<script setup>
import { onMounted, ref } from 'vue';
import { CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const { formatCurrency } = useCurrency();
const subscriptions = ref([]);
const loading = ref(false);

function isRenewingSoon(dateString) {
    if (!dateString) return false;
    const renewal = new Date(dateString);
    const now = new Date();
    const diffDays = (renewal.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
    return diffDays >= 0 && diffDays <= 3;
}

function renewalDateClass(dateString) {
    return isRenewingSoon(dateString) ? 'text-red-400' : 'text-gray-500';
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-emerald-500/10 text-emerald-400',
        inactive: 'bg-gray-500/10 text-gray-500',
        cancelled: 'bg-gray-500/10 text-gray-500',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

function sourceLabel(subscription) {
    if (subscription.payment_source_type === 'credit-card') {
        return subscription.credit_card?.name ?? 'Credit card';
    }

    return subscription.account?.name ?? 'Account';
}

async function fetchSubscriptions() {
    if (!auth.accessToken) {
        subscriptions.value = [];
        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/api/v1/subscriptions?per_page=100', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            subscriptions.value = [];
            return;
        }

        const data = await response.json();
        subscriptions.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void fetchSubscriptions();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Subscriptions</h1>
                <p class="mt-1 text-sm text-gray-500">Keep recurring charges, reminders, and automatic postings in sync.</p>
            </div>
            <router-link
                to="/subscriptions/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add subscription
            </router-link>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <EmptyState
            v-else-if="!subscriptions.length"
            title="No subscriptions tracked"
            message="Add a subscription to keep tabs on recurring costs."
            :icon="CalendarDaysIcon"
            action-label="Add subscription"
            action-to="/subscriptions/new"
        />

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="subscription in subscriptions"
                :key="subscription.id"
                class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition-colors duration-150 hover:border-gray-300"
                @click="router.push(`/subscriptions/${subscription.id}/edit`)"
            >
                <div class="mb-3 flex items-start justify-between">
                    <div class="min-w-0 flex-1 pr-2">
                        <h3 class="truncate text-base font-normal text-gray-900">{{ subscription.name }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-700">
                                {{ subscription.frequency_label ?? subscription.frequency }}
                            </span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-sm text-slate-600">
                                {{ sourceLabel(subscription) }}
                            </span>
                        </div>
                    </div>
                    <span :class="statusBadgeClass(subscription.status)" class="shrink-0 rounded px-2 py-0.5 text-sm capitalize">
                        {{ subscription.status }}
                    </span>
                </div>

                <p class="mb-2 font-mono text-xl font-semibold text-amber-400">{{ formatCurrency(subscription.billing_amount) }}</p>

                <div class="flex items-center gap-1.5">
                    <CalendarDaysIcon class="h-4 w-4 shrink-0 text-gray-500" />
                    <span class="text-sm" :class="renewalDateClass(subscription.next_renewal_date)">
                        {{ isRenewingSoon(subscription.next_renewal_date) ? '⚠ ' : '' }}Renews {{ subscription.next_renewal_date ?? 'N/A' }}
                    </span>
                </div>

                <p v-if="subscription.auto_create_transaction" class="mt-2 text-sm text-gray-500">
                    Auto-posting enabled
                </p>

                <p v-if="subscription.notes" class="mt-2 truncate text-sm text-gray-500">{{ subscription.notes }}</p>
            </div>
        </div>
    </AppLayout>
</template>
