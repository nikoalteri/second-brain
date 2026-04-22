<script setup>
import { computed } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { ArrowsRightLeftIcon, PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import DataTable from '@/components/ui/DataTable.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const route = useRoute();
const { formatCurrency, colorClass, formatSigned } = useCurrency();
const accountTypeLabels = {
    bank: 'Bank',
    cash: 'Cash',
    investment: 'Investment',
    emergency_fund: 'Emergency Fund',
    debt: 'Debt',
};

const ACCOUNT_DETAIL_QUERY = gql`
    query GetAccount($id: ID!) {
        account(id: $id) {
            id
            name
            type
            balance
            currency
            is_active
            transactions {
                id
                amount
                date
                description
                is_transfer
                category {
                    id
                    name
                }
            }
        }
    }
`;

const { result, loading, error } = useQuery(ACCOUNT_DETAIL_QUERY, () => ({ id: route.params.id }));
const account = computed(() => result.value?.account);
const transactions = computed(() => account.value?.transactions ?? []);
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="account">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-white">{{ account.name }}</h1>
                    <p class="mt-1 text-sm text-gray-400">{{ accountTypeLabels[account.type] ?? account.type }} · {{ account.currency }}</p>
                </div>
                <router-link
                    :to="`/accounts/${account.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="mb-6 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <p class="text-sm font-normal uppercase tracking-wide text-gray-400">Current Balance</p>
                <p class="mt-1 font-mono text-3xl font-semibold text-blue-400">
                    {{ formatCurrency(account.balance, account.currency) }}
                </p>
            </div>

            <h2 class="mb-4 text-xl font-semibold text-white">Recent Transactions</h2>
            <DataTable
                :loading="false"
                :error="error"
                :empty="!transactions.length"
                empty-title="No transactions"
                empty-message="No transactions found for this account."
                :icon="ArrowsRightLeftIcon"
            >
                <template #thead>
                    <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Date</th>
                    <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Description</th>
                    <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-400">Category</th>
                    <th class="pb-3 text-right text-sm font-normal uppercase tracking-wide text-gray-400">Amount</th>
                </template>

                <template #tbody>
                    <tr v-for="transaction in transactions" :key="transaction.id" class="transition-colors duration-100 hover:bg-gray-700/40">
                        <td class="py-3 pr-4 text-sm text-gray-400">{{ transaction.date }}</td>
                        <td class="py-3 pr-4 text-sm text-gray-100">{{ transaction.description }}</td>
                        <td class="py-3 pr-4 text-sm text-gray-400">{{ transaction.category?.name ?? '—' }}</td>
                        <td class="py-3 text-right font-mono text-sm" :class="colorClass(transaction.amount, 'signed')">
                            {{ formatSigned(transaction.amount) }}
                        </td>
                    </tr>
                </template>

                <template #mobile>
                    <div
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="flex items-start justify-between rounded-lg border border-gray-700 bg-gray-800 p-4"
                    >
                        <div>
                            <p class="text-sm text-gray-100">{{ transaction.description }}</p>
                            <p class="mt-0.5 text-sm text-gray-400">{{ transaction.date }}</p>
                        </div>
                        <span class="font-mono text-sm" :class="colorClass(transaction.amount, 'signed')">
                            {{ formatSigned(transaction.amount) }}
                        </span>
                    </div>
                </template>
            </DataTable>
        </template>
    </AppLayout>
</template>
