<script setup>
import { computed, onMounted, ref } from 'vue';
import { PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const { addToast } = useToast();
const { formatCurrency } = useCurrency();
const auth = useAuthStore();

const loading = ref(false);
const goal = ref(null);
const showContributionForm = ref(false);
const submittingContribution = ref(false);
const contributionToDelete = ref(null);
const deletingContributionId = ref(null);
const contributionForm = ref({ amount: '', date: new Date().toISOString().slice(0, 10), notes: '' });

const contributions = computed(() => goal.value?.contributions ?? []);

function authHeaders(includeJson = false) {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
    };
}

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

function openContributionForm() {
    contributionForm.value = { amount: '', date: new Date().toISOString().slice(0, 10), notes: '' };
    showContributionForm.value = true;
}

async function submitContribution() {
    const amount = Number(contributionForm.value.amount);

    if (Number.isNaN(amount) || amount === 0) {
        addToast('Enter a non-zero amount.', 'error');
        return;
    }

    submittingContribution.value = true;

    try {
        const response = await fetch(`/api/v1/saving-goals/${route.params.id}/contributions`, {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({
                amount,
                date: contributionForm.value.date,
                notes: contributionForm.value.notes || undefined,
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to add contribution');
        }

        await fetchGoal();
        showContributionForm.value = false;
        addToast('Contribution added.', 'success');
    } catch {
        addToast('Could not add the contribution. Please try again.', 'error');
    } finally {
        submittingContribution.value = false;
    }
}

async function deleteContribution() {
    if (!contributionToDelete.value) return;

    deletingContributionId.value = contributionToDelete.value.id;

    try {
        const response = await fetch(
            `/api/v1/saving-goals/${route.params.id}/contributions/${contributionToDelete.value.id}`,
            { method: 'DELETE', headers: authHeaders() },
        );

        if (!response.ok) {
            throw new Error('Failed to delete contribution');
        }

        await fetchGoal();
        contributionToDelete.value = null;
        addToast('Contribution removed.', 'success');
    } catch {
        addToast('Could not remove the contribution. Please try again.', 'error');
    } finally {
        deletingContributionId.value = null;
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
                    <span :class="statusBadgeClass(goal.status)" class="mt-1 inline-block rounded px-2 py-0.5 text-sm capitalize">
                        {{ goal.status }}
                    </span>
                </div>
                <router-link
                    :to="`/saving-goals/${goal.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="mb-8 rounded-xl border border-gray-200 bg-white p-6">
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
                <p v-if="goal.target_date" class="mt-3 text-sm text-gray-500">Target date: {{ goal.target_date }}</p>
                <p v-if="goal.notes" class="mt-3 text-sm text-gray-600">{{ goal.notes }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Contributions</h2>
                    <button
                        type="button"
                        class="flex h-9 items-center rounded-lg bg-amber-500 px-3 text-sm font-medium text-white transition-colors hover:bg-amber-600"
                        @click="openContributionForm"
                    >
                        Add contribution
                    </button>
                </div>

                <section v-if="showContributionForm" class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Amount</span>
                            <input
                                v-model="contributionForm.amount"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            />
                            <span class="text-sm text-gray-500">Negative to withdraw</span>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Date</span>
                            <input
                                v-model="contributionForm.date"
                                type="date"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            />
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Notes (optional)</span>
                            <input
                                v-model="contributionForm.notes"
                                type="text"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            />
                        </label>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                            :disabled="submittingContribution"
                            @click="submitContribution"
                        >
                            {{ submittingContribution ? 'Adding…' : 'Add' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            @click="showContributionForm = false"
                        >
                            Cancel
                        </button>
                    </div>
                </section>

                <div v-if="!contributions.length" class="py-6 text-center text-sm text-gray-500">
                    No contributions yet.
                </div>

                <table v-else class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="pb-3 pr-4 text-left font-medium text-gray-500">Date</th>
                            <th class="pb-3 pr-4 text-right font-medium text-gray-500">Amount</th>
                            <th class="pb-3 pr-4 text-left font-medium text-gray-500">Notes</th>
                            <th class="pb-3 text-right font-medium text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="contribution in contributions" :key="contribution.id">
                            <td class="py-3 pr-4 text-gray-900">{{ contribution.date }}</td>
                            <td
                                class="py-3 pr-4 text-right font-mono"
                                :class="contribution.amount >= 0 ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ formatCurrency(contribution.amount) }}
                            </td>
                            <td class="py-3 pr-4 text-gray-500">{{ contribution.notes ?? '—' }}</td>
                            <td class="py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                                    @click="contributionToDelete = contribution"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <ConfirmModal
            :open="!!contributionToDelete"
            title="Delete contribution?"
            message="This cannot be undone."
            confirm-label="Delete"
            :loading="!!deletingContributionId"
            @confirm="deleteContribution"
            @cancel="contributionToDelete = null"
        />
    </AppLayout>
</template>
