<script setup>
import { computed } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const route = useRoute();
const { formatCurrency } = useCurrency();

const LOAN_DETAIL_QUERY = gql`
    query GetLoan($id: ID!) {
        loan(id: $id) {
            id
            name
            total_amount
            remaining_amount
            monthly_payment
            interest_rate
            is_variable_rate
            paid_installments
            total_installments
            status
            start_date
            end_date
            payments {
                id
                due_date
                actual_date
                amount
                status
                notes
            }
        }
    }
`;

const { result, loading } = useQuery(LOAN_DETAIL_QUERY, () => ({ id: route.params.id }));
const loan = computed(() => result.value?.loan);
const payments = computed(() => loan.value?.payments ?? []);

function progressPct(currentLoan) {
    if (!currentLoan?.total_installments) return 0;
    return Math.round((currentLoan.paid_installments / currentLoan.total_installments) * 100);
}

function paymentStatusClass(status) {
    const map = {
        paid: 'bg-emerald-500/10 text-emerald-400',
        pending: 'bg-amber-500/10 text-amber-400',
        overdue: 'bg-red-500/10 text-red-400',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-400';
}
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="loan">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-white">{{ loan.name }}</h1>
                    <p class="mt-1 text-sm text-gray-400">{{ loan.status }} · {{ loan.interest_rate }}% interest</p>
                </div>
                <router-link
                    :to="`/loans/${loan.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <div class="mb-4 grid grid-cols-2 gap-6 md:grid-cols-4">
                    <div>
                        <p class="text-sm text-gray-400">Total Amount</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-amber-400">{{ formatCurrency(loan.total_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Remaining</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-amber-400">{{ formatCurrency(loan.remaining_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Monthly Payment</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-white">{{ formatCurrency(loan.monthly_payment) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-400">Progress</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ progressPct(loan) }}%</p>
                    </div>
                </div>

                <div class="mb-1 h-2 w-full rounded-full bg-gray-700">
                    <div class="h-2 rounded-full bg-amber-500" :style="{ width: `${progressPct(loan)}%` }" />
                </div>
                <div class="flex justify-between text-sm text-gray-400">
                    <span>{{ loan.paid_installments }} paid</span>
                    <span>{{ loan.total_installments }} total</span>
                </div>
            </div>

            <h2 class="mb-4 text-xl font-semibold text-white">Payment Schedule</h2>

            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">#</th>
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Due Date</th>
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Paid Date</th>
                            <th class="pb-3 pr-4 text-right text-sm font-normal uppercase tracking-wide text-gray-400">Amount</th>
                            <th class="pb-3 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <tr v-for="(payment, index) in payments" :key="payment.id" class="transition-colors hover:bg-gray-700/40">
                            <td class="py-3 pr-4 text-sm text-gray-400">{{ index + 1 }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-100">{{ payment.due_date ?? '—' }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-400">{{ payment.actual_date ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right font-mono text-sm text-amber-400">{{ formatCurrency(payment.amount) }}</td>
                            <td class="py-3">
                                <span :class="paymentStatusClass(payment.status)" class="rounded px-2 py-0.5 text-sm capitalize">
                                    {{ payment.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2 sm:hidden">
                <div
                    v-for="(payment, index) in payments"
                    :key="payment.id"
                    class="rounded-lg border border-gray-700 bg-gray-800 p-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-400">Installment #{{ index + 1 }}</p>
                            <p class="mt-0.5 text-sm text-gray-100">Due: {{ payment.due_date ?? '—' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-sm text-amber-400">{{ formatCurrency(payment.amount) }}</p>
                            <span :class="paymentStatusClass(payment.status)" class="mt-1 inline-block rounded px-2 py-0.5 text-sm capitalize">
                                {{ payment.status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
