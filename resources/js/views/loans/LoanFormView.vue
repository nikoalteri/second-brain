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
    name: '',
    total_amount: '',
    monthly_payment: '',
    interest_rate: '',
    is_variable_rate: false,
    withdrawal_day: 1,
    skip_weekends: false,
    start_date: today,
    end_date: '',
    total_installments: '',
    paid_installments: 0,
    remaining_amount: '',
    status: 'active',
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

const LOAN_QUERY = gql`
    query GetLoan($id: ID!) {
        loan(id: $id) {
            id
            name
            total_amount
            monthly_payment
            interest_rate
            is_variable_rate
            paid_installments
            total_installments
            remaining_amount
            status
            start_date
            end_date
        }
    }
`;

const CREATE_LOAN = gql`
    mutation CreateLoan($input: CreateLoanInput!) {
        createLoan(input: $input) {
            id
        }
    }
`;

const UPDATE_LOAN = gql`
    mutation UpdateLoan($id: ID!, $input: UpdateLoanInput!) {
        updateLoan(id: $id, input: $input) {
            id
        }
    }
`;

const DELETE_LOAN = gql`
    mutation DeleteLoan($id: ID!) {
        deleteLoan(id: $id) {
            id
        }
    }
`;

const { result: accountsResult } = useQuery(ACCOUNTS_QUERY);
const { result: loanResult, loading: loadingLoan } = useQuery(
    LOAN_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).map((account) => ({ value: account.id, label: account.name }))
);
const statusOptions = ['active', 'paid', 'defaulted'].map((value) => ({
    value,
    label: value.charAt(0).toUpperCase() + value.slice(1),
}));

watch(
    loanResult,
    (value) => {
        if (value?.loan) {
            const loan = value.loan;
            form.value = {
                account_id: '',
                name: loan.name,
                total_amount: loan.total_amount,
                monthly_payment: loan.monthly_payment,
                interest_rate: loan.interest_rate,
                is_variable_rate: loan.is_variable_rate,
                withdrawal_day: 1,
                skip_weekends: false,
                start_date: loan.start_date ?? today,
                end_date: loan.end_date ?? '',
                total_installments: loan.total_installments,
                paid_installments: loan.paid_installments,
                remaining_amount: loan.remaining_amount,
                status: loan.status,
            };
        }
    },
    { immediate: true }
);

const { mutate: createLoan, loading: creating } = useMutation(CREATE_LOAN);
const { mutate: updateLoan, loading: updating } = useMutation(UPDATE_LOAN);
const { mutate: deleteLoan, loading: deleting } = useMutation(DELETE_LOAN);
const saving = computed(() => creating.value || updating.value);

async function handleSubmit() {
    errors.value = {};

    try {
        if (isEdit.value) {
            await updateLoan({
                id: route.params.id,
                input: {
                    name: form.value.name,
                    total_amount: parseFloat(form.value.total_amount),
                    monthly_payment: parseFloat(form.value.monthly_payment),
                    interest_rate: parseFloat(form.value.interest_rate) || undefined,
                    is_variable_rate: form.value.is_variable_rate,
                    status: form.value.status,
                },
            });
            addToast('Loan updated successfully.', 'success');
        } else {
            await createLoan({
                input: {
                    account_id: form.value.account_id,
                    name: form.value.name,
                    total_amount: parseFloat(form.value.total_amount),
                    monthly_payment: parseFloat(form.value.monthly_payment),
                    interest_rate: parseFloat(form.value.interest_rate) || undefined,
                    is_variable_rate: form.value.is_variable_rate,
                    withdrawal_day: parseInt(form.value.withdrawal_day),
                    skip_weekends: form.value.skip_weekends,
                    start_date: form.value.start_date,
                    end_date: form.value.end_date || undefined,
                    total_installments: parseInt(form.value.total_installments),
                    paid_installments: parseInt(form.value.paid_installments),
                    remaining_amount: parseFloat(form.value.remaining_amount) || undefined,
                    status: form.value.status,
                },
            });
            addToast('Loan recorded successfully.', 'success');
        }

        router.push('/loans');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    try {
        await deleteLoan({ id: route.params.id });
        addToast('Loan deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/loans');
    } catch {
        addToast('Could not delete loan. Please try again.', 'error');
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ isEdit ? 'Edit Loan' : 'Record Loan' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingLoan" class="py-16" />

        <form v-else class="max-w-xl" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-4 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <FormSelect
                    v-if="!isEdit"
                    label="Account *"
                    v-model="form.account_id"
                    :options="accountOptions"
                    placeholder="Select account"
                />
                <FormInput label="Loan Name *" v-model="form.name" placeholder="e.g. Car Loan" required />

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Total Amount *" v-model="form.total_amount" type="number" step="0.01" placeholder="0.00" />
                    <FormInput label="Monthly Payment *" v-model="form.monthly_payment" type="number" step="0.01" placeholder="0.00" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Interest Rate (%)" v-model="form.interest_rate" type="number" step="0.01" placeholder="0.00" />
                    <FormSelect label="Status *" v-model="form.status" :options="statusOptions" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Total Installments *" v-model="form.total_installments" type="number" step="1" placeholder="12" />
                    <FormInput label="Paid Installments *" v-model="form.paid_installments" type="number" step="1" placeholder="0" />
                </div>

                <template v-if="!isEdit">
                    <div class="grid grid-cols-2 gap-4">
                        <FormInput label="Start Date *" v-model="form.start_date" type="date" />
                        <FormInput label="End Date" v-model="form.end_date" type="date" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <FormInput label="Withdrawal Day *" v-model="form.withdrawal_day" type="number" min="1" max="31" placeholder="1" />
                        <FormInput label="Remaining Amount" v-model="form.remaining_amount" type="number" step="0.01" placeholder="0.00" />
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            id="skip_weekends"
                            v-model="form.skip_weekends"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500"
                        >
                        <label for="skip_weekends" class="text-sm text-gray-300">Skip weekends for payment dates</label>
                    </div>

                    <div class="flex items-center gap-3">
                        <input
                            id="is_variable"
                            v-model="form.is_variable_rate"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500"
                        >
                        <label for="is_variable" class="text-sm text-gray-300">Variable interest rate</label>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm text-red-400 hover:text-red-300 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete loan
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/loans"
                        class="flex h-10 items-center rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : (isEdit ? 'Update Loan' : 'Record Loan') }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete loan?"
            message="This loan will be permanently removed."
            confirm-label="Delete Loan"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
