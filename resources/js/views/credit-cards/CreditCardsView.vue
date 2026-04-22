<script setup>
import { computed, onMounted, ref } from 'vue';
import { CreditCardIcon } from '@heroicons/vue/24/outline';
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
const cardsResponse = ref({ data: [] });

const cards = computed(() => cardsResponse.value?.data ?? []);

async function fetchCards() {
    if (!auth.accessToken) {
        cardsResponse.value = { data: [] };
        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/api/v1/credit-cards?per_page=100', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            cardsResponse.value = { data: [] };
            return;
        }

        const data = await response.json();
        cardsResponse.value = {
            data: data.data ?? [],
        };
    } finally {
        loading.value = false;
    }
}

function availablePct(card) {
    if (!card.credit_limit) return 100;
    return Math.round(((card.available_credit ?? 0) / card.credit_limit) * 100);
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-emerald-500/10 text-emerald-400',
        suspended: 'bg-amber-100 text-amber-700',
        closed: 'bg-gray-500/10 text-gray-500',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

onMounted(() => {
    void fetchCards();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Credit Cards</h1>
                <p class="mt-1 text-sm text-gray-500">Track your credit card spending cycles</p>
            </div>
            <router-link
                to="/credit-cards/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add card
            </router-link>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <EmptyState
            v-else-if="!cards.length"
            title="No cards added"
            message="Add a credit card to track your spending cycles."
            :icon="CreditCardIcon"
            action-label="Add card"
            action-to="/credit-cards/new"
        />

        <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="card in cards"
                    :key="card.id"
                    class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition-colors duration-150 hover:border-gray-300"
                    @click="router.push(`/credit-cards/${card.id}`)"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-normal text-gray-900">{{ card.name }}</h3>
                            <p class="mt-0.5 text-sm capitalize text-gray-500">{{ card.type }}</p>
                        </div>
                        <span :class="statusBadgeClass(card.status)" class="rounded px-2 py-0.5 text-sm capitalize">
                            {{ card.status }}
                        </span>
                    </div>

                    <div class="mb-1 h-2 w-full rounded-full bg-gray-100">
                        <div
                            class="h-2 rounded-full bg-purple-500 transition-all duration-300"
                            :style="{ width: `${availablePct(card)}%` }"
                        />
                    </div>

                    <div class="mb-3 flex justify-between text-sm text-gray-500">
                        <span>{{ formatCurrency(card.available_credit ?? 0) }} available</span>
                        <span>{{ formatCurrency(card.credit_limit ?? 0) }} limit</span>
                    </div>

                    <p class="font-mono text-xl font-semibold text-purple-400">{{ formatCurrency(card.current_balance) }} balance</p>
                    <p class="mt-1 text-sm text-gray-500">Statement day {{ card.statement_day }} · Due day {{ card.due_day }}</p>
                </div>
            </div>

        </template>
    </AppLayout>
</template>
