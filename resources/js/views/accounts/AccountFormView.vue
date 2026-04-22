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

const accountTypeLabels = {
    bank: 'Bank',
    cash: 'Cash',
    investment: 'Investment',
    emergency_fund: 'Emergency Fund',
    debt: 'Debt',
};

const isEdit = computed(() => !!route.params.id);
const showDeleteModal = ref(false);
const form = ref({ name: '', type: 'bank', opening_balance: 0, balance: 0, currency: 'EUR', is_active: true });
const errors = ref({});

const typeOptions = ['bank', 'cash', 'investment', 'emergency_fund', 'debt'].map((value) => ({
    value,
    label: accountTypeLabels[value] ?? value,
}));
const currencyOptions = ['EUR', 'USD', 'GBP', 'CHF'].map((value) => ({ value, label: value }));

const ACCOUNT_QUERY = gql`
    query GetAccount($id: ID!) {
        account(id: $id) {
            id
            name
            type
            balance
            opening_balance
            currency
            is_active
        }
    }
`;

const { result: accountResult, loading: loadingAccount } = useQuery(
    ACCOUNT_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

watch(
    accountResult,
    (value) => {
        if (value?.account) {
            const account = value.account;
            form.value = {
                name: account.name,
                type: account.type,
                balance: account.balance,
                opening_balance: account.opening_balance,
                currency: account.currency,
                is_active: account.is_active,
            };
        }
    },
    { immediate: true }
);

const CREATE_ACCOUNT = gql`
    mutation CreateAccount($input: CreateAccountInput!) {
        createAccount(input: $input) {
            id
            name
        }
    }
`;

const UPDATE_ACCOUNT = gql`
    mutation UpdateAccount($id: ID!, $input: UpdateAccountInput!) {
        updateAccount(id: $id, input: $input) {
            id
            name
        }
    }
`;

const DELETE_ACCOUNT = gql`
    mutation DeleteAccount($id: ID!) {
        deleteAccount(id: $id) {
            id
        }
    }
`;

const { mutate: createAccount, loading: creating } = useMutation(CREATE_ACCOUNT);
const { mutate: updateAccount, loading: updating } = useMutation(UPDATE_ACCOUNT);
const { mutate: deleteAccount, loading: deleting } = useMutation(DELETE_ACCOUNT);
const saving = computed(() => creating.value || updating.value);

async function handleSubmit() {
    errors.value = {};

    try {
        if (isEdit.value) {
            await updateAccount({
                id: route.params.id,
                input: {
                    name: form.value.name,
                    type: form.value.type,
                    currency: form.value.currency,
                    is_active: form.value.is_active,
                },
            });
            addToast('Account updated successfully.', 'success');
        } else {
            await createAccount({
                input: {
                    name: form.value.name,
                    type: form.value.type,
                    opening_balance: parseFloat(form.value.opening_balance),
                    currency: form.value.currency,
                    is_active: form.value.is_active,
                },
            });
            addToast('Account created successfully.', 'success');
        }

        router.push('/accounts');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    try {
        await deleteAccount({ id: route.params.id });
        addToast('Account deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/accounts');
    } catch {
        addToast('Could not delete account. Please try again.', 'error');
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Account' : 'Add Account' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Use the same account type, balance, currency, and activation fields defined in the Filament account form.</p>
        </div>

        <LoadingSpinner v-if="loadingAccount" class="py-16" />

        <form v-else class="max-w-3xl" @submit.prevent="handleSubmit">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 md:grid-cols-2">
                    <FormInput label="Name *" v-model="form.name" placeholder="e.g. Main Bank Account" required :error="errors.name" />
                    <FormSelect label="Type *" v-model="form.type" :options="typeOptions" :error="errors.type" />
                    <FormInput
                        label="Current Balance"
                        v-model="form.balance"
                        type="number"
                        step="0.01"
                        readonly
                        helper="Calculated automatically by the system."
                    />
                    <FormInput
                        label="Opening Balance"
                        v-model="form.opening_balance"
                        type="number"
                        step="0.01"
                        min="0"
                        :readonly="isEdit"
                        helper="Balance at the time of system adoption."
                    />
                    <FormSelect label="Currency *" v-model="form.currency" :options="currencyOptions" :error="errors.currency" />
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                    >
                    <label for="is_active" class="text-sm font-medium text-gray-700">Active</label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm font-medium text-red-500 hover:text-red-600 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete account
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/accounts"
                        class="flex h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex h-10 items-center gap-2 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : (isEdit ? 'Update Account' : 'Create Account') }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete account?"
            message="This will permanently remove the account and all its transaction history. This cannot be undone."
            confirm-label="Delete Account"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
