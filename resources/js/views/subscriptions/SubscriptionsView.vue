<script setup>
import { computed, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { CalendarDaysIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const router = useRouter();
const { formatCurrency } = useCurrency();
const page = ref(1);

const SUBSCRIPTIONS_QUERY = gql`
    query GetSubscriptions($page: Int) {
        subscriptions(first: 20, page: $page) {
            data {
                id
                name
                monthly_cost
                annual_cost
                frequency
                day_of_month
                next_renewal_date
                status
                notes
            }
            paginatorInfo {
                currentPage
                lastPage
                total
            }
        }
    }
`;

const { result, loading } = useQuery(SUBSCRIPTIONS_QUERY, () => ({ page: page.value }));
const subscriptions = computed(() => result.value?.subscriptions?.data ?? []);
const paginator = computed(() => result.value?.subscriptions?.paginatorInfo);

function isRenewingSoon(dateString) {
    if (!dateString) return false;
    const renewal = new Date(dateString);
    const now = new Date();
    const diffDays = (renewal.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
    return diffDays >= 0 && diffDays <= 7;
}

function renewalDateClass(dateString) {
    return isRenewingSoon(dateString) ? 'text-red-400' : 'text-gray-400';
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-emerald-500/10 text-emerald-400',
        inactive: 'bg-gray-500/10 text-gray-400',
        cancelled: 'bg-gray-500/10 text-gray-400',
        trial: 'bg-blue-500/10 text-blue-400',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-400';
}

function frequencyLabel(frequency) {
    const map = { monthly: 'Monthly', annual: 'Yearly', biennial: 'Biennial', weekly: 'Weekly' };
    return map[frequency?.toLowerCase()] ?? frequency;
}

function displayCost(subscription) {
    if (subscription.frequency === 'annual') return `${formatCurrency(subscription.annual_cost)}/yr`;
    return `${formatCurrency(subscription.monthly_cost)}/mo`;
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-white">Subscriptions</h1>
                <p class="mt-1 text-sm text-gray-400">Keep tabs on recurring costs</p>
            </div>
            <router-link
                to="/subscriptions/new"
                class="flex h-10 items-center rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-950 hover:bg-blue-700"
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

        <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="subscription in subscriptions"
                    :key="subscription.id"
                    class="cursor-pointer rounded-xl border border-gray-700 bg-gray-800 p-4 transition-colors duration-150 hover:border-gray-600"
                    @click="router.push(`/subscriptions/${subscription.id}/edit`)"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div class="min-w-0 flex-1 pr-2">
                            <h3 class="truncate text-base font-normal text-white">{{ subscription.name }}</h3>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="rounded bg-gray-700 px-2 py-0.5 text-sm text-gray-300">
                                    {{ frequencyLabel(subscription.frequency) }}
                                </span>
                            </div>
                        </div>
                        <span :class="statusBadgeClass(subscription.status)" class="shrink-0 rounded px-2 py-0.5 text-sm capitalize">
                            {{ subscription.status }}
                        </span>
                    </div>

                    <p class="mb-2 font-mono text-xl font-semibold text-amber-400">{{ displayCost(subscription) }}</p>

                    <div class="flex items-center gap-1.5">
                        <CalendarDaysIcon class="h-4 w-4 shrink-0 text-gray-500" />
                        <span class="text-sm" :class="renewalDateClass(subscription.next_renewal_date)">
                            {{ isRenewingSoon(subscription.next_renewal_date) ? '⚠ ' : '' }}Renews {{ subscription.next_renewal_date ?? 'N/A' }}
                        </span>
                    </div>

                    <p v-if="subscription.notes" class="mt-2 truncate text-sm text-gray-500">{{ subscription.notes }}</p>
                </div>
            </div>

            <div
                v-if="paginator?.lastPage > 1"
                class="mt-6 flex items-center justify-between border-t border-gray-700 pt-4"
            >
                <p class="text-sm text-gray-500">Page {{ paginator.currentPage }} of {{ paginator.lastPage }}</p>
                <div class="flex gap-2">
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page <= 1"
                        @click="page--"
                    >
                        Prev
                    </button>
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page >= paginator.lastPage"
                        @click="page++"
                    >
                        Next
                    </button>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
