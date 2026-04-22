<script setup>
import { computed, onMounted, ref } from 'vue';
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
import { useAuthStore } from '@/stores/auth.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const { formatCurrency } = useCurrency();
const auth = useAuthStore();
const now = new Date();
const year = now.getFullYear();
const month = now.getMonth() + 1;
const upcomingPayments = ref([]);
const upcomingLoading = ref(false);

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
const loading = computed(() => cashflowLoading.value || categoriesLoading.value || accountsLoading.value || upcomingLoading.value);

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
                color: '#6b7280',
                font: { family: 'Figtree, system-ui', size: 13 },
            },
        },
        tooltip: {
            backgroundColor: '#ffffff',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            titleColor: '#111827',
            bodyColor: '#4b5563',
            callbacks: {
                label: (context) => formatCurrency(context.raw),
            },
        },
    },
    scales: {
        x: {
            grid: { color: '#e5e7eb' },
            ticks: { color: '#6b7280', font: { size: 12 } },
        },
        y: {
            grid: { color: '#e5e7eb' },
            ticks: {
                color: '#6b7280',
                font: { size: 12 },
                callback: (value) => `EUR ${value}`,
            },
        },
    },
};

function upcomingPaymentStatus(payment) {
    if (payment.days_until_due === 0) {
        return 'Due today';
    }

    if (payment.days_until_due === 1) {
        return 'Due tomorrow';
    }

    return `Due in ${payment.days_until_due} days`;
}

function upcomingPaymentTypeLabel(payment) {
    if (payment.type === 'loan') {
        return 'Loan installment';
    }

    if (payment.type === 'credit-card') {
        return 'Credit card payment';
    }

    return 'Subscription renewal';
}

function upcomingPostingLabel(payment) {
    if (payment.type !== 'subscription') {
        return payment.transaction_posted ? 'Posted to transactions' : 'Will post on due date';
    }

    if (!payment.auto_create_transaction) {
        return 'Reminder only';
    }

    if (payment.transaction_posted) {
        return payment.posting_target === 'credit-card-expense'
            ? 'Added to card expenses'
            : 'Posted to transactions';
    }

    return payment.posting_target === 'credit-card-expense'
        ? 'Will add to card expenses on due date'
        : 'Will post on due date';
}

async function fetchUpcomingPayments() {
    if (!auth.accessToken) {
        upcomingPayments.value = [];
        return;
    }

    upcomingLoading.value = true;

    try {
        const response = await fetch('/api/v1/dashboard/upcoming-payments?days=3', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            upcomingPayments.value = [];
            return;
        }

        const data = await response.json();
        upcomingPayments.value = data.data ?? [];
    } finally {
        upcomingLoading.value = false;
    }
}

onMounted(() => {
    void fetchUpcomingPayments();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Your financial overview for this month</p>
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

            <div v-if="upcomingPayments.length" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Impending payments</h2>
                        <p class="mt-1 text-sm text-gray-600">Upcoming installments, card payments, and subscription renewals due in the next 3 days.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="payment in upcomingPayments"
                        :key="payment.id"
                        class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ upcomingPaymentTypeLabel(payment) }}</p>
                                <p class="mt-1 truncate text-sm font-medium text-gray-900">{{ payment.description }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ payment.due_date }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                {{ upcomingPaymentStatus(payment) }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-end justify-between gap-3">
                            <p class="font-mono text-lg font-semibold text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                            <span
                                class="text-xs font-medium"
                                :class="payment.transaction_posted ? 'text-emerald-600' : 'text-gray-500'"
                            >
                                {{ upcomingPostingLabel(payment) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6 h-72 rounded-xl border border-gray-200 bg-white p-6">
                <Bar :data="chartData" :options="chartOptions" />
            </div>

            <div v-if="categories.length" class="mt-6">
                <h2 class="mb-4 text-xl font-semibold text-gray-900">Spending by Category</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <div
                        v-for="category in categories"
                        :key="category.category"
                        class="rounded-xl border border-gray-200 bg-white p-4"
                    >
                        <p class="truncate text-sm font-normal text-gray-500">{{ category.category }}</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-red-400">
                            {{ formatCurrency(category.total) }}
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
