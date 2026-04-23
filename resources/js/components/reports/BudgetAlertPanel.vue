<script setup>
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency.js';

const props = defineProps({
    alerts: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: 'Budget alerts',
    },
    description: {
        type: String,
        default: 'Current month warning, exceeded, and critical categories.',
    },
    emptyLabel: {
        type: String,
        default: 'No current budget alerts.',
    },
    monthLabel: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const { formatCurrency } = useCurrency();

const hasAlerts = computed(() => props.alerts.length > 0);

function statusClasses(status) {
    switch (status) {
    case 'warning':
        return 'border-amber-200 bg-amber-50 text-amber-700';
    case 'exceeded':
        return 'border-red-200 bg-red-50 text-red-700';
    case 'critical':
        return 'border-rose-300 bg-rose-100 text-rose-800';
    default:
        return 'border-gray-200 bg-gray-50 text-gray-600';
    }
}

function usageLabel(alert) {
    if (alert.usage_ratio === null || alert.usage_ratio === undefined) {
        return 'No budget';
    }

    return `${Math.round(alert.usage_ratio * 100)}% used`;
}
</script>

<template>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ description }}</p>
            </div>
            <span
                v-if="monthLabel"
                class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600"
            >
                {{ monthLabel }}
            </span>
        </div>

        <div v-if="hasAlerts" class="mt-4 space-y-3">
            <article
                v-for="alert in alerts"
                :key="alert.transaction_category_id"
                class="rounded-xl border p-4"
                :class="statusClasses(alert.alert_status)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ alert.name }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ alert.parent_name ?? 'Uncategorized' }}
                        </p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-xs font-semibold capitalize">
                        {{ alert.alert_status }}
                    </span>
                </div>

                <div
                    class="mt-3 grid gap-2 text-sm text-gray-600"
                    :class="compact ? 'sm:grid-cols-2' : 'sm:grid-cols-3'"
                >
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Spent</p>
                        <p class="mt-1 font-mono text-sm font-medium text-gray-900">
                            {{ formatCurrency(alert.spent_amount ?? 0) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Budget</p>
                        <p class="mt-1 font-mono text-sm font-medium text-gray-900">
                            {{ alert.budget_amount === null ? '—' : formatCurrency(alert.budget_amount) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Usage</p>
                        <p class="mt-1 text-sm font-medium text-gray-900">
                            {{ usageLabel(alert) }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500"
        >
            {{ emptyLabel }}
        </div>
    </section>
</template>
