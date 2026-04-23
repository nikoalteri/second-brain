<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
    ArcElement,
    Chart as ChartJS,
    Legend,
    Tooltip,
} from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import AppLayout from '@/components/layout/AppLayout.vue';
import BudgetAlertPanel from '@/components/reports/BudgetAlertPanel.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useLocalizedLabels } from '@/composables/useLocalizedLabels.js';
import { useToast } from '@/composables/useToast.js';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

ChartJS.register(ArcElement, Tooltip, Legend);

const { formatCurrency } = useCurrency();
const { translateCategoryName, translateOptionalCategory } = useLocalizedLabels();
const { addToast } = useToast();
const { locale } = useUserPreferences();
const auth = useAuthStore();

const report = ref(null);
const loading = ref(false);
const loadingDetails = ref(false);
const budgetLoading = ref(false);
const budgetSavingId = ref(null);
const exportingFormat = ref(null);
const selectedYear = ref(null);
const selectedBudgetMonth = ref(new Date().getMonth() + 1);
const selectedTypes = ref([]);
const selectedNote = ref('');
const expandedCategories = ref([]);
const budgetOverview = ref({
    selected_year: new Date().getFullYear(),
    selected_month: new Date().getMonth() + 1,
    period_start: null,
    categories: [],
});
const budgetInputs = ref({});
const detailState = ref({
    open: false,
    month: null,
    categoryKey: null,
    categoryLabel: '',
    transactions: [],
    total: 0,
});

const months = Array.from({ length: 12 }, (_, index) => index + 1);
const monthFormatter = computed(() => new Intl.DateTimeFormat(locale.value, { month: 'short' }));
const monthLabelFormatter = computed(() => new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }));
const pieColors = ['#3b82f6', '#f97316', '#22c55e', '#a855f7', '#ef4444', '#eab308', '#14b8a6', '#ec4899', '#64748b', '#f43f5e'];

const years = computed(() => report.value?.years ?? []);
const typeOptions = computed(() => Object.entries(report.value?.type_options ?? {}).map(([value, label]) => ({
    value: Number(value),
    label,
})));
const noteOptions = computed(() => Object.entries(report.value?.note_options ?? {}).map(([value, label]) => ({
    value,
    label,
})));
const pivot = computed(() => report.value?.pivot ?? { tree: [], pivot: {}, monthTotals: {}, grandTotal: 0 });
const pie = computed(() => report.value?.pie ?? []);
const table = computed(() => report.value?.table ?? []);
const budgetCategories = computed(() => budgetOverview.value?.categories ?? []);
const budgetAlerts = computed(() =>
    budgetCategories.value.filter((category) => ['warning', 'exceeded', 'critical'].includes(category.alert_status)),
);
const budgetMonthLabel = computed(() => getMonthLabel(selectedBudgetMonth.value));

const pieData = computed(() => ({
    labels: pie.value.map((item) => item.label),
    datasets: [
        {
            data: pie.value.map((item) => item.amount),
            backgroundColor: pie.value.map((_, index) => pieColors[index % pieColors.length]),
            borderColor: '#ffffff',
            borderWidth: 2,
        },
    ],
}));

const pieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: (context) => formatCurrency(context.raw ?? 0),
            },
        },
    },
};

function getMonthName(month) {
    return monthFormatter.value.format(new Date(2024, month - 1, 1));
}

function getMonthLabel(month) {
    return monthLabelFormatter.value.format(new Date(selectedYear.value ?? new Date().getFullYear(), month - 1, 1));
}

function authHeaders(extra = {}) {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
        ...extra,
    };
}

function getBudgetStatusClasses(status) {
    switch (status) {
    case 'warning':
        return 'border-amber-200 bg-amber-50 text-amber-700';
    case 'exceeded':
        return 'border-red-200 bg-red-50 text-red-700';
    case 'critical':
        return 'border-rose-300 bg-rose-100 text-rose-800';
    case 'ok':
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    default:
        return 'border-gray-200 bg-gray-50 text-gray-600';
    }
}

