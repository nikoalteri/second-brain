<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMutation, useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
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
const today = new Date().toISOString().split('T')[0];

const form = ref({
    account_id: '',
    to_account_id: '',
    transaction_type_id: '',
    transaction_category_id: '',
    amount: '',
    date: today,
    description: '',
    notes: '',
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
            parent_id
            name
            parent {
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
            to_account_id
            transaction_type_id
            transaction_category_id
            amount
            date
            description
            notes
            is_transfer
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

const { result: typesResult } = useQuery(TYPES_QUERY, null, {
    fetchPolicy: 'network-only',
});
const { result: categoriesResult } = useQuery(CATEGORIES_QUERY, null, {
    fetchPolicy: 'network-only',
});
const { result: txResult, loading: loadingTx } = useQuery(
    TX_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accounts = ref([]);

const typeOptions = computed(() =>
    (typesResult.value?.transactionTypes ?? []).map((type) => ({ value: type.id, label: type.name }))
);
const categoryOptions = computed(() => {
    const categories = categoriesResult.value?.transactionCategories ?? [];
    const parents = categories.filter((category) => !category.parent_id);
    const childrenByParentId = new Map();

    for (const category of categories.filter((item) => item.parent_id)) {
        const key = String(category.parent_id);
        const bucket = childrenByParentId.get(key) ?? [];
        bucket.push(category);
        childrenByParentId.set(key, bucket);
    }

    const options = [{ value: '', label: 'No category' }];

    for (const parent of parents) {
        const children = (childrenByParentId.get(String(parent.id)) ?? [])
            .sort((a, b) => a.name.localeCompare(b.name));

        if (children.length === 0) {
            options.push({
                value: parent.id,
                label: parent.name,
            });
            continue;
        }

        options.push({
            value: `group-${parent.id}`,
            label: parent.name,
            disabled: true,
        });

        for (const child of children) {
            options.push({
                value: child.id,
                label: `${parent.name} › ${child.name}`,
            });
        }
    }

    return options;
});
const accountOptions = computed(() =>
    accounts.value.map((account) => ({ value: account.id, label: account.name }))
);
const accountHelperText = computed(() => {
    if (accountOptions.value.length > 0) {
        return null;
    }

    return auth.isAdmin
        ? 'No accounts are available for this user. Only superadmin can select accounts across users.'
        : 'No accounts are available. You can create transactions only for accounts you own.';
});
const selectedTransactionTypeName = computed(() =>
    (typesResult.value?.transactionTypes ?? []).find((type) => type.id === form.value.transaction_type_id)?.name ?? ''
);
const showsDestinationAccount = computed(() => selectedTransactionTypeName.value.toLowerCase().includes('transfer'));
const destinationAccountOptions = computed(() =>
    accountOptions.value.filter((account) => account.value !== form.value.account_id)
);

watch(
    txResult,
    (value) => {
        if (value?.transaction) {
            const transaction = value.transaction;
            form.value = {
                account_id: transaction.account_id ?? '',
                to_account_id: transaction.to_account_id ?? '',
                transaction_type_id: transaction.transaction_type_id ?? '',
                transaction_category_id: transaction.transaction_category_id ?? '',
                amount: Math.abs(transaction.amount),
                date: transaction.date,
                description: transaction.description ?? '',
                notes: transaction.notes ?? '',
            };
        }
    },
    { immediate: true }
);

watch(showsDestinationAccount, (visible) => {
    if (!visible) {
        form.value.to_account_id = '';
    }
});

const { mutate: createTx, loading: creating } = useMutation(CREATE_TX);
const { mutate: updateTx, loading: updating } = useMutation(UPDATE_TX);
const { mutate: deleteTx, loading: deleting } = useMutation(DELETE_TX);
const saving = computed(() => creating.value || updating.value);

async function fetchAccounts() {
    if (!auth.accessToken) {
        accounts.value = [];
        return;
    }

    const response = await fetch('/api/v1/accounts?per_page=100', {
        headers: {
            Authorization: `Bearer ${auth.accessToken}`,
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        accounts.value = [];
        return;
    }

    const data = await response.json();
    accounts.value = data.data ?? [];
}

onMounted(() => {
    void fetchAccounts();
});

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

    if (showsDestinationAccount.value && !form.value.to_account_id) {
        errors.value.to_account_id = 'Destination account is required';
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
            to_account_id: showsDestinationAccount.value ? form.value.to_account_id : undefined,
            transaction_type_id: form.value.transaction_type_id,
            transaction_category_id: form.value.transaction_category_id || undefined,
            amount: parseFloat(form.value.amount),
            date: form.value.date,
            description: form.value.description,
            notes: form.value.notes || undefined,
            is_transfer: showsDestinationAccount.value,
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
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Transaction' : 'Add Transaction' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingTx" class="py-16" />

        <form v-else class="max-w-3xl" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Transaction details</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormSelect
                            label="Account *"
                            v-model="form.account_id"
                            :options="accountOptions"
                            placeholder="Select account"
                            :error="errors.account_id"
                            :helper="accountHelperText"
                            :disabled="!accountOptions.length"
                        />
                        <FormSelect
                            label="Type *"
                            v-model="form.transaction_type_id"
                            :options="typeOptions"
                            placeholder="Select type"
                            :error="errors.transaction_type_id"
                        />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <FormSelect
                        label="Category"
                        v-model="form.transaction_category_id"
                        :options="categoryOptions"
                        placeholder="No category"
                    />
                    <FormSelect
                        v-if="showsDestinationAccount"
                        label="Destination account *"
                        v-model="form.to_account_id"
                        :options="destinationAccountOptions"
                        placeholder="Select destination account"
                        :error="errors.to_account_id"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <FormInput
                        label="Amount *"
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        :error="errors.amount"
                    />
                    <FormInput label="Date *" v-model="form.date" type="date" :error="errors.date" />
                </div>

                <FormInput
                    label="Description *"
                    v-model="form.description"
                    placeholder="e.g. McDonald's"
                    :error="errors.description"
                />

                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700">Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        placeholder="Optional notes"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm font-medium text-red-500 hover:text-red-600 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete transaction
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/transactions"
                        class="flex h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex h-10 items-center gap-2 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
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
