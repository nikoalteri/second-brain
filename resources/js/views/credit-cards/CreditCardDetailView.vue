<script setup>
import { computed, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { ChevronDownIcon, ChevronUpIcon, PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const route = useRoute();
const { formatCurrency } = useCurrency();
const expandedCycles = ref(new Set());

const CARD_DETAIL_QUERY = gql`
    query GetCreditCard($id: ID!) {
        creditCard(id: $id) {
            id
            name
            type
            credit_limit
            current_balance
            available_credit
            interest_rate
            status
            statement_day
            due_day
            start_date
            cycles {
                id
                period_month
                period_start_date
                statement_date
                due_date
                total_spent
                total_due
                interest_amount
                status
                expenses {
                    id
                    amount
                    description
                    spent_at
                }
            }
        }
    }
`;

const { result, loading } = useQuery(CARD_DETAIL_QUERY, () => ({ id: route.params.id }));
const card = computed(() => result.value?.creditCard);
const cycles = computed(() => card.value?.cycles ?? []);

function toggleCycle(cycleId) {
    if (expandedCycles.value.has(cycleId)) {
        expandedCycles.value.delete(cycleId);
    } else {
        expandedCycles.value.add(cycleId);
    }
}

function cycleStatusClass(status) {
    const map = {
        open: 'bg-blue-500/10 text-blue-400',
        closed: 'bg-gray-500/10 text-gray-500',
        paid: 'bg-emerald-500/10 text-emerald-400',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

function availablePct(currentCard) {
    if (!currentCard?.credit_limit) return 100;
    return Math.round(((currentCard.available_credit ?? 0) / currentCard.credit_limit) * 100);
}
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="card">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ card.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ card.type }} card · {{ card.interest_rate }}% interest</p>
                </div>
                <router-link
                    :to="`/credit-cards/${card.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-600 bg-gray-100 px-4 text-sm text-gray-900 transition-colors hover:bg-gray-50"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 grid grid-cols-2 gap-6 md:grid-cols-3">
                    <div>
                        <p class="text-sm text-gray-500">Current Balance</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-purple-400">{{ formatCurrency(card.current_balance) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Credit Limit</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-gray-900">{{ formatCurrency(card.credit_limit ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Available</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-purple-400">{{ formatCurrency(card.available_credit ?? 0) }}</p>
                    </div>
                </div>

                <div class="mb-1 h-2 w-full rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-purple-500" :style="{ width: `${availablePct(card)}%` }" />
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>{{ availablePct(card) }}% available</span>
                    <span>Statement day {{ card.statement_day }} · Due day {{ card.due_day }}</span>
                </div>
            </div>

            <h2 class="mb-4 text-xl font-semibold text-gray-900">Billing Cycles</h2>

            <div v-if="!cycles.length" class="py-8 text-center text-sm text-gray-500">
                No billing cycles yet.
            </div>

            <div class="flex flex-col gap-3">
                <div
                    v-for="cycle in cycles"
                    :key="cycle.id"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white"
                >
                    <button
                        class="flex w-full items-center justify-between p-4 transition-colors hover:bg-gray-100/40"
                        @click="toggleCycle(cycle.id)"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="min-w-0 text-left">
                                <p class="text-sm font-normal text-gray-900">{{ cycle.period_month ?? cycle.period_start_date }}</p>
                                <p class="mt-0.5 text-sm text-gray-500">Due: {{ cycle.due_date ?? '—' }}</p>
                            </div>
                            <span :class="cycleStatusClass(cycle.status)" class="shrink-0 rounded px-2 py-0.5 text-sm capitalize">
                                {{ cycle.status }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4">
                            <p class="font-mono text-sm text-purple-400">{{ formatCurrency(cycle.total_spent) }}</p>
                            <component :is="expandedCycles.has(cycle.id) ? ChevronUpIcon : ChevronDownIcon" class="h-4 w-4 shrink-0 text-gray-500" />
                        </div>
                    </button>

                    <div v-if="expandedCycles.has(cycle.id)" class="border-t border-gray-200 px-4 py-3">
                        <div v-if="!cycle.expenses?.length" class="py-4 text-center text-sm text-gray-500">
                            No expenses in this cycle.
                        </div>
                        <div v-else class="divide-y divide-gray-700/50">
                            <div
                                v-for="expense in cycle.expenses"
                                :key="expense.id"
                                class="flex items-center justify-between py-3"
                            >
                                <div>
                                    <p class="text-sm text-gray-900">{{ expense.description ?? 'Expense' }}</p>
                                    <p class="text-sm text-gray-500">{{ expense.spent_at }}</p>
                                </div>
                                <p class="font-mono text-sm text-purple-400">{{ formatCurrency(expense.amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
