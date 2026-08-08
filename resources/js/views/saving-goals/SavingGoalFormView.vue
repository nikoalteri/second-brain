<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const { addToast } = useToast();
const auth = useAuthStore();

const isEdit = computed(() => !!route.params.id);
const showDeleteModal = ref(false);
const loadingGoal = ref(false);
const deleting = ref(false);
const saving = ref(false);
const errors = ref({});
const accounts = ref([]);

const form = ref({
    name: '',
    account_id: '',
    target_amount: '',
    target_date: '',
    status: 'active',
    notes: '',
});

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
];

const accountOptions = computed(() =>
    accounts.value.map((account) => ({ value: account.id, label: account.name })),
);

async function fetchAccounts() {
    try {
        const response = await fetch('/api/v1/accounts?per_page=100', {
            headers: authHeaders(),
        });

        if (!response.ok) return;

        const data = await response.json();
        accounts.value = data.data ?? [];
    } catch {
        // Leave the picker empty; handleSubmit's own validation will catch a missing account.
    }
}

function authHeaders(includeJson = false) {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
    };
}

async function fetchGoal() {
    if (!isEdit.value) return;

    loadingGoal.value = true;

    try {
        const response = await fetch(`/api/v1/saving-goals/${route.params.id}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to load saving goal');
        }

        const { data } = await response.json();
        form.value = {
            name: data.name,
            account_id: data.account_id,
            target_amount: String(data.target_amount),
            target_date: data.target_date ?? '',
            status: data.status,
            notes: data.notes ?? '',
        };
    } catch {
        addToast('Could not load this saving goal.', 'error');
    } finally {
        loadingGoal.value = false;
    }
}

async function handleSubmit() {
    errors.value = {};

    if (!form.value.name) {
        errors.value.name = 'Name is required';
        return;
    }

    if (!form.value.account_id) {
        errors.value.account_id = 'Choose an account';
        return;
    }

    if (!form.value.target_amount || Number(form.value.target_amount) <= 0) {
        errors.value.target_amount = 'Enter a target amount greater than zero';
        return;
    }

    saving.value = true;

    try {
        const payload = {
            name: form.value.name,
            account_id: form.value.account_id,
            target_amount: Number(form.value.target_amount),
            target_date: form.value.target_date || null,
            status: form.value.status,
            notes: form.value.notes || null,
        };

        const response = await fetch(
            isEdit.value ? `/api/v1/saving-goals/${route.params.id}` : '/api/v1/saving-goals',
            {
                method: isEdit.value ? 'PUT' : 'POST',
                headers: authHeaders(true),
                body: JSON.stringify(payload),
            },
        );

        if (response.status === 422) {
            const data = await response.json();
            errors.value = Object.fromEntries(
                Object.entries(data.errors ?? {}).map(([key, value]) => [key, value?.[0] ?? 'Invalid value']),
            );
            return;
        }

        if (!response.ok) {
            throw new Error('Failed to save saving goal');
        }

        addToast(isEdit.value ? 'Saving goal updated.' : 'Saving goal created.', 'success');
        router.push('/saving-goals');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    deleting.value = true;

    try {
        const response = await fetch(`/api/v1/saving-goals/${route.params.id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to delete saving goal');
        }

        addToast('Saving goal deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/saving-goals');
    } catch {
        addToast('Could not delete this saving goal. Please try again.', 'error');
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    void fetchAccounts();
    void fetchGoal();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Saving Goal' : 'Add Saving Goal' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingGoal" class="py-16" />

        <form v-else class="max-w-2xl" @submit.prevent="handleSubmit">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInput
                            v-model="form.name"
                            label="Name"
                            placeholder="e.g., Emergency fund"
                            required
                            :error="errors.name"
                            class="md:col-span-2"
                        />
                        <FormSelect
                            v-model="form.account_id"
                            label="Account"
                            placeholder="Choose an account"
                            :options="accountOptions"
                            :error="errors.account_id"
                            class="md:col-span-2"
                        />
                        <FormInput
                            v-model="form.target_amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            label="Target amount"
                            required
                            :error="errors.target_amount"
                        />
                        <FormInput
                            v-model="form.target_date"
                            type="date"
                            label="Target date (optional)"
                            :error="errors.target_date"
                        />
                        <FormSelect
                            v-model="form.status"
                            label="Status"
                            :options="statusOptions"
                            :error="errors.status"
                        />
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium text-gray-700">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        />
                    </div>
                </section>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                    @click="showDeleteModal = true"
                >
                    Delete
                </button>
                <div v-else />

                <div class="flex gap-3">
                    <router-link
                        to="/saving-goals"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="saving"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete saving goal?"
            message="This also deletes all its contributions. This cannot be undone."
            confirm-label="Delete"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
