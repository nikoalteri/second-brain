<script setup>
import { computed, onMounted, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import DataTable from '@/components/ui/DataTable.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const { colorClass, formatSigned } = useCurrency();
const auth = useAuthStore();

const page = ref(1);
const filterAccountId = ref(null);
const filterDateFrom = ref('');
const filterDateTo = ref('');

const CATEGORIES_QUERY = gql`
    query GetTransactionCategories {
        transactionCategories {
            id
            name
        }
    }
`;

const TRANSACTIONS_QUERY = gql`
    query GetTransactions($page: Int, $account_id: ID) {
        transactions(first: 20, page: $page, account_id: $account_id) {
            data {
                id
                account_id
                transaction_category_id
                amount
                date
                description
                is_transfer
            }
            paginatorInfo {
                currentPage
                lastPage
                total
            }
        }
    }
`;

const { result: categoriesResult } = useQuery(CATEGORIES_QUERY, null, {
    fetchPolicy: 'network-only',
});
const { result, loading, error } = useQuery(TRANSACTIONS_QUERY, () => ({
    page: page.value,
    account_id: filterAccountId.value || undefined,
}));

const accounts = ref([]);
const accountOptions = computed(() => [
    { value: '', label: 'All accounts' },
    ...accounts.value.map((account) => ({ value: account.id, label: account.name })),
]);
const categories = computed(() => categoriesResult.value?.transactionCategories ?? []);
const accountNameById = computed(() =>
    Object.fromEntries(accounts.value.map((account) => [String(account.id), account.name]))
);
const categoryNameById = computed(() =>
    Object.fromEntries(categories.value.map((category) => [String(category.id), category.name]))
);
const transactions = computed(() => result.value?.transactions?.data ?? []);
const filteredTransactions = computed(() =>
    transactions.value.filter((transaction) => {
        if (filterDateFrom.value && transaction.date < filterDateFrom.value) {
            return false;
        }

        if (filterDateTo.value && transaction.date > filterDateTo.value) {
            return false;
        }

        return true;
    })
);
const paginator = computed(() => result.value?.transactions?.paginatorInfo);

function applyAccountFilter(value) {
    filterAccountId.value = value || null;
    page.value = 1;
}

async function fetchAccounts() {
    if (!auth.accessToken) {
        accounts.value = [];
        return;
    }

    const response = await fetch('/api/v1/accounts?per_page=100', {
        headers: {
            Authorization: `Bearer ${auth.accessToken}`,
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        accounts.value = [];
        return;
    }

    const data = await response.json();
    accounts.value = data.data ?? [];
}

onMounted(() => {
    void fetchAccounts();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Transactions</h1>
            </div>
            <router-link
                to="/transactions/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add transaction
            </router-link>
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <input
                v-model="filterDateFrom"
                type="date"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
            <input
                v-model="filterDateTo"
                type="date"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            >
            <select
                :value="filterAccountId ?? ''"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                @change="applyAccountFilter($event.target.value)"
            >
                <option v-for="option in accountOptions" :key="String(option.value)" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <DataTable
            :loading="loading"
            :error="error"
            :empty="!filteredTransactions.length"
            empty-title="No transactions found"
            empty-message="Record a transaction or adjust your filters."
            :icon="ArrowsRightLeftIcon"
            action-label="Add transaction"
            action-to="/transactions/new"
            :current-page="paginator?.currentPage ?? 1"
            :last-page="paginator?.lastPage ?? 1"
            :total="paginator?.total ?? 0"
            @page-change="page = $event"
        >
            <template #thead>
                <th class="w-28 pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">Date</th>
                <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">Description</th>
                <th class="hidden pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500 lg:table-cell">Category</th>
                <th class="hidden pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500 md:table-cell">Account</th>
                <th class="pb-3 text-right text-sm font-normal uppercase tracking-wide text-gray-500">Amount</th>
            </template>

            <template #tbody>
                <tr
                    v-for="transaction in filteredTransactions"
                    :key="transaction.id"
                    class="cursor-pointer transition-colors duration-100 hover:bg-gray-100/40"
                    @click="router.push(`/transactions/${transaction.id}/edit`)"
                >
                    <td class="w-28 py-3 pr-4 text-sm text-gray-500">{{ transaction.date }}</td>
                    <td class="py-3 pr-4 text-sm text-gray-900">{{ transaction.description }}</td>
                    <td class="hidden py-3 pr-4 text-sm text-gray-500 lg:table-cell">
                        {{ categoryNameById[String(transaction.transaction_category_id)] ?? '—' }}
                    </td>
                    <td class="hidden py-3 pr-4 text-sm text-gray-500 md:table-cell">
                        {{ accountNameById[String(transaction.account_id)] ?? '—' }}
                    </td>
                    <td class="py-3 text-right font-mono text-sm" :class="colorClass(transaction.amount, 'signed')">
                        {{ formatSigned(transaction.amount) }}
                    </td>
                </tr>
            </template>

            <template #mobile>
                <div
                    v-for="transaction in filteredTransactions"
                    :key="transaction.id"
                    class="flex cursor-pointer items-start justify-between rounded-lg border border-gray-200 bg-white p-4 transition-colors hover:border-gray-300"
                    @click="router.push(`/transactions/${transaction.id}/edit`)"
                >
                    <div class="min-w-0 flex-1 pr-3">
                        <p class="truncate text-sm text-gray-900">{{ transaction.description }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">
                            {{ transaction.date }} · {{ accountNameById[String(transaction.account_id)] ?? '—' }}
                        </p>
                    </div>
                    <span class="shrink-0 font-mono text-sm" :class="colorClass(transaction.amount, 'signed')">
                        {{ formatSigned(transaction.amount) }}
                    </span>
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
