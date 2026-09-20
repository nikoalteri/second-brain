<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import KpiCard from '@/components/ui/KpiCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { subscriptionStatusIcons } from '@/icons/domainIcons.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const { formatCurrency } = useCurrency();
const subscriptions = ref([]);
const loading = ref(false);
const sortField = ref('next_renewal_date');
const sortDirection = ref('asc');
const filterStatus = ref('');

const sortOptions = [
    { value: 'next_renewal_date', label: 'Next renewal' },
    { value: 'monthly_cost', label: 'Monthly cost' },
    { value: 'annual_cost', label: 'Annual cost' },
    { value: 'created_at', label: 'Date added' },
];
const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'cancelled', label: 'Cancelled' },
];

const activeSubscriptions = computed(() => subscriptions.value.filter((subscription) => subscription.status === 'active'));
const monthlyActualTotal = computed(() =>
    activeSubscriptions.value
        .filter((subscription) => (subscription.frequency_option?.months_interval ?? 1) === 1)
        .reduce((sum, subscription) => sum + (subscription.monthly_cost ?? 0), 0)
);
const monthlyWeightedTotal = computed(() =>
    activeSubscriptions.value.reduce((sum, subscription) => {
        const interval = Math.max(1, subscription.frequency_option?.months_interval ?? 1);
        return sum + (subscription.billing_amount ?? 0) / interval;
    }, 0)
);
const annualTotal = computed(() => monthlyWeightedTotal.value * 12);

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
        const params = new URLSearchParams({ per_page: '100' });
        params.set('sort', `${sortDirection.value === 'desc' ? '-' : ''}${sortField.value}`);

        if (filterStatus.value) {
            params.set('filter[status]', filterStatus.value);
        }

        const response = await fetch(`/api/v1/subscriptions?${params.toString()}`, {
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

watch([sortField, sortDirection, filterStatus], () => {
    void fetchSubscriptions();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Subscriptions</h1>
            </div>
            <router-link
                to="/subscriptions/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add subscription
            </router-link>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <KpiCard label="Monthly (actual)" :value="formatCurrency(monthlyActualTotal)" color="amber" delta="Subscriptions billed monthly" />
            <KpiCard label="Monthly (weighted)" :value="formatCurrency(monthlyWeightedTotal)" color="blue" delta="Every subscription, normalized to a monthly figure" />
            <KpiCard label="Annual" :value="formatCurrency(annualTotal)" color="purple" delta="Projected yearly subscription spend" />
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <select
                v-model="sortField"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
                <option v-for="option in sortOptions" :key="option.value" :value="option.value">Sort: {{ option.label }}</option>
            </select>
            <select
                v-model="sortDirection"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
            <select
                v-model="filterStatus"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
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
                    <span :class="statusBadgeClass(subscription.status)" class="inline-flex shrink-0 items-center gap-1 rounded px-2 py-0.5 text-sm capitalize">
                        <component :is="subscriptionStatusIcons[subscription.status]" class="h-3.5 w-3.5" />
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
