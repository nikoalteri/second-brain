<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMutation, useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { addToast } = useToast();
const auth = useAuthStore();
const isEdit = computed(() => !!route.params.id);
const showDeleteModal = ref(false);
const today = new Date().toISOString().split('T')[0];
const accounts = ref([]);

const form = ref({
    account_id: '',
    name: '',
    total_amount: '',
    monthly_payment: '',
    interest_rate: '',
    is_variable_rate: false,
    withdrawal_day: 1,
    skip_weekends: true,
    start_date: today,
    end_date: '',
    total_installments: 1,
    paid_installments: 0,
    remaining_amount: '',
    status: 'active',
});

const LOAN_QUERY = gql`
    query GetLoan($id: ID!) {
        loan(id: $id) {
            id
            account_id
            name
            total_amount
            monthly_payment
            interest_rate
            is_variable_rate
            withdrawal_day
            skip_weekends
            start_date
            end_date
            total_installments
            paid_installments
            remaining_amount
            status
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

const { result: loanResult, loading: loadingLoan } = useQuery(
    LOAN_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    accounts.value.map((account) => ({ value: account.id, label: account.name }))
);
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'defaulted', label: 'Defaulted' },
];

function calcMonthlyPayment(total, rate, installments) {
    const totalAmount = Number(total || 0);
    const installmentCount = Number(installments || 0);

    if (installmentCount <= 0 || totalAmount <= 0) {
        return '';
    }

    if (rate > 0) {
        const monthlyRate = (rate / 100) / 12;
        return Number(((totalAmount * monthlyRate * (1 + monthlyRate) ** installmentCount) / ((1 + monthlyRate) ** installmentCount - 1)).toFixed(2));
    }

    return Number((totalAmount / installmentCount).toFixed(2));
}

function calcOutstandingPrincipal(total, rate, monthlyPayment, paidInstallments) {
    const totalAmount = Number(total || 0);
    const payment = Number(monthlyPayment || 0);
    const paid = Number(paidInstallments || 0);

    if (totalAmount <= 0 || payment <= 0) {
        return totalAmount ? Number(totalAmount.toFixed(2)) : '';
    }

    if (rate > 0 && paid > 0) {
        const monthlyRate = (rate / 100) / 12;
        return Number(Math.max(0, totalAmount * (1 + monthlyRate) ** paid - payment * ((1 + monthlyRate) ** paid - 1) / monthlyRate).toFixed(2));
    }

    return Number(Math.max(0, totalAmount - paid * payment).toFixed(2));
}

const effectiveInterestRate = computed(() => form.value.is_variable_rate ? 0 : Number(form.value.interest_rate || 0));
const totalInterestLabel = computed(() => {
    const totalAmount = Number(form.value.total_amount || 0);
    const installmentCount = Number(form.value.total_installments || 0);
    const monthlyPayment = Number(form.value.monthly_payment || 0);

    if (totalAmount <= 0 || installmentCount <= 0 || monthlyPayment <= 0) {
        return '€ 0.00';
    }

    return `€ ${Math.max(0, (monthlyPayment * installmentCount) - totalAmount).toFixed(2)}`;
});

function recalculateLoan() {
    const monthlyPayment = calcMonthlyPayment(form.value.total_amount, effectiveInterestRate.value, form.value.total_installments);
    form.value.monthly_payment = monthlyPayment;
    form.value.remaining_amount = calcOutstandingPrincipal(
        form.value.total_amount,
        effectiveInterestRate.value,
        monthlyPayment,
        form.value.paid_installments
    );
}

watch(
    () => [
        form.value.total_amount,
        form.value.interest_rate,
        form.value.is_variable_rate,
        form.value.total_installments,
        form.value.paid_installments,
    ],
    recalculateLoan
);

watch(
    loanResult,
    (value) => {
        if (value?.loan) {
            const loan = value.loan;
            form.value = {
                account_id: loan.account_id ?? '',
                name: loan.name,
                total_amount: loan.total_amount,
                monthly_payment: loan.monthly_payment,
                interest_rate: loan.interest_rate,
                is_variable_rate: loan.is_variable_rate,
                withdrawal_day: loan.withdrawal_day ?? 1,
                skip_weekends: loan.skip_weekends ?? true,
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
    const input = {
        account_id: form.value.account_id,
        name: form.value.name,
        total_amount: parseFloat(form.value.total_amount),
        monthly_payment: parseFloat(form.value.monthly_payment),
        interest_rate: form.value.is_variable_rate ? null : (form.value.interest_rate === '' ? null : parseFloat(form.value.interest_rate)),
        is_variable_rate: form.value.is_variable_rate,
        withdrawal_day: parseInt(form.value.withdrawal_day, 10),
        skip_weekends: form.value.skip_weekends,
        start_date: form.value.start_date,
        end_date: form.value.end_date || undefined,
        total_installments: parseInt(form.value.total_installments, 10),
        paid_installments: parseInt(form.value.paid_installments, 10),
        remaining_amount: parseFloat(form.value.remaining_amount),
        status: form.value.status,
    };

    try {
        if (isEdit.value) {
            await updateLoan({
                id: route.params.id,
                input,
            });
            addToast('Loan updated successfully.', 'success');
        } else {
            await createLoan({ input });
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
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? t('loansForm.edit') : t('loansForm.add') }}</h1>
        </div>

        <LoadingSpinner v-if="loadingLoan" class="py-16" />

        <form v-else class="max-w-4xl" @submit.prevent="handleSubmit">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ t('loansForm.mainData') }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput label="Name *" v-model="form.name" required />
                        <FormSelect
                            label="Account *"
                            v-model="form.account_id"
                            :options="accountOptions"
                            placeholder="Select account"
                        />
                        <FormInput
                            label="Total Amount *"
                            v-model="form.total_amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <FormInput
                            v-if="!form.is_variable_rate"
                            label="Interest Rate (%)"
                            v-model="form.interest_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <FormInput
                            label="Total Installments *"
                            v-model="form.total_installments"
                            type="number"
                            min="1"
                            step="1"
                        />
                        <FormInput
                            label="Paid Installments *"
                            v-model="form.paid_installments"
                            type="number"
                            min="0"
                            step="1"
                        />
                        <FormInput
                            label="Monthly Payment"
                            v-model="form.monthly_payment"
                            type="number"
                            step="0.01"
                            readonly
                        />
                        <FormInput
                            label="Remaining Amount"
                            v-model="form.remaining_amount"
                            type="number"
                            step="0.01"
                            readonly
                        />
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <input
                            id="is_variable_rate"
                            v-model="form.is_variable_rate"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                        >
                        <label for="is_variable_rate" class="text-sm font-medium text-gray-700">Variable rate</label>
                    </div>

                    <p class="mt-4 text-sm text-gray-500">Total interest (full loan): <span class="font-medium text-gray-900">{{ totalInterestLabel }}</span></p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ t('loansForm.schedule') }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput label="Start Date *" v-model="form.start_date" type="date" />
                        <FormInput label="End Date" v-model="form.end_date" type="date" />
                        <FormInput
                            label="Withdrawal Day *"
                            v-model="form.withdrawal_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                        />
                        <FormSelect label="Status *" v-model="form.status" :options="statusOptions" />
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <input
                            id="skip_weekends"
                            v-model="form.skip_weekends"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                        >
                        <label for="skip_weekends" class="text-sm font-medium text-gray-700">Skip weekends</label>
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
                    {{ t('loansForm.delete') }}
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/loans"
                        class="flex h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        {{ t('common.actions.cancel') }}
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex h-10 items-center gap-2 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? t('settings.actions.saving') : (isEdit ? t('loansForm.update') : t('loansForm.save')) }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            :title="`${t('loansForm.delete')}?`"
            message="This loan will be permanently removed."
            :confirm-label="t('loansForm.delete')"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
