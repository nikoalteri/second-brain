<script setup>
import { computed, ref, watch } from 'vue';
import { useMutation, useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToast } from '@/composables/useToast.js';

const route = useRoute();
const router = useRouter();
const { addToast } = useToast();

const isEdit = computed(() => !!route.params.id);
const showDeleteModal = ref(false);
const defaultRenewal = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

const form = ref({
    account_id: '',
    category_id: '',
    name: '',
    monthly_cost: '',
    annual_cost: '',
    frequency: 'monthly',
    day_of_month: 1,
    next_renewal_date: defaultRenewal,
    auto_create_transaction: false,
    status: 'active',
    notes: '',
});
const errors = ref({});

const ACCOUNTS_QUERY = gql`
    query GetAccounts {
        accounts(first: 100) {
            data {
                id
                name
            }
        }
    }
`;

const CATEGORIES_QUERY = gql`
    query GetTransactionCategories {
        transactionCategories {
            id
            name
        }
    }
`;

const SUB_QUERY = gql`
    query GetSubscription($id: ID!) {
        subscription(id: $id) {
            id
            account_id
            category_id
            name
            monthly_cost
            annual_cost
            frequency
            day_of_month
            next_renewal_date
            auto_create_transaction
            status
            notes
        }
    }
`;

const CREATE_SUB = gql`
    mutation CreateSubscription($input: CreateSubscriptionInput!) {
        createSubscription(input: $input) {
            id
        }
    }
`;

const UPDATE_SUB = gql`
    mutation UpdateSubscription($id: ID!, $input: UpdateSubscriptionInput!) {
        updateSubscription(id: $id, input: $input) {
            id
        }
    }
`;

const DELETE_SUB = gql`
    mutation DeleteSubscription($id: ID!) {
        deleteSubscription(id: $id) {
            id
        }
    }
`;

const { result: accountsResult } = useQuery(ACCOUNTS_QUERY);
const { result: categoriesResult } = useQuery(CATEGORIES_QUERY);
const { result: subResult, loading: loadingSub } = useQuery(
    SUB_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).map((account) => ({ value: account.id, label: account.name }))
);
const categoryOptions = computed(() => [
    { value: '', label: 'No category' },
    ...(categoriesResult.value?.transactionCategories ?? []).map((category) => ({ value: category.id, label: category.name })),
]);
const frequencyOptions = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'annual', label: 'Annual' },
    { value: 'biennial', label: 'Every 2 Years' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'cancelled', label: 'Cancelled' },
];

watch(
    subResult,
    (value) => {
        if (value?.subscription) {
            const subscription = value.subscription;
            form.value = {
                account_id: subscription.account_id ?? '',
                category_id: subscription.category_id ?? '',
                name: subscription.name,
                monthly_cost: subscription.monthly_cost,
                annual_cost: subscription.annual_cost,
                frequency: subscription.frequency,
                day_of_month: subscription.day_of_month,
                next_renewal_date: subscription.next_renewal_date ?? defaultRenewal,
                auto_create_transaction: subscription.auto_create_transaction ?? false,
                status: subscription.status,
                notes: subscription.notes ?? '',
            };
        }
    },
    { immediate: true }
);

const { mutate: createSub, loading: creating } = useMutation(CREATE_SUB);
const { mutate: updateSub, loading: updating } = useMutation(UPDATE_SUB);
const { mutate: deleteSub, loading: deleting } = useMutation(DELETE_SUB);
const saving = computed(() => creating.value || updating.value);

function parseOptionalFloat(value) {
    return value === '' || value === null ? null : parseFloat(value);
}

async function handleSubmit() {
    errors.value = {};

    if (!form.value.name) {
        errors.value.name = 'Name is required';
        return;
    }

    if (!form.value.account_id) {
        errors.value.account_id = 'Account is required';
        return;
    }

    try {
        const input = {
            account_id: form.value.account_id,
            category_id: form.value.category_id || null,
            name: form.value.name,
            monthly_cost: parseOptionalFloat(form.value.monthly_cost),
            annual_cost: parseOptionalFloat(form.value.annual_cost),
            frequency: form.value.frequency,
            day_of_month: parseInt(form.value.day_of_month, 10),
            next_renewal_date: form.value.next_renewal_date,
            auto_create_transaction: form.value.auto_create_transaction,
            status: form.value.status,
            notes: form.value.notes || null,
        };

        if (isEdit.value) {
            await updateSub({
                id: route.params.id,
                input,
            });
            addToast('Subscription updated successfully.', 'success');
        } else {
            await createSub({ input });
            addToast('Subscription saved successfully.', 'success');
        }

        router.push('/subscriptions');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    try {
        await deleteSub({ id: route.params.id });
        addToast('Subscription deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/subscriptions');
    } catch {
        addToast('Could not delete subscription. Please try again.', 'error');
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Subscription' : 'Add Subscription' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Use the same billing, account, category, and automation settings available in the Filament subscription form.</p>
        </div>

        <LoadingSpinner v-if="loadingSub" class="py-16" />

        <form v-else class="max-w-4xl" @submit.prevent="handleSubmit">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Subscription Info</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Name *"
                            v-model="form.name"
                            placeholder="e.g. Netflix, Spotify"
                            :error="errors.name"
                        />
                        <FormSelect label="Frequency *" v-model="form.frequency" :options="frequencyOptions" />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Cost</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Monthly cost"
                            v-model="form.monthly_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            helper="For monthly subscriptions."
                        />
                        <FormInput
                            label="Annual cost"
                            v-model="form.annual_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            helper="For annual or biennial subscriptions."
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Renewal</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Day of month *"
                            v-model="form.day_of_month"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            helper="Day of month for renewal."
                        />
                        <FormInput
                            label="Next renewal date *"
                            v-model="form.next_renewal_date"
                            type="date"
                            helper="Next scheduled renewal."
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Account &amp; Category</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormSelect
                            label="Account *"
                            v-model="form.account_id"
                            :options="accountOptions"
                            placeholder="Select account"
                            :error="errors.account_id"
                            helper="Account to debit."
                        />
                        <FormSelect
                            label="Category"
                            v-model="form.category_id"
                            :options="categoryOptions"
                            placeholder="No category"
                            helper="Transaction category (optional)."
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Settings</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormSelect label="Status *" v-model="form.status" :options="statusOptions" />
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <input
                            id="auto_tx"
                            v-model="form.auto_create_transaction"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                        >
                        <label for="auto_tx" class="text-sm font-medium text-gray-700">Auto-create transaction on renewal</label>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Notes</h2>
                    <div class="mt-4">
                        <textarea
                            v-model="form.notes"
                            rows="4"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                        />
                    </div>
                </section>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm font-medium text-red-500 hover:text-red-600 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete subscription
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/subscriptions"
                        class="flex h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="h-10 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : (isEdit ? 'Update Subscription' : 'Save Subscription') }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete subscription?"
            message="This subscription will be permanently removed from tracking."
            confirm-label="Delete Subscription"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
