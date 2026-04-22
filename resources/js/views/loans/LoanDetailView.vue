<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const { formatCurrency } = useCurrency();
const { addToast } = useToast();
const auth = useAuthStore();
const loading = ref(false);
const generatingSchedule = ref(false);
const loan = ref(null);
const payments = computed(() => loan.value?.payments ?? []);

async function fetchLoan() {
    if (!auth.accessToken) {
        loan.value = null;
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/api/v1/loans/${route.params.id}`, {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            loan.value = null;
            return;
        }

        const data = await response.json();
        loan.value = data.data ?? null;
    } finally {
        loading.value = false;
    }
}

async function handleGenerateSchedule() {
    generatingSchedule.value = true;

    try {
        const response = await fetch(`/api/v1/loans/${route.params.id}/generate-schedule`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to generate schedule.');
        }

        const data = await response.json();
        loan.value = data.data ?? loan.value;
        addToast('Payment schedule generated.', 'success');
    } catch {
        addToast('Could not generate the payment schedule. Please try again.', 'error');
    } finally {
        generatingSchedule.value = false;
    }
}

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

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

watch(() => route.params.id, () => {
    void fetchLoan();
});

onMounted(() => {
    void fetchLoan();
});
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="loan">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ loan.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ loan.status }} · {{ loan.interest_rate }}% interest</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex h-10 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="generatingSchedule"
                        @click="handleGenerateSchedule"
                    >
                        {{ generatingSchedule ? 'Generating…' : 'Generate schedule' }}
                    </button>
                    <router-link
                        :to="`/loans/${loan.id}/edit`"
                        class="flex h-10 items-center gap-2 rounded-lg border border-gray-600 bg-gray-100 px-4 text-sm text-gray-900 transition-colors hover:bg-gray-50"
                    >
                        <PencilIcon class="h-4 w-4" />
                        Edit
                    </router-link>
                </div>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 grid grid-cols-2 gap-6 md:grid-cols-4">
                    <div>
                        <p class="text-sm text-gray-500">Total Amount</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-amber-400">{{ formatCurrency(loan.total_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Remaining</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-amber-400">{{ formatCurrency(loan.remaining_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Monthly Payment</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-gray-900">{{ formatCurrency(loan.monthly_payment) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Progress</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ progressPct(loan) }}%</p>
                    </div>
                </div>

                <div class="mb-1 h-2 w-full rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-amber-500" :style="{ width: `${progressPct(loan)}%` }" />
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>{{ loan.paid_installments }} paid</span>
                    <span>{{ loan.total_installments }} total</span>
                </div>
            </div>

            <h2 class="mb-4 text-xl font-semibold text-gray-900">Payment Schedule</h2>

            <p v-if="!payments.length" class="mb-4 text-sm text-gray-500">No payment schedule yet. Generate it to see the installments here.</p>

            <div v-else class="hidden overflow-x-auto sm:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">#</th>
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">Due Date</th>
                            <th class="pb-3 pr-4 text-left text-sm font-normal uppercase tracking-wide text-gray-500">Paid Date</th>
                            <th class="pb-3 pr-4 text-right text-sm font-normal uppercase tracking-wide text-gray-500">Amount</th>
                            <th class="pb-3 text-left text-sm font-normal uppercase tracking-wide text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <tr v-for="(payment, index) in payments" :key="payment.id" class="transition-colors hover:bg-gray-100/40">
                            <td class="py-3 pr-4 text-sm text-gray-500">{{ index + 1 }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-900">{{ payment.due_date ?? '—' }}</td>
                            <td class="py-3 pr-4 text-sm text-gray-500">{{ payment.actual_date ?? '—' }}</td>
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

            <div v-if="payments.length" class="flex flex-col gap-2 sm:hidden">
                <div
                    v-for="(payment, index) in payments"
                    :key="payment.id"
                    class="rounded-lg border border-gray-200 bg-white p-4"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Installment #{{ index + 1 }}</p>
                            <p class="mt-0.5 text-sm text-gray-900">Due: {{ payment.due_date ?? '—' }}</p>
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
