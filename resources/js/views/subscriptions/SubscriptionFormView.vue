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

const SUB_QUERY = gql`
    query GetSubscription($id: ID!) {
        subscription(id: $id) {
            id
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
const { result: subResult, loading: loadingSub } = useQuery(
    SUB_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).map((account) => ({ value: account.id, label: account.name }))
);
const frequencyOptions = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'annual', label: 'Yearly' },
    { value: 'biennial', label: 'Biennial' },
    { value: 'weekly', label: 'Weekly' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'trial', label: 'Trial' },
];

watch(
    subResult,
    (value) => {
        if (value?.subscription) {
            const subscription = value.subscription;
            form.value = {
                account_id: '',
                name: subscription.name,
                monthly_cost: subscription.monthly_cost,
                annual_cost: subscription.annual_cost,
                frequency: subscription.frequency,
                day_of_month: subscription.day_of_month,
                next_renewal_date: subscription.next_renewal_date ?? defaultRenewal,
                auto_create_transaction: subscription.auto_create_transaction,
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

async function handleSubmit() {
    errors.value = {};

    if (!form.value.name) {
        errors.value.name = 'Name is required';
        return;
    }

    if (!form.value.frequency) {
        errors.value.frequency = 'Frequency is required';
        return;
    }

    try {
        if (isEdit.value) {
            await updateSub({
                id: route.params.id,
                input: {
                    name: form.value.name,
                    monthly_cost: parseFloat(form.value.monthly_cost) || undefined,
                    annual_cost: parseFloat(form.value.annual_cost) || undefined,
                    frequency: form.value.frequency,
                    day_of_month: parseInt(form.value.day_of_month),
                    next_renewal_date: form.value.next_renewal_date,
                    status: form.value.status,
                    notes: form.value.notes || undefined,
                },
            });
            addToast('Subscription updated successfully.', 'success');
        } else {
            if (!form.value.account_id) {
                errors.value.account_id = 'Account is required';
                return;
            }

            await createSub({
                input: {
                    account_id: form.value.account_id,
                    name: form.value.name,
                    monthly_cost: parseFloat(form.value.monthly_cost) || undefined,
                    annual_cost: parseFloat(form.value.annual_cost) || undefined,
                    frequency: form.value.frequency,
                    day_of_month: parseInt(form.value.day_of_month),
                    next_renewal_date: form.value.next_renewal_date,
                    auto_create_transaction: form.value.auto_create_transaction,
                    status: form.value.status,
                    notes: form.value.notes || undefined,
                },
            });
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
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ isEdit ? 'Edit Subscription' : 'Add Subscription' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingSub" class="py-16" />

        <form v-else class="max-w-xl" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-4 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <FormSelect
                    v-if="!isEdit"
                    label="Account *"
                    v-model="form.account_id"
                    :options="accountOptions"
                    placeholder="Select account"
                    :error="errors.account_id"
                />
                <FormInput label="Service Name *" v-model="form.name" placeholder="e.g. Netflix" :error="errors.name" />

                <div class="grid grid-cols-2 gap-4">
                    <FormSelect label="Billing Frequency *" v-model="form.frequency" :options="frequencyOptions" :error="errors.frequency" />
                    <FormSelect label="Status *" v-model="form.status" :options="statusOptions" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Monthly Cost (EUR)" v-model="form.monthly_cost" type="number" step="0.01" placeholder="0.00" />
                    <FormInput label="Annual Cost (EUR)" v-model="form.annual_cost" type="number" step="0.01" placeholder="0.00" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Day of Month *" v-model="form.day_of_month" type="number" min="1" max="31" placeholder="1" />
                    <FormInput label="Next Renewal Date *" v-model="form.next_renewal_date" type="date" />
                </div>

                <FormInput label="Notes" v-model="form.notes" placeholder="Optional notes" />

                <div class="flex items-center gap-3">
                    <input
                        id="auto_tx"
                        v-model="form.auto_create_transaction"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500"
                    >
                    <label for="auto_tx" class="text-sm text-gray-300">Auto-create transaction on renewal</label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm text-red-400 hover:text-red-300 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete subscription
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/subscriptions"
                        class="flex h-10 items-center rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="h-10 rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
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
