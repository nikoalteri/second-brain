<script setup>
import { onMounted, ref } from 'vue';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const { formatCurrency } = useCurrency();
const auth = useAuthStore();
const loading = ref(false);
const loans = ref([]);

async function fetchLoans() {
    if (!auth.accessToken) {
        loans.value = [];
        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/api/v1/loans?per_page=100', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            loans.value = [];
            return;
        }

        const data = await response.json();
        loans.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

function progressPct(loan) {
    if (!loan.total_installments) return 0;
    return Math.round((loan.paid_installments / loan.total_installments) * 100);
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-emerald-500/10 text-emerald-400',
        completed: 'bg-slate-200 text-slate-700',
        paid: 'bg-slate-200 text-slate-700',
        defaulted: 'bg-red-500/10 text-red-400',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

onMounted(() => {
    void fetchLoans();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Loans</h1>
            </div>
            <router-link
                to="/loans/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add loan
            </router-link>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <EmptyState
            v-else-if="!loans.length"
            title="No loans tracked"
            message="Add a loan to monitor repayment progress."
            :icon="DocumentTextIcon"
            action-label="Add loan"
            action-to="/loans/new"
        />

        <template v-else>
            <div class="flex flex-col gap-4">
                <div
                    v-for="loan in loans"
                    :key="loan.id"
                    class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition-colors duration-150 hover:border-gray-300"
                    @click="router.push(`/loans/${loan.id}`)"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-normal text-gray-900">{{ loan.name }}</h3>
                            <p class="mt-0.5 text-sm text-gray-500">
                                {{ formatCurrency(loan.monthly_payment) }}/mo · ends {{ loan.end_date ?? 'N/A' }}
                            </p>
                        </div>
                        <span :class="statusBadgeClass(loan.status)" class="rounded px-2 py-0.5 text-sm capitalize">
                            {{ loan.status }}
                        </span>
                    </div>

                    <div class="mb-1 h-2 w-full rounded-full bg-gray-100">
                        <div
                            class="h-2 rounded-full bg-amber-500 transition-all duration-300"
                            :style="{ width: `${progressPct(loan)}%` }"
                        />
                    </div>

                    <div class="mb-3 flex justify-between text-sm text-gray-500">
                        <span>{{ loan.paid_installments }} paid</span>
                        <span>{{ loan.total_installments }} total</span>
                    </div>

                    <p class="font-mono text-xl font-semibold text-amber-400">
                        {{ formatCurrency(loan.remaining_amount) }} remaining
                    </p>
                </div>
            </div>

        </template>
    </AppLayout>
</template>
