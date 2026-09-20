<script setup>
import { computed, onMounted, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { ArrowsRightLeftIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import DataTable from '@/components/ui/DataTable.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { categoryIcon } from '@/icons/domainIcons.js';
import { useLocalizedLabels } from '@/composables/useLocalizedLabels.js';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const { colorClass, formatSigned } = useCurrency();
const { translateCategoryName, translateTransactionType } = useLocalizedLabels();
const { locale } = useUserPreferences();
const auth = useAuthStore();

const page = ref(1);
const filterAccountId = ref(null);
const filterTypeId = ref(null);
const filterCategoryId = ref(null);
const filterDateFrom = ref('');
const filterDateTo = ref('');
const sortColumn = ref('DATE');
const sortOrder = ref('ASC');

const currentMonthKey = new Date().toISOString().slice(0, 7);
const openMonths = ref(new Set([currentMonthKey]));

const TYPES_QUERY = gql`
    query GetTransactionTypes {
        transactionTypes {
            id
            name
        }
    }
`;

const CATEGORIES_QUERY = gql`
    query GetTransactionCategories {
        transactionCategories {
            id
            name
        }
    }
`;

const TRANSACTIONS_QUERY = gql`
    query GetTransactions(
        $page: Int
        $account_id: ID
        $transaction_type_id: ID
        $transaction_category_id: ID
        $orderBy: [QueryTransactionsOrderByOrderByClause!]
    ) {
        transactions(
            first: 100
            page: $page
            account_id: $account_id
            transaction_type_id: $transaction_type_id
            transaction_category_id: $transaction_category_id
            orderBy: $orderBy
        ) {
            data {
                id
                account_id
                transaction_type_id
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

const { result: typesResult } = useQuery(TYPES_QUERY, null, { fetchPolicy: 'network-only' });
const { result: categoriesResult } = useQuery(CATEGORIES_QUERY, null, {
    fetchPolicy: 'network-only',
});
const { result, loading, error } = useQuery(TRANSACTIONS_QUERY, () => ({
    page: page.value,
    account_id: filterAccountId.value || undefined,
    transaction_type_id: filterTypeId.value || undefined,
    transaction_category_id: filterCategoryId.value || undefined,
    orderBy: [{ column: sortColumn.value, order: sortOrder.value }],
}));

const accounts = ref([]);
const accountOptions = computed(() => [
    { value: '', label: 'All accounts' },
    ...accounts.value.map((account) => ({ value: account.id, label: account.name })),
]);
const types = computed(() => typesResult.value?.transactionTypes ?? []);
const typeOptions = computed(() => [
    { value: '', label: 'All types' },
    ...types.value.map((type) => ({ value: type.id, label: translateTransactionType(type.name) })),
]);
const categories = computed(() => categoriesResult.value?.transactionCategories ?? []);
const categoryOptions = computed(() => [
    { value: '', label: 'All categories' },
    ...categories.value.map((category) => ({ value: category.id, label: translateCategoryName(category.name) })),
]);
const accountNameById = computed(() =>
    Object.fromEntries(accounts.value.map((account) => [String(account.id), account.name]))
);
const categoryNameById = computed(() =>
    Object.fromEntries(categories.value.map((category) => [String(category.id), translateCategoryName(category.name)]))
);
const categoryIconById = computed(() =>
    Object.fromEntries(categories.value.map((category) => [String(category.id), categoryIcon(category.name)]))
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

const monthGroups = computed(() => {
    const groups = [];
    const byKey = new Map();

    for (const transaction of filteredTransactions.value) {
        const key = (transaction.date ?? '').slice(0, 7);

        if (!byKey.has(key)) {
            const group = { key, label: monthLabel(key), items: [] };
            byKey.set(key, group);
            groups.push(group);
        }

        byKey.get(key).items.push(transaction);
    }

    return groups;
});

function monthLabel(key) {
    if (!key) {
        return 'Unknown date';
    }

    const [year, month] = key.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleString(locale.value, { month: 'long', year: 'numeric' });
}

function isMonthOpen(key) {
    return openMonths.value.has(key);
}

function toggleMonth(key) {
    const next = new Set(openMonths.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    openMonths.value = next;
}

function toggleSort(column) {
    if (sortColumn.value === column) {
        sortOrder.value = sortOrder.value === 'ASC' ? 'DESC' : 'ASC';
    } else {
        sortColumn.value = column;
        sortOrder.value = 'ASC';
    }

    page.value = 1;
}

function applyAccountFilter(value) {
    filterAccountId.value = value || null;
    page.value = 1;
}

function applyTypeFilter(value) {
    filterTypeId.value = value || null;
    page.value = 1;
}

function applyCategoryFilter(value) {
    filterCategoryId.value = value || null;
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
            <select
                :value="filterTypeId ?? ''"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                @change="applyTypeFilter($event.target.value)"
            >
                <option v-for="option in typeOptions" :key="String(option.value)" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <select
                :value="filterCategoryId ?? ''"
                class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                @change="applyCategoryFilter($event.target.value)"
            >
                <option v-for="option in categoryOptions" :key="String(option.value)" :value="option.value">
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
            :per-page="100"
            @page-change="page = $event"
        >
            <template #thead>
                <th class="w-28 pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">
                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-900" @click="toggleSort('DATE')">
                        Date
                        <ChevronUpIcon v-if="sortColumn === 'DATE' && sortOrder === 'ASC'" class="h-3.5 w-3.5" />
                        <ChevronDownIcon v-else-if="sortColumn === 'DATE' && sortOrder === 'DESC'" class="h-3.5 w-3.5" />
                    </button>
                </th>
                <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">
                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-900" @click="toggleSort('DESCRIPTION')">
                        Description
                        <ChevronUpIcon v-if="sortColumn === 'DESCRIPTION' && sortOrder === 'ASC'" class="h-3.5 w-3.5" />
                        <ChevronDownIcon v-else-if="sortColumn === 'DESCRIPTION' && sortOrder === 'DESC'" class="h-3.5 w-3.5" />
                    </button>
                </th>
                <th class="hidden pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500 lg:table-cell">Category</th>
                <th class="hidden pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500 md:table-cell">Account</th>
                <th class="pb-3 text-right text-sm font-normal uppercase tracking-wide text-gray-500">
                    <button type="button" class="ml-auto inline-flex items-center gap-1 hover:text-gray-900" @click="toggleSort('AMOUNT')">
                        Amount
                        <ChevronUpIcon v-if="sortColumn === 'AMOUNT' && sortOrder === 'ASC'" class="h-3.5 w-3.5" />
                        <ChevronDownIcon v-else-if="sortColumn === 'AMOUNT' && sortOrder === 'DESC'" class="h-3.5 w-3.5" />
                    </button>
                </th>
            </template>

            <template #tbody>
                <template v-for="group in monthGroups" :key="group.key">
                    <tr
                        class="cursor-pointer bg-gray-50/80 transition-colors hover:bg-gray-100"
                        @click="toggleMonth(group.key)"
                    >
                        <td colspan="5" class="py-2 pr-4 text-sm font-medium text-gray-700">
                            <span class="inline-flex items-center gap-1.5">
                                <ChevronDownIcon v-if="isMonthOpen(group.key)" class="h-4 w-4 text-gray-400" />
                                <ChevronUpIcon v-else class="h-4 w-4 text-gray-400" />
                                {{ group.label }}
                                <span class="text-gray-400">· {{ group.items.length }}</span>
                            </span>
                        </td>
                    </tr>
                    <template v-if="isMonthOpen(group.key)">
                        <tr
                            v-for="transaction in group.items"
                            :key="transaction.id"
                            class="cursor-pointer transition-colors duration-100 hover:bg-gray-100/40"
                            @click="router.push(`/transactions/${transaction.id}/edit`)"
                        >
                            <td class="w-28 py-3 pr-4 text-sm text-gray-500">{{ transaction.date }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-900">{{ transaction.description }}</td>
                            <td class="hidden py-3 pr-4 text-sm text-gray-500 lg:table-cell">
                                <span v-if="categoryNameById[String(transaction.transaction_category_id)]" class="inline-flex items-center gap-1">
                                    <component :is="categoryIconById[String(transaction.transaction_category_id)]" class="h-4 w-4" />
                                    {{ categoryNameById[String(transaction.transaction_category_id)] }}
                                </span>
                                <template v-else>—</template>
                            </td>
                            <td class="hidden py-3 pr-4 text-sm text-gray-500 md:table-cell">
                                {{ accountNameById[String(transaction.account_id)] ?? '—' }}
                            </td>
                            <td class="py-3 text-right font-mono text-sm" :class="colorClass(transaction.amount, 'signed')">
                                {{ formatSigned(transaction.amount) }}
                            </td>
                        </tr>
                    </template>
                </template>
            </template>

            <template #mobile>
                <div v-for="group in monthGroups" :key="group.key" class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700"
                        @click="toggleMonth(group.key)"
                    >
                        <ChevronDownIcon v-if="isMonthOpen(group.key)" class="h-4 w-4 text-gray-400" />
                        <ChevronUpIcon v-else class="h-4 w-4 text-gray-400" />
                        {{ group.label }}
                        <span class="text-gray-400">· {{ group.items.length }}</span>
                    </button>
                    <template v-if="isMonthOpen(group.key)">
                        <div
                            v-for="transaction in group.items"
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
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
