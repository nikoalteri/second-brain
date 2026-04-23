<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import AppLayout from '@/components/layout/AppLayout.vue';
import BudgetAlertPanel from '@/components/reports/BudgetAlertPanel.vue';
import KpiCard from '@/components/ui/KpiCard.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useToast } from '@/composables/useToast.js';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, LineElement, PointElement, Filler, Title, Tooltip, Legend);

const { formatCurrency } = useCurrency();
const { addToast } = useToast();
const { locale } = useUserPreferences();
const auth = useAuthStore();
const now = new Date();
const year = now.getFullYear();
const month = now.getMonth() + 1;
const accounts = ref([]);
const accountsLoading = ref(false);
const upcomingPayments = ref([]);
const upcomingLoading = ref(false);
const chartsLoading = ref(false);
const budgetAlertsLoading = ref(false);
const budgetAlerts = ref([]);
const dashboardCharts = ref({
    month_label: now.toLocaleString(locale.value, { month: 'long' }),
    cashflow: {
        income: 0,
        expenses: 0,
        payments: 0,
        net: 0,
    },
    expense_categories: [],
    net_worth_trend: [],
});

const cashflow = computed(() => dashboardCharts.value.cashflow ?? {
    income: 0,
    expenses: 0,
    payments: 0,
    net: 0,
});
const categories = computed(() => dashboardCharts.value.expense_categories ?? []);
const netWorthTrend = computed(() => dashboardCharts.value.net_worth_trend ?? []);
const totalBalance = computed(() =>
    accounts.value.reduce((sum, account) => sum + (account.balance ?? 0), 0)
);
const totalIncome = computed(() => cashflow.value.income ?? 0);
const totalExpense = computed(() => cashflow.value.expenses ?? 0);
const totalPayments = computed(() => cashflow.value.payments ?? 0);
const totalOutflow = computed(() => totalExpense.value + totalPayments.value);
const netCashflow = computed(() => cashflow.value.net ?? (totalIncome.value - totalExpense.value - totalPayments.value));
const loading = computed(() => chartsLoading.value || accountsLoading.value || upcomingLoading.value);
const monthLabel = computed(() => dashboardCharts.value.month_label ?? now.toLocaleString(locale.value, { month: 'long' }));
const budgetMonthLabel = computed(() => now.toLocaleString(locale.value, { month: 'long', year: 'numeric' }));
const accountCount = computed(() => accounts.value.length);
const upcomingCount = computed(() => upcomingPayments.value.length);
const upcomingDueTotal = computed(() =>
    upcomingPayments.value.reduce((sum, payment) => sum + (payment.amount ?? 0), 0)
);
const upcomingPostedCount = computed(() =>
    upcomingPayments.value.filter((payment) => payment.transaction_posted).length
);
const upcomingReminderOnlyCount = computed(() =>
    upcomingPayments.value.filter((payment) => payment.type === 'subscription' && !payment.auto_create_transaction).length
);
const upcomingAutoPendingCount = computed(() =>
    upcomingPayments.value.filter((payment) => !payment.transaction_posted && !(payment.type === 'subscription' && !payment.auto_create_transaction)).length
);
const loanDueCount = computed(() => upcomingPayments.value.filter((payment) => payment.type === 'loan').length);
const creditCardDueCount = computed(() => upcomingPayments.value.filter((payment) => payment.type === 'credit-card').length);
const subscriptionDueCount = computed(() => upcomingPayments.value.filter((payment) => payment.type === 'subscription').length);
const nextDuePayment = computed(() => upcomingPayments.value[0] ?? null);
const topCategories = computed(() =>
    [...categories.value]
        .sort((left, right) => (right.total ?? 0) - (left.total ?? 0))
        .slice(0, 5)
);
const topCategory = computed(() => topCategories.value[0] ?? null);
const topCategoryMax = computed(() =>
    topCategories.value.length ? Math.max(...topCategories.value.map((category) => category.total ?? 0)) : 0
);
const topCategoryShare = computed(() => {
    if (!topCategory.value || totalExpense.value <= 0) {
        return 0;
    }

    return (topCategory.value.total ?? 0) / totalExpense.value;
});
const categoryTransactionCount = computed(() =>
    categories.value.reduce((sum, category) => sum + (category.count ?? 0), 0)
);
const savingsRate = computed(() => {
    if (totalIncome.value <= 0) {
        return null;
    }

    return netCashflow.value / totalIncome.value;
});

const cashflowChartData = computed(() => ({
    labels: ['This Month'],
    datasets: [
        {
            label: 'Income',
            data: [totalIncome.value],
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderRadius: 8,
        },
        {
            label: 'Expenses',
            data: [totalExpense.value],
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderRadius: 8,
        },
        {
            label: 'Payments',
            data: [totalPayments.value],
            backgroundColor: 'rgba(245, 158, 11, 0.7)',
            borderRadius: 8,
        },
    ],
}));

