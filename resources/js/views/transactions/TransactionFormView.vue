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
const today = new Date().toISOString().split('T')[0];

const form = ref({
    account_id: '',
    transaction_type_id: '',
    transaction_category_id: '',
    amount: '',
    date: today,
    description: '',
    notes: '',
    is_transfer: false,
});
const errors = ref({});

const TYPES_QUERY = gql`
    query GetTransactionTypes {
        transactionTypes {
            id
            name
            is_income
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

const TX_QUERY = gql`
    query GetTransaction($id: ID!) {
        transaction(id: $id) {
            id
            account_id
            transaction_type_id
            amount
            date
            description
            notes
            is_transfer
            category {
                id
                name
            }
        }
    }
`;

const CREATE_TX = gql`
    mutation CreateTransaction($input: CreateTransactionInput!) {
        createTransaction(input: $input) {
            id
        }
    }
`;

const UPDATE_TX = gql`
    mutation UpdateTransaction($id: ID!, $input: UpdateTransactionInput!) {
        updateTransaction(id: $id, input: $input) {
            id
        }
    }
`;

const DELETE_TX = gql`
    mutation DeleteTransaction($id: ID!) {
        deleteTransaction(id: $id) {
            id
        }
    }
`;

const { result: typesResult } = useQuery(TYPES_QUERY);
const { result: categoriesResult } = useQuery(CATEGORIES_QUERY);
const { result: accountsResult } = useQuery(ACCOUNTS_QUERY);
const { result: txResult, loading: loadingTx } = useQuery(
    TX_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const typeOptions = computed(() =>
    (typesResult.value?.transactionTypes ?? []).map((type) => ({ value: type.id, label: type.name }))
);
const categoryOptions = computed(() => [
    { value: '', label: 'No category' },
    ...(categoriesResult.value?.transactionCategories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
]);
const accountOptions = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).map((account) => ({ value: account.id, label: account.name }))
);

watch(
    txResult,
    (value) => {
        if (value?.transaction) {
            const transaction = value.transaction;
            form.value = {
                account_id: transaction.account_id ?? '',
                transaction_type_id: transaction.transaction_type_id ?? '',
                transaction_category_id: transaction.category?.id ?? '',
                amount: Math.abs(transaction.amount),
                date: transaction.date,
                description: transaction.description ?? '',
                notes: transaction.notes ?? '',
                is_transfer: transaction.is_transfer ?? false,
            };
        }
    },
    { immediate: true }
);

const { mutate: createTx, loading: creating } = useMutation(CREATE_TX);
const { mutate: updateTx, loading: updating } = useMutation(UPDATE_TX);
const { mutate: deleteTx, loading: deleting } = useMutation(DELETE_TX);
const saving = computed(() => creating.value || updating.value);

async function handleSubmit() {
    errors.value = {};

    if (!form.value.account_id) {
        errors.value.account_id = 'Account is required';
        return;
    }

    if (!form.value.transaction_type_id) {
        errors.value.transaction_type_id = 'Transaction type is required';
        return;
    }

    if (!form.value.amount || parseFloat(form.value.amount) <= 0) {
        errors.value.amount = 'Amount must be greater than 0';
        return;
    }

    if (!form.value.date) {
        errors.value.date = 'Date is required';
        return;
    }

    if (!form.value.description) {
        errors.value.description = 'Description is required';
        return;
    }

    try {
        const input = {
            account_id: form.value.account_id,
            transaction_type_id: form.value.transaction_type_id,
            transaction_category_id: form.value.transaction_category_id || undefined,
            amount: parseFloat(form.value.amount),
            date: form.value.date,
            description: form.value.description,
            notes: form.value.notes || undefined,
            is_transfer: form.value.is_transfer,
        };

        if (isEdit.value) {
            await updateTx({ id: route.params.id, input });
            addToast('Transaction updated successfully.', 'success');
        } else {
            await createTx({ input });
            addToast('Transaction added successfully.', 'success');
        }

        router.push('/transactions');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    try {
        await deleteTx({ id: route.params.id });
        addToast('Transaction deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/transactions');
    } catch {
        addToast('Could not delete transaction. Please try again.', 'error');
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-white">{{ isEdit ? 'Edit Transaction' : 'Add Transaction' }}</h1>
            </div>
        </div>

        <LoadingSpinner v-if="loadingTx" class="py-16" />

        <form v-else class="max-w-xl" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-4 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <FormSelect
                    label="Account *"
                    v-model="form.account_id"
                    :options="accountOptions"
                    placeholder="Select account"
                    :error="errors.account_id"
                />

                <FormSelect
                    label="Transaction Type *"
                    v-model="form.transaction_type_id"
                    :options="typeOptions"
                    placeholder="Select type"
                    :error="errors.transaction_type_id"
                />

                <FormSelect
                    label="Category"
                    v-model="form.transaction_category_id"
                    :options="categoryOptions"
                    placeholder="No category"
                />

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-normal text-gray-300">Amount *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400">EUR</span>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="0.00"
                            class="h-10 w-full rounded-lg border bg-gray-900 pl-12 pr-3 text-right font-mono text-base text-gray-100 placeholder:text-gray-500 transition-colors focus:outline-none focus:ring-1"
                            :class="errors.amount
                                ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                                : 'border-gray-700 focus:border-blue-500 focus:ring-blue-500'"
                        >
                    </div>
                    <p v-if="errors.amount" class="text-sm text-red-400">{{ errors.amount }}</p>
                </div>

                <FormInput label="Date *" v-model="form.date" type="date" :error="errors.date" />
                <FormInput
                    label="Description *"
                    v-model="form.description"
                    placeholder="e.g. Grocery shopping"
                    :error="errors.description"
                />
                <FormInput label="Notes" v-model="form.notes" placeholder="Optional notes" />

                <div class="flex items-center gap-3">
                    <input
                        id="is_transfer"
                        v-model="form.is_transfer"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500"
                    >
                    <label for="is_transfer" class="text-sm font-normal text-gray-300">This is a transfer between accounts</label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm text-red-400 hover:text-red-300 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete transaction
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/transactions"
                        class="flex h-10 items-center rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : (isEdit ? 'Update Transaction' : 'Add Transaction') }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete transaction?"
            message="This transaction will be permanently removed."
            confirm-label="Delete Transaction"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