function getBudgetUsageLabel(category) {
    if (category.usage_ratio === null || category.usage_ratio === undefined) {
        return '—';
    }

    return `${(category.usage_ratio * 100).toFixed(1)}%`;
}

function syncBudgetInputs(categories) {
    const nextInputs = {};

    categories.forEach((category) => {
        nextInputs[category.transaction_category_id] = category.budget_amount === null
            ? ''
            : Number(category.budget_amount).toFixed(2);
    });

    budgetInputs.value = nextInputs;
}

function isExpanded(categoryKey) {
    return expandedCategories.value.includes(categoryKey);
}

function toggleExpand(categoryKey) {
    if (isExpanded(categoryKey)) {
        expandedCategories.value = expandedCategories.value.filter((key) => key !== categoryKey);
        return;
    }

    expandedCategories.value = [...expandedCategories.value, categoryKey];
}

function getEntry(key) {
    return pivot.value.pivot?.[key] ?? {};
}

function getNodeMonthValue(node, month) {
    if (!node.has_children) {
        return Number(getEntry(node.key)[month] ?? 0);
    }

    return node.children.reduce((sum, child) => sum + Number(getEntry(child.key)[month] ?? 0), 0);
}

function getNodeTotal(node) {
    if (!node.has_children) {
        return Number(getEntry(node.key).total ?? 0);
    }

    return node.children.reduce((sum, child) => sum + Number(getEntry(child.key).total ?? 0), 0);
}

function getChildMonthValue(childKey, month) {
    return Number(getEntry(childKey)[month] ?? 0);
}

function getChildTotal(childKey) {
    return Number(getEntry(childKey).total ?? 0);
}

async function loadReport() {
    loading.value = true;

    try {
        const params = new URLSearchParams();

        if (selectedYear.value) {
            params.set('year', selectedYear.value);
        }

        if (selectedNote.value) {
            params.set('note', selectedNote.value);
        }

        selectedTypes.value.forEach((typeId) => {
            params.append('types[]', typeId);
        });

        const response = await fetch(`/api/v1/reports/finance?${params.toString()}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to load finance report.');
        }

        const payload = await response.json();
        report.value = payload;

        if (!selectedYear.value) {
            selectedYear.value = payload.selected_year;
        }
    } catch (error) {
        addToast('Could not load the finance report. Please try again.', 'error');
    } finally {
        loading.value = false;
    }
}

async function openDetail(month, categoryKey, categoryLabel) {
    loadingDetails.value = true;
    detailState.value = {
        open: true,
        month,
        categoryKey,
        categoryLabel,
        transactions: [],
        total: 0,
    };

    try {
        const params = new URLSearchParams({
            year: String(selectedYear.value),
            month: String(month),
            category_key: categoryKey,
        });

        if (selectedNote.value) {
            params.set('note', selectedNote.value);
        }

        selectedTypes.value.forEach((typeId) => {
            params.append('types[]', typeId);
        });

        const response = await fetch(`/api/v1/reports/finance/details?${params.toString()}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to load detail transactions.');
        }

        const payload = await response.json();
        detailState.value = {
            ...detailState.value,
            transactions: payload.transactions ?? [],
            total: payload.total ?? 0,
        };
    } catch (error) {
        detailState.value.open = false;
        addToast('Could not load the report detail. Please try again.', 'error');
    } finally {
        loadingDetails.value = false;
    }
}

function closeDetail() {
    detailState.value = {
        open: false,
        month: null,
        categoryKey: null,
        categoryLabel: '',
        transactions: [],
        total: 0,
    };
}

