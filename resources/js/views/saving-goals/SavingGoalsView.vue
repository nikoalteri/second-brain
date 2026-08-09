<script setup>
import { onMounted, ref } from 'vue';
import { FlagIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { savingGoalStatusIcons } from '@/icons/domainIcons.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const { formatCurrency } = useCurrency();
const goals = ref([]);
const loading = ref(false);

function statusBadgeClass(status) {
    const map = {
        active: 'bg-blue-500/10 text-blue-400',
        achieved: 'bg-emerald-500/10 text-emerald-400',
        archived: 'bg-gray-500/10 text-gray-500',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

function progressBarClass(percent) {
    if (percent >= 100) return 'bg-emerald-500';
    if (percent >= 50) return 'bg-amber-500';
    return 'bg-blue-400';
}

async function fetchGoals() {
    if (!auth.accessToken) {
        goals.value = [];
        return;
    }

    loading.value = true;

    try {
        const response = await fetch('/api/v1/saving-goals?per_page=100', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            goals.value = [];
            return;
        }

        const data = await response.json();
        goals.value = data.data ?? [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void fetchGoals();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Saving Goals</h1>
            </div>
            <router-link
                to="/saving-goals/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white hover:bg-amber-600"
            >
                Add goal
            </router-link>
        </div>

        <LoadingSpinner v-if="loading" class="py-16" />

        <EmptyState
            v-else-if="!goals.length"
            title="No saving goals yet"
            message="Set a target and start tracking your progress toward it."
            :icon="FlagIcon"
            action-label="Add goal"
            action-to="/saving-goals/new"
        />

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="goal in goals"
                :key="goal.id"
                class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition-colors duration-150 hover:border-gray-300"
                @click="router.push(`/saving-goals/${goal.id}`)"
            >
                <div class="mb-3 flex items-start justify-between">
                    <h3 class="truncate text-base font-normal text-gray-900">{{ goal.name }}</h3>
                    <span :class="statusBadgeClass(goal.status)" class="inline-flex shrink-0 items-center gap-1 rounded px-2 py-0.5 text-sm capitalize">
                        <component :is="savingGoalStatusIcons[goal.status]" class="h-3.5 w-3.5" />
                        {{ goal.status }}
                    </span>
                </div>

                <p class="mb-1 font-mono text-lg font-semibold text-gray-900">
                    {{ formatCurrency(goal.current_amount) }}
                    <span class="text-sm font-normal text-gray-500">/ {{ formatCurrency(goal.target_amount) }}</span>
                </p>

                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="progressBarClass(goal.progress_percent)"
                        :style="{ width: `${Math.min(100, goal.progress_percent)}%` }"
                    />
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ goal.progress_percent }}% funded</p>

                <p v-if="goal.target_date" class="mt-2 text-sm text-gray-500">Target: {{ goal.target_date }}</p>
            </div>
        </div>
    </AppLayout>
</template>