const expenseChartColors = [
    '#FF6384',
    '#36A2EB',
    '#FFCE56',
    '#4BC0C0',
    '#9966FF',
    '#FF9F40',
    '#14b8a6',
    '#8b5cf6',
];

const expenseBreakdownChartData = computed(() => ({
    labels: categories.value.map((category) => category.category),
    datasets: [
        {
            label: 'Amount',
            data: categories.value.map((category) => category.total ?? 0),
            backgroundColor: categories.value.map((_, index) => expenseChartColors[index % expenseChartColors.length]),
            borderColor: '#ffffff',
            borderWidth: 2,
        },
    ],
}));

const netWorthTrendChartData = computed(() => ({
    labels: netWorthTrend.value.map((point) => point.label),
    datasets: [
        {
            label: 'Net Worth',
            data: netWorthTrend.value.map((point) => point.value ?? 0),
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.12)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 4,
        },
    ],
}));

const cashflowChartOptions = {
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
            grid: { color: '#f3f4f6' },
            ticks: { color: '#6b7280', font: { size: 12 } },
        },
        y: {
            grid: { color: '#f3f4f6' },
            ticks: {
                color: '#6b7280',
                font: { size: 12 },
                callback: (value) => `EUR ${value}`,
            },
        },
    },
};

const expenseBreakdownChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                color: '#6b7280',
                boxWidth: 12,
                font: { family: 'Figtree, system-ui', size: 12 },
            },
        },
        tooltip: {
            backgroundColor: '#ffffff',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            titleColor: '#111827',
            bodyColor: '#4b5563',
            callbacks: {
                label: (context) => `${context.label}: ${formatCurrency(context.raw)}`,
            },
        },
    },
};

const netWorthTrendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
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
            grid: { display: false },
            ticks: { color: '#6b7280', font: { size: 12 } },
        },
        y: {
            grid: { color: '#f3f4f6' },
            ticks: {
                color: '#6b7280',
                font: { size: 12 },
                callback: (value) => `EUR ${value}`,
            },
        },
    },
};

function formatPercent(value) {
    if (value === null || Number.isNaN(value)) {
        return 'N/A';
    }

    return `${Math.round(value * 100)}%`;
}

function categoryBarWidth(total) {
    if (!topCategoryMax.value) {
        return '0%';
    }

    return `${Math.max(10, Math.round(((total ?? 0) / topCategoryMax.value) * 100))}%`;
}

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

function upcomingMetaLabel(payment) {
    if (payment.type === 'subscription') {
        const source = payment.payment_source_type === 'credit-card' ? 'Credit card' : 'Account';
        return `${payment.frequency_label ?? 'Recurring'} · ${source}`;
    }

    return payment.transaction_posted ? 'Already reflected' : 'Needs attention';
}