async function loadBudgetOverview() {
    if (!auth.accessToken || !selectedYear.value) {
        budgetOverview.value = {
            selected_year: selectedYear.value ?? new Date().getFullYear(),
            selected_month: selectedBudgetMonth.value,
            period_start: null,
            categories: [],
        };
        budgetInputs.value = {};
        return;
    }

    budgetLoading.value = true;

    try {
        const params = new URLSearchParams({
            year: String(selectedYear.value),
            month: String(selectedBudgetMonth.value),
        });

        const response = await fetch(`/api/v1/budgets/monthly?${params.toString()}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to load monthly budgets.');
        }

        const payload = await response.json();
        budgetOverview.value = payload;
        syncBudgetInputs(payload.categories ?? []);
    } catch (error) {
        addToast('Could not load monthly budgets. Please try again.', 'error');
    } finally {
        budgetLoading.value = false;
    }
}

async function saveBudget(transactionCategoryId) {
    const value = budgetInputs.value[transactionCategoryId];

    if (!value || Number(value) <= 0) {
        addToast('Budget amount must be greater than zero.', 'error');
        return;
    }

    budgetSavingId.value = transactionCategoryId;

    try {
        const response = await fetch(`/api/v1/budgets/monthly/${transactionCategoryId}`, {
            method: 'PUT',
            headers: authHeaders({
                'Content-Type': 'application/json',
            }),
            body: JSON.stringify({
                year: selectedYear.value,
                month: selectedBudgetMonth.value,
                amount: Number(value),
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to save budget.');
        }

        addToast('Monthly budget saved.', 'success');
        await loadBudgetOverview();
    } catch (error) {
        addToast('Could not save the budget. Please try again.', 'error');
    } finally {
        budgetSavingId.value = null;
    }
}

async function clearBudget(transactionCategoryId) {
    budgetSavingId.value = transactionCategoryId;

    try {
        const params = new URLSearchParams({
            year: String(selectedYear.value),
            month: String(selectedBudgetMonth.value),
        });

        const response = await fetch(`/api/v1/budgets/monthly/${transactionCategoryId}?${params.toString()}`, {
            method: 'DELETE',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to clear budget.');
        }

        addToast('Monthly budget cleared.', 'success');
        await loadBudgetOverview();
    } catch (error) {
        addToast('Could not clear the budget. Please try again.', 'error');
    } finally {
        budgetSavingId.value = null;
    }
}

function getExportFilename(response, fallback) {
    const disposition = response.headers.get('content-disposition') ?? '';
    const match = disposition.match(/filename="?([^"]+)"?/i);

    return match?.[1] ?? fallback;
}

async function downloadExport(format) {
    if (!auth.accessToken || !selectedYear.value) {
        return;
    }

    exportingFormat.value = format;

    try {
        const params = new URLSearchParams({
            year: String(selectedYear.value),
            format,
        });

        if (selectedNote.value) {
            params.set('note', selectedNote.value);
        }

        selectedTypes.value.forEach((typeId) => {
            params.append('types[]', typeId);
        });

        const response = await fetch(`/api/v1/reports/finance/export?${params.toString()}`, {
            headers: authHeaders({
                Accept: '*/*',
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to export report.');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        const extension = format === 'xlsx' ? 'xlsx' : format;

        link.href = url;
        link.download = getExportFilename(response, `finance-report-${selectedYear.value}.${extension}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        addToast('Could not export the finance report. Please try again.', 'error');
    } finally {
        exportingFormat.value = null;
    }
}

watch([selectedYear, selectedNote], ([year]) => {
    if (!year) {
        return;
    }

    void loadReport();
    void loadBudgetOverview();
});

watch(selectedTypes, () => {
    if (!selectedYear.value) {
        return;
    }

    void loadReport();
}, { deep: true });

watch(selectedBudgetMonth, () => {
    if (!selectedYear.value) {
        return;
    }

    void loadBudgetOverview();
});

onMounted(async () => {
    await loadReport();
    await loadBudgetOverview();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Finance report</h1>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:text-gray-900"
                        :class="exportingFormat === 'csv' ? 'opacity-60' : ''"
                        :disabled="exportingFormat !== null"
                        @click="downloadExport('csv')"
                    >
                        CSV
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 shadow-sm transition hover:border-blue-300 hover:text-blue-900"
                        :class="exportingFormat === 'xlsx' ? 'opacity-60' : ''"
                        :disabled="exportingFormat !== null"
                        @click="downloadExport('xlsx')"
                    >
                        XLSX
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-purple-200 bg-purple-50 px-3 py-2 text-sm font-medium text-purple-700 shadow-sm transition hover:border-purple-300 hover:text-purple-900"
                        :class="exportingFormat === 'pdf' ? 'opacity-60' : ''"
                        :disabled="exportingFormat !== null"
                        @click="downloadExport('pdf')"
                    >
                        PDF
                    </button>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium text-gray-600">
                    <span>Year</span>
                    <span class="relative">
                        <select
                            v-model="selectedYear"
                            class="min-w-24 appearance-none rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-9 text-sm text-gray-700 shadow-sm outline-none focus:border-amber-300 focus:ring-2 focus:ring-amber-200"
                            style="background-image: none;"
                        >
                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-gray-400">▼</span>
                    </span>
                </label>

                <label class="flex items-center gap-2 text-sm font-medium text-gray-600">
                    <span>Notes</span>
                    <span class="relative">
                        <select
                            v-model="selectedNote"
                            class="min-w-48 appearance-none rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-9 text-sm text-gray-700 shadow-sm outline-none focus:border-amber-300 focus:ring-2 focus:ring-amber-200"
                            style="background-image: none;"
                        >
                            <option value="">All</option>
                            <option v-for="option in noteOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-gray-400">▼</span>
                    </span>
                </label>
            </div>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else>
            <div class="mb-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Monthly budgets</h2>
                        </div>

                        <label class="flex items-center gap-2 text-sm font-medium text-gray-600">
                            <span>Budget month</span>
                            <span class="relative">
                                <select
                                    v-model="selectedBudgetMonth"
                                    class="min-w-36 appearance-none rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-9 text-sm text-gray-700 shadow-sm outline-none focus:border-amber-300 focus:ring-2 focus:ring-amber-200"
                                    style="background-image: none;"
                                >
                                    <option v-for="month in months" :key="`budget-${month}`" :value="month">
                                        {{ getMonthLabel(month) }}
                                    </option>
                                </select>
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-gray-400">▼</span>
                            </span>
                        </label>
                    </div>

                    <LoadingSpinner v-if="budgetLoading" class="py-10" />

                    <div v-else class="mt-4 overflow-x-auto rounded-2xl border border-gray-200">
                        <table class="min-w-full border-collapse text-sm">
                            <thead class="bg-gray-50">
                                <tr class="border-b border-gray-200">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Category</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Budget</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Spent</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Usage</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="category in budgetCategories"
                                    :key="category.transaction_category_id"
                                    class="border-b border-gray-100"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-900">{{ translateCategoryName(category.name) }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ translateOptionalCategory(category.parent_name) }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="budgetInputs[category.transaction_category_id]"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-28 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 shadow-sm outline-none focus:border-amber-300 focus:ring-2 focus:ring-amber-200"
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-gray-700">
                                        {{ formatCurrency(Number(category.spent_amount ?? 0)) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-700">
                                        {{ getBudgetUsageLabel(category) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize"
                                            :class="getBudgetStatusClasses(category.alert_status)"
                                        >
                                            {{ category.alert_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-gray-700"
                                                :class="budgetSavingId === category.transaction_category_id ? 'opacity-60' : ''"
                                                :disabled="budgetSavingId !== null"
                                                @click="saveBudget(category.transaction_category_id)"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-gray-300 hover:text-gray-900"
                                                :class="budgetSavingId === category.transaction_category_id ? 'opacity-60' : ''"
                                                :disabled="budgetSavingId !== null"
                                                @click="clearBudget(category.transaction_category_id)"
                                            >
                                                Clear
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <BudgetAlertPanel
                    :alerts="budgetAlerts"
                    title="Budget alerts"
                    :month-label="budgetMonthLabel"
                    empty-label="No warning, exceeded, or critical budget alerts for this month."
                />
            </div>

            <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap gap-2">
                    <label
                        v-for="option in typeOptions"
                        :key="option.value"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
                        :class="selectedTypes.includes(option.value)
                            ? 'border-amber-300 bg-amber-50 text-amber-900'
                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900'"
                    >
                        <input
                            v-model="selectedTypes"
                            type="checkbox"
                            :value="option.value"
                            class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-300"
                        >
                        {{ option.label }}
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div>
                        <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grand total {{ selectedYear }}</p>
                                <p
                                    class="mt-2 font-mono text-3xl font-semibold"
                                    :class="pivot.grandTotal >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                >
                                    {{ formatCurrency(pivot.grandTotal) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-gray-200 bg-white p-4">
                                <div
                                    v-for="month in months"
                                    :key="month"
                                    class="rounded-xl bg-gray-50 px-3 py-2 text-sm"
                                >
                                    <span class="text-gray-500">{{ getMonthName(month) }}:</span>
                                    <span
                                        class="ml-2 font-mono font-semibold"
                                        :class="Number(pivot.monthTotals?.[month] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                    >
                                        {{ formatCurrency(Number(pivot.monthTotals?.[month] ?? 0)) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                            <table class="min-w-full border-collapse text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="sticky left-0 z-20 min-w-56 bg-gray-50 px-4 py-3 text-left font-semibold text-gray-700 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.12)]">
                                            Category
                                        </th>
                                        <th
                                            v-for="month in months"
                                            :key="month"
                                            class="px-3 py-3 text-right font-medium text-gray-500"
                                        >
                                            {{ getMonthName(month) }}
                                        </th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="node in pivot.tree" :key="node.key">
                                        <tr class="border-b border-gray-100">
                                            <td class="sticky left-0 z-10 min-w-56 bg-white px-4 py-3 text-gray-900 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.12)]">
                                                <button
                                                    v-if="node.has_children"
                                                    type="button"
                                                    class="inline-flex rounded-md text-left font-medium text-gray-900 transition hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-200"
                                                    @click="toggleExpand(node.key)"
                                                >
                                                    {{ node.label }}
                                                </button>
                                                <span v-else>
                                                    {{ node.label }}
                                                </span>
                                            </td>
                                            <td
                                                v-for="month in months"
                                                :key="`${node.key}-${month}`"
                                                class="cursor-pointer px-3 py-3 text-right font-mono"
                                                :class="getNodeMonthValue(node, month) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                                @dblclick="openDetail(month, node.key, node.label)"
                                            >
                                                {{ getNodeMonthValue(node, month) === 0 ? '-' : formatCurrency(getNodeMonthValue(node, month)) }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-right font-mono font-semibold"
                                                :class="getNodeTotal(node) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                            >
                                                {{ formatCurrency(getNodeTotal(node)) }}
                                            </td>
                                        </tr>

                                        <tr
                                            v-for="child in (isExpanded(node.key) ? node.children : [])"
                                            :key="child.key"
                                            class="border-b border-gray-100 bg-gray-50/80"
                                        >
                                            <td class="sticky left-0 z-10 min-w-56 bg-gray-50 px-4 py-2 pl-10 text-sm text-gray-500 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.12)]">
                                                {{ child.label }}
                                            </td>
                                            <td
                                                v-for="month in months"
                                                :key="`${child.key}-${month}`"
                                                class="cursor-pointer px-3 py-2 text-right font-mono text-sm"
                                                :class="getChildMonthValue(child.key, month) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                                @dblclick="openDetail(month, child.key, `${node.label} › ${child.label}`)"
                                            >
                                                {{ getChildMonthValue(child.key, month) === 0 ? '-' : formatCurrency(getChildMonthValue(child.key, month)) }}
                                            </td>
                                            <td
                                                class="px-4 py-2 text-right font-mono text-sm font-semibold"
                                                :class="getChildTotal(child.key) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                            >
                                                {{ formatCurrency(getChildTotal(child.key)) }}
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t-2 border-gray-200">
                                        <td class="sticky left-0 z-20 min-w-56 bg-gray-50 px-4 py-3 font-semibold text-gray-700 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.12)]">
                                            Grand total
                                        </td>
                                        <td
                                            v-for="month in months"
                                            :key="`total-${month}`"
                                            class="px-3 py-3 text-right font-mono font-semibold"
                                            :class="Number(pivot.monthTotals?.[month] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                        >
                                            {{ Number(pivot.monthTotals?.[month] ?? 0) === 0 ? '-' : formatCurrency(Number(pivot.monthTotals?.[month] ?? 0)) }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-mono font-semibold"
                                            :class="pivot.grandTotal >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                        >
                                            {{ formatCurrency(pivot.grandTotal) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-gray-900">Distribution {{ selectedYear }}</h2>

                        <div v-if="pie.length" class="mt-4 h-72">
                            <Doughnut :data="pieData" :options="pieOptions" />
                        </div>
                        <p v-else class="mt-4 text-sm text-gray-500">No category totals match the current filters.</p>

                        <div v-if="pie.length" class="mt-6 space-y-2">
                            <div
                                v-for="(item, index) in pie"
                                :key="`${item.label}-${index}`"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: pieColors[index % pieColors.length] }" />
                                    <span class="truncate text-gray-700">{{ item.label }}</span>
                                </div>
                                <span class="font-mono text-gray-500">{{ formatCurrency(item.amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="table.length" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Monthly summary</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Month</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Earnings</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Expenses</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in table" :key="row.month_name" class="border-b border-gray-100">
                                <td class="px-4 py-3 text-gray-700">{{ row.month_name }}</td>
                                <td
                                    class="px-4 py-3 text-right font-mono"
                                    :class="Number(row.earnings ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                >
                                    {{ Number(row.earnings ?? 0) === 0 ? '-' : formatCurrency(Number(row.earnings ?? 0)) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-red-500">
                                    {{ Number(row.expenses ?? 0) === 0 ? '-' : formatCurrency(Number(row.expenses ?? 0)) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-semibold"
                                    :class="Number(row.net ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                >
                                    {{ Number(row.net ?? 0) === 0 ? '-' : formatCurrency(Number(row.net ?? 0)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <Teleport to="body">
            <div v-if="detailState.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <button type="button" class="absolute inset-0 bg-black/50" @click="closeDetail" />

                <div class="relative z-10 flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ detailState.categoryLabel }}</h2>
                            <p class="text-sm text-gray-500">{{ getMonthLabel(detailState.month) }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900"
                            @click="closeDetail"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="overflow-auto px-6 py-4">
                        <LoadingSpinner v-if="loadingDetails" class="py-12" />

                        <template v-else>
                            <p v-if="!detailState.transactions.length" class="text-sm text-gray-500">No transaction found.</p>

                            <table v-else class="min-w-full border-collapse text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Description</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Account</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-700">Amount</th>
                                        <th class="px-4 py-3 text-center font-semibold text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="transaction in detailState.transactions" :key="transaction.id" class="border-b border-gray-100">
                                        <td class="px-4 py-3 text-gray-700">{{ transaction.date }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ transaction.description || '-' }}</td>
                                        <td class="px-4 py-3 text-gray-700">{{ transaction.account_name || '-' }}</td>
                                        <td
                                            class="px-4 py-3 text-right font-mono"
                                            :class="transaction.amount >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                        >
                                            {{ formatCurrency(transaction.amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <router-link
                                                :to="`/transactions/${transaction.id}/edit`"
                                                class="text-sm font-medium text-amber-700 transition hover:text-amber-900"
                                                @click="closeDetail"
                                            >
                                                Edit
                                            </router-link>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr class="border-t-2 border-gray-200">
                                        <td colspan="3" class="px-4 py-3 font-semibold text-gray-700">Total</td>
                                        <td
                                            class="px-4 py-3 text-right font-mono font-semibold"
                                            :class="detailState.total >= 0 ? 'text-emerald-600' : 'text-red-500'"
                                        >
                                            {{ formatCurrency(detailState.total) }}
                                        </td>
                                        <td />
                                    </tr>
                                </tfoot>
                            </table>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
