<script setup>
import { computed } from 'vue';
import { ExclamationTriangleIcon, ScaleIcon } from '@heroicons/vue/24/outline';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useCashFlowForecastStore } from '@/stores/cashFlowForecast.js';
import { coverageMessages, currencyRows, hasEstimate, isIncomplete } from '@/utils/cashFlowForecast.js';

const { formatCurrency } = useCurrency();
const store = useCashFlowForecastStore();

const rows = computed(() => currencyRows(store.forecast));
const messages = computed(() => coverageMessages(store.forecast));
const estimateAvailable = computed(() => hasEstimate(store.forecast));
const incomplete = computed(() => isIncomplete(store.forecast));

function typeLabel(type) {
    return { loan: 'Loan', 'credit-card': 'Card payment', subscription: 'Subscription' }[type] ?? type;
}
</script>

<template>
    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" aria-labelledby="cash-flow-forecast-title">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="cash-flow-forecast-title" class="flex items-center gap-2 text-lg font-semibold text-gray-900">
                    <ScaleIcon class="h-5 w-5 text-gray-400" /> Next 30 days: known commitments
                </h2>
                <p v-if="store.forecast" class="mt-1 text-sm text-gray-500">
                    Estimate for {{ store.forecast.as_of }} to {{ store.forecast.through_date }}
                </p>
            </div>
            <span
                v-if="store.forecast && incomplete"
                class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"
            >
                Incomplete estimate
            </span>
        </div>

        <LoadingSpinner v-if="store.loading" class="py-10" />

        <div v-else-if="store.error" class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
            The forecast could not be loaded. The rest of the dashboard is not affected.
            <button type="button" class="ml-1 font-medium text-blue-600 hover:underline" @click="store.fetchForecast()">Try again</button>
        </div>

        <div v-else-if="!store.forecast || !estimateAvailable" class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
            No forecast available: there is no active account with a valid currency and balance outside a savings goal.
        </div>

        <template v-else>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <article
                    v-for="row in rows"
                    :key="row.currency"
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                    :data-currency="row.currency"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ row.currency }}</p>
                    <p class="mt-2 font-mono text-2xl font-semibold" :class="row.remaining < 0 ? 'text-red-600' : 'text-gray-900'">
                        {{ formatCurrency(row.remaining, row.currency) }}
                    </p>
                    <p class="text-xs text-gray-500">estimated left after known commitments</p>

                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Available balance</dt>
                            <dd class="font-mono text-gray-900">{{ formatCurrency(row.available, row.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Counted commitments</dt>
                            <dd class="font-mono text-gray-900">{{ formatCurrency(row.committed, row.currency) }}</dd>
                        </div>
                    </dl>
                    <p v-if="row.excludedAccounts" class="mt-2 text-xs text-gray-500">
                        {{ row.excludedAccounts }} account{{ row.excludedAccounts === 1 ? '' : 's' }} set aside for savings goals {{ row.excludedAccounts === 1 ? 'is' : 'are' }} not included.
                    </p>

                    <ul v-if="row.obligations.length" class="mt-3 divide-y divide-gray-200 border-t border-gray-200 text-sm">
                        <li v-for="item in row.obligations" :key="item.id" class="flex items-start justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900">{{ item.name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ item.due_date }} · {{ typeLabel(item.type) }}<template v-if="item.reasonLabel"> · {{ item.reasonLabel }}</template>
                                </p>
                            </div>
                            <p class="shrink-0 font-mono" :class="item.counted > 0 ? 'text-gray-900' : 'text-gray-400'">
                                {{ item.counted > 0 ? formatCurrency(item.counted, row.currency) : `(${formatCurrency(item.face, row.currency)})` }}
                            </p>
                        </li>
                    </ul>

                    <div v-if="row.overdue.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <p class="font-semibold">Overdue, not in the figure</p>
                        <ul class="mt-1 space-y-1">
                            <li v-for="item in row.overdue" :key="item.id" class="flex justify-between gap-3">
                                <span class="truncate">{{ item.name }} · {{ item.due_date }}</span>
                                <span class="font-mono">{{ formatCurrency(item.face, row.currency) }}</span>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
        </template>

        <div v-if="store.forecast && !store.loading && !store.error" class="mt-4 space-y-2 text-xs text-gray-500">
            <ul v-if="messages.length" class="space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
                <li v-for="message in messages" :key="message.code" class="flex gap-2">
                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ message.text }}<template v-if="message.count > 1"> ({{ message.count }})</template></span>
                </li>
            </ul>
            <p>
                Only loan installments, card payments and subscription renewals already tracked are counted. Future income and
                untracked spending are not included, and amounts in different currencies are never combined. This is not a guaranteed safe-to-spend amount.
            </p>
        </div>
    </section>
</template>
