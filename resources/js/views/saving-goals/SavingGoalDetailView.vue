<script setup>
import { onMounted, ref } from 'vue';
import { PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const { formatCurrency } = useCurrency();
const auth = useAuthStore();

const loading = ref(false);
const goal = ref(null);

function authHeaders() {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
    };
}

function statusBadgeClass(status) {
    const map = {
        active: 'bg-blue-500/10 text-blue-400',
        archived: 'bg-gray-500/10 text-gray-500',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

function progressBarClass(percent) {
    if (percent >= 100) return 'bg-emerald-500';
    if (percent >= 50) return 'bg-amber-500';
    return 'bg-blue-400';
}

async function fetchGoal() {
    loading.value = true;

    try {
        const response = await fetch(`/api/v1/saving-goals/${route.params.id}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            goal.value = null;
            return;
        }

        const { data } = await response.json();
        goal.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void fetchGoal();
});
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="goal">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ goal.name }}</h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span :class="statusBadgeClass(goal.status)" class="inline-block rounded px-2 py-0.5 text-sm capitalize">
                            {{ goal.status }}
                        </span>
                        <span v-if="goal.is_achieved" class="inline-block rounded bg-emerald-500/10 px-2 py-0.5 text-sm text-emerald-600">
                            Achieved
                        </span>
                    </div>
                </div>
                <router-link
                    :to="`/saving-goals/${goal.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <p class="font-mono text-2xl font-semibold text-gray-900">
                    {{ formatCurrency(goal.current_amount) }}
                    <span class="text-base font-normal text-gray-500">/ {{ formatCurrency(goal.target_amount) }}</span>
                </p>
                <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="progressBarClass(goal.progress_percent)"
                        :style="{ width: `${Math.min(100, goal.progress_percent)}%` }"
                    />
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ goal.progress_percent }}% funded</p>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <p class="text-sm text-gray-500">Tracking account</p>
                    <router-link
                        v-if="goal.account"
                        :to="`/accounts/${goal.account.id}`"
                        class="font-medium text-amber-600 hover:underline"
                    >
                        {{ goal.account.name }}
                    </router-link>
                </div>

                <p v-if="goal.target_date" class="mt-3 text-sm text-gray-500">Target date: {{ goal.target_date }}</p>
                <p v-if="goal.notes" class="mt-3 text-sm text-gray-600">{{ goal.notes }}</p>
            </div>

            <p class="mt-4 text-sm text-gray-500">
                Progress tracks the account's real balance — deposit or withdraw from
                <router-link v-if="goal.account" :to="`/accounts/${goal.account.id}`" class="text-amber-600 hover:underline">
                    {{ goal.account.name }}
                </router-link>
                to move it.
            </p>
        </template>
    </AppLayout>
</template>
