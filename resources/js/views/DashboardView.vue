<script setup>
import { computed } from 'vue';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { Bar } from 'vue-chartjs';
import AppLayout from '@/components/layout/AppLayout.vue';
import KpiCard from '@/components/ui/KpiCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const { formatCurrency } = useCurrency();
const now = new Date();
const year = now.getFullYear();
const month = now.getMonth() + 1;

const CASHFLOW_QUERY = gql`
    query GetMonthlyCashflow($year: Int!, $month: Int!) {
        monthlyCashflow(year: $year, month: $month) {
            year
            month
            total_income
            total_expense
            net
        }
    }
`;

const CATEGORIES_QUERY = gql`
    query GetCategoryTotals($year: Int!, $month: Int!) {
        totalByCategory(year: $year, month: $month) {
            category
            total
            count
        }
    }
`;

const ACCOUNTS_QUERY = gql`
    query GetAccounts {
        accounts(first: 100) {
            data {
                id
                balance
                currency
            }
        }
    }
`;

const { result: cashflowResult, loading: cashflowLoading } = useQuery(CASHFLOW_QUERY, { year, month });
const { result: categoriesResult, loading: categoriesLoading } = useQuery(CATEGORIES_QUERY, { year, month });
const { result: accountsResult, loading: accountsLoading } = useQuery(ACCOUNTS_QUERY);

const cashflow = computed(() => cashflowResult.value?.monthlyCashflow ?? {
    total_income: 0,
    total_expense: 0,
    net: 0,
});
const categories = computed(() => categoriesResult.value?.totalByCategory ?? []);
const totalBalance = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).reduce((sum, account) => sum + (account.balance ?? 0), 0)
);
const netCashflow = computed(() => cashflow.value.net ?? (cashflow.value.total_income - cashflow.value.total_expense));
const loading = computed(() => cashflowLoading.value || categoriesLoading.value || accountsLoading.value);

const chartData = computed(() => ({
    labels: ['This Month'],
    datasets: [
        {
            label: 'Income',
            data: [cashflow.value.total_income ?? 0],
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderRadius: 4,
        },
        {
            label: 'Expenses',
            data: [cashflow.value.total_expense ?? 0],
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderRadius: 4,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#9ca3af',
                font: { family: 'Figtree, system-ui', size: 13 },
            },
        },
        tooltip: {
            backgroundColor: '#1f2937',
            borderColor: '#374151',
            borderWidth: 1,
            titleColor: '#f3f4f6',
            bodyColor: '#9ca3af',
            callbacks: {
                label: (context) => formatCurrency(context.raw),
            },
        },
    },
    scales: {
        x: {
            grid: { color: '#374151' },
            ticks: { color: '#9ca3af', font: { size: 12 } },
        },
        y: {
            grid: { color: '#374151' },
            ticks: {
                color: '#9ca3af',
                font: { size: 12 },
                callback: (value) => `EUR ${value}`,
            },
        },
    },
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-white">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-400">Your financial overview for this month</p>
            </div>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else>
            <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <KpiCard label="Total Balance" :value="formatCurrency(totalBalance)" color="blue" />
                <KpiCard label="Monthly Income" :value="formatCurrency(cashflow.total_income)" color="emerald" />
                <KpiCard label="Monthly Expenses" :value="formatCurrency(cashflow.total_expense)" color="red" />
                <KpiCard
                    label="Net Cashflow"
                    :value="formatCurrency(netCashflow)"
                    :color="netCashflow >= 0 ? 'emerald' : 'red'"
                />
            </div>

            <div class="mb-6 h-72 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <Bar :data="chartData" :options="chartOptions" />
            </div>

            <div v-if="categories.length" class="mt-6">
                <h2 class="mb-4 text-xl font-semibold text-white">Spending by Category</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <div
                        v-for="category in categories"
                        :key="category.category"
                        class="rounded-xl border border-gray-700 bg-gray-800 p-4"
                    >
                        <p class="truncate text-sm font-normal text-gray-400">{{ category.category }}</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-red-400">
                            {{ formatCurrency(category.total) }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