function netCashflowTone() {
    return netCashflow.value >= 0 ? 'text-emerald-500' : 'text-red-500';
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

async function fetchDashboardCharts() {
    if (!auth.accessToken) {
        dashboardCharts.value = {
            month_label: now.toLocaleString(locale.value, { month: 'long' }),
            cashflow: {
                income: 0,
                expenses: 0,
                payments: 0,
                net: 0,
            },
            expense_categories: [],
            net_worth_trend: [],
        };
        return;
    }

    chartsLoading.value = true;

    try {
        const response = await fetch(`/api/v1/dashboard/charts?year=${year}&month=${month}`, {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            dashboardCharts.value = {
                month_label: now.toLocaleString(locale.value, { month: 'long' }),
                cashflow: {
                    income: 0,
                    expenses: 0,
                    payments: 0,
                    net: 0,
                },
                expense_categories: [],
                net_worth_trend: [],
            };
            return;
        }

        const data = await response.json();
        dashboardCharts.value = data.data ?? dashboardCharts.value;
    } finally {
        chartsLoading.value = false;
    }
}

async function fetchAccounts() {
    if (!auth.accessToken) {
        accounts.value = [];
        return;
    }

    accountsLoading.value = true;

    try {
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
    } finally {
        accountsLoading.value = false;
    }
}

async function fetchBudgetAlerts() {
    if (!auth.accessToken) {
        budgetAlerts.value = [];
        return;
    }

    budgetAlertsLoading.value = true;

    try {
        const response = await fetch(`/api/v1/budgets/monthly?year=${year}&month=${month}`, {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            budgetAlerts.value = [];
            return;
        }

        const data = await response.json();
        budgetAlerts.value = (data.categories ?? []).filter((category) =>
            ['warning', 'exceeded', 'critical'].includes(category.alert_status)
        );
    } catch (error) {
        budgetAlerts.value = [];
        addToast('Could not load current budget alerts. Please try again.', 'error');
    } finally {
        budgetAlertsLoading.value = false;
    }
}

onMounted(() => {
    void fetchDashboardCharts();
    void fetchAccounts();
    void fetchUpcomingPayments();
    void fetchBudgetAlerts();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Overview first, urgent items next, and deeper finance insights after that.</p>
            </div>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else>
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ monthLabel }} pulse</p>
                            <h2 class="mt-2 text-2xl font-semibold text-gray-900">Financial overview</h2>
                            <p class="mt-2 text-sm text-gray-500">A faster snapshot of what is healthy, what is due soon, and where money is moving this month.</p>
                        </div>
                        <div class="rounded-2xl bg-gray-50 px-4 py-3 text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Net cashflow</p>
                            <p class="mt-1 font-mono text-3xl font-semibold" :class="netCashflowTone()">{{ formatCurrency(netCashflow) }}</p>
                            <p class="mt-1 text-sm text-gray-500">
                                Savings rate: <span class="font-medium text-gray-700">{{ formatPercent(savingsRate) }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total balance</p>
                            <p class="mt-2 font-mono text-2xl font-semibold text-blue-500">{{ formatCurrency(totalBalance) }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ accountCount }} tracked account{{ accountCount === 1 ? '' : 's' }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Income this month</p>
                            <p class="mt-2 font-mono text-2xl font-semibold text-emerald-500">{{ formatCurrency(totalIncome) }}</p>
                            <p class="mt-1 text-sm text-gray-500">Current month inflows</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Expenses this month</p>
                            <p class="mt-2 font-mono text-2xl font-semibold text-red-500">{{ formatCurrency(totalOutflow) }}</p>
                            <p class="mt-1 text-sm text-gray-500">Includes spending and payment outflows this month</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Attention needed</p>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">Next 3 days</h2>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-700 shadow-sm">
                            {{ upcomingCount }} item{{ upcomingCount === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="nextDuePayment" class="mt-5 rounded-2xl border border-amber-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ upcomingPaymentTypeLabel(nextDuePayment) }}</p>
                                <p class="mt-1 truncate text-base font-semibold text-gray-900">{{ nextDuePayment.description }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ nextDuePayment.due_date }} · {{ upcomingMetaLabel(nextDuePayment) }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                {{ upcomingPaymentStatus(nextDuePayment) }}
                            </span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-3">
                            <p class="font-mono text-2xl font-semibold text-gray-900">{{ formatCurrency(nextDuePayment.amount) }}</p>
                            <p class="text-xs font-medium text-gray-500">{{ upcomingPostingLabel(nextDuePayment) }}</p>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-2xl border border-dashed border-amber-300 bg-white/70 p-5 text-sm text-gray-600">
                        No urgent payments in the next 3 days.
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                        <div class="rounded-xl border border-amber-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total due soon</p>
                            <p class="mt-2 font-mono text-xl font-semibold text-gray-900">{{ formatCurrency(upcomingDueTotal) }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Needs automatic posting</p>
                            <p class="mt-2 text-xl font-semibold text-gray-900">{{ upcomingAutoPendingCount }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reminder only</p>
                            <p class="mt-2 text-xl font-semibold text-gray-900">{{ upcomingReminderOnlyCount }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Accounts tracked" :value="String(accountCount)" color="blue" delta="Active balances included in overview" />
                <KpiCard label="Upcoming items" :value="String(upcomingCount)" color="amber" delta="Loans, cards, and subscriptions due soon" />
                <KpiCard label="Already posted" :value="String(upcomingPostedCount)" color="emerald" delta="Items already reflected in transactions or card expenses" />
                <KpiCard
                    label="Top category"
                    :value="topCategory ? topCategory.category : 'No data'"
                    color="purple"
                    :delta="topCategory ? `${formatCurrency(topCategory.total)} · ${formatPercent(topCategoryShare)}` : 'No categorized spending yet'"
                />
            </div>

            <div class="mt-6">
                <LoadingSpinner v-if="budgetAlertsLoading" class="py-10" />
                <BudgetAlertPanel
                    v-else
                    :alerts="budgetAlerts"
                    title="Budget Alerts"
                    description="Current-month warning, exceeded, and critical categories from the shared budget contract."
                    :month-label="budgetMonthLabel"
                    empty-label="No current dashboard budget alerts."
                    compact
                />
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Upcoming payments</h2>
                            <p class="mt-1 text-sm text-gray-500">Urgent reminders for loans, credit cards, and subscriptions with posting-state context.</p>
                        </div>
                        <div class="grid shrink-0 grid-cols-3 gap-2 text-center text-sm">
                            <div class="rounded-xl bg-gray-50 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Loans</p>
                                <p class="mt-1 font-semibold text-gray-900">{{ loanDueCount }}</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Cards</p>
                                <p class="mt-1 font-semibold text-gray-900">{{ creditCardDueCount }}</p>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Subs</p>
                                <p class="mt-1 font-semibold text-gray-900">{{ subscriptionDueCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="upcomingPayments.length" class="mt-4 grid gap-3 md:grid-cols-2">
                        <div
                            v-for="payment in upcomingPayments"
                            :key="payment.id"
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ upcomingPaymentTypeLabel(payment) }}</p>
                                    <p class="mt-1 truncate text-sm font-medium text-gray-900">{{ payment.description }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ payment.due_date }} · {{ upcomingMetaLabel(payment) }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm">
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

                    <div v-else class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
                        Nothing urgent is due in the next 3 days.
                    </div>
                </section>

                <section class="grid gap-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Automation snapshot</h2>
                        <p class="mt-1 text-sm text-gray-500">Shows how much of the upcoming work is already reflected automatically.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                            <div class="rounded-xl bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Already posted</p>
                                <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ upcomingPostedCount }}</p>
                            </div>
                            <div class="rounded-xl bg-amber-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending auto-post</p>
                                <p class="mt-2 text-2xl font-semibold text-amber-700">{{ upcomingAutoPendingCount }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-100 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Reminder only</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-700">{{ upcomingReminderOnlyCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900">Spending highlights</h2>
                        <p class="mt-1 text-sm text-gray-500">The Filament-style category breakdown plus the largest expense buckets for this month.</p>

                        <div v-if="topCategories.length" class="mt-4 space-y-4">
                            <div class="h-64 rounded-xl bg-gray-50 p-4">
                                <Doughnut :data="expenseBreakdownChartData" :options="expenseBreakdownChartOptions" />
                            </div>

                            <div v-if="topCategory" class="rounded-xl bg-gray-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Largest category</p>
                                <div class="mt-2 flex items-end justify-between gap-3">
                                    <p class="text-base font-semibold text-gray-900">{{ topCategory.category }}</p>
                                    <p class="font-mono text-lg font-semibold text-red-500">{{ formatCurrency(topCategory.total) }}</p>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ formatPercent(topCategoryShare) }} of this month's total expenses</p>
                            </div>

                            <div class="space-y-3">
                                <div v-for="category in topCategories" :key="category.category">
                                    <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                        <p class="truncate text-gray-700">{{ category.category }}</p>
                                        <p class="font-mono font-medium text-gray-900">{{ formatCurrency(category.total) }}</p>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full bg-amber-400" :style="{ width: categoryBarWidth(category.total) }" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
                            No categorized expense data yet for this month.
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Cashflow comparison</h2>
                            <p class="mt-1 text-sm text-gray-500">Mirrors the Filament monthly cashflow graph with income, expenses, and payments.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            {{ monthLabel }}
                        </span>
                    </div>

                    <div class="mt-4 h-72">
                        <Bar :data="cashflowChartData" :options="cashflowChartOptions" />
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Net worth trend</h2>
                            <p class="mt-1 text-sm text-gray-500">A 12-month line chart so the dashboard matches the Filament trend view.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            12 months
                        </span>
                    </div>

                    <div class="mt-4 h-72">
                        <Line :data="netWorthTrendChartData" :options="netWorthTrendChartOptions" />
                    </div>
                </section>
            </div>

            <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Quick health checks</h2>
                        <p class="mt-1 text-sm text-gray-500">Short answers to the questions you usually need first.</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2 text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Payments this month</p>
                        <p class="mt-1 font-mono text-lg font-semibold text-amber-600">{{ formatCurrency(totalPayments) }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-3">
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cashflow health</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900">
                            {{ netCashflow >= 0 ? 'You are cashflow positive this month.' : 'You are spending more than you earn this month.' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Biggest focus area</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900">
                            {{ topCategory ? topCategory.category : 'Waiting for categorized expenses' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ topCategory ? `${formatCurrency(topCategory.total)} spent here so far.` : 'Once transactions are categorized, this section will call out your top cost driver.' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reminder coverage</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900">
                            {{ upcomingCount ? `${upcomingCount} reminder${upcomingCount === 1 ? '' : 's'} in the next 3 days` : 'No imminent reminders' }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ upcomingCount ? `${upcomingPostedCount} already reflected, ${upcomingAutoPendingCount} still waiting on automatic posting.` : 'The dashboard is clear for now.' }}
                        </p>
                    </div>
                </div>
            </section>
        </template>
    </AppLayout>
</template>
