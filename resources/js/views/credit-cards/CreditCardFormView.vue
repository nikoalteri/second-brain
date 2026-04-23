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
const accounts = ref([]);

const form = ref({
    account_id: '',
    name: '',
    type: 'charge',
    credit_limit: '',
    fixed_payment: '',
    interest_rate: '',
    stamp_duty_amount: 2,
    statement_day: 1,
    due_day: 15,
    skip_weekends: true,
    current_balance: 0,
    status: 'active',
    start_date: today,
    interest_calculation_method: 'daily_balance',
});

const CARD_QUERY = gql`
    query GetCreditCard($id: ID!) {
        creditCard(id: $id) {
            id
            account_id
            name
            type
            credit_limit
            fixed_payment
            interest_rate
            stamp_duty_amount
            statement_day
            due_day
            skip_weekends
            current_balance
            status
            start_date
            interest_calculation_method
        }
    }
`;

const CREATE_CARD = gql`
    mutation CreateCreditCard($input: CreateCreditCardInput!) {
        createCreditCard(input: $input) {
            id
        }
    }
`;

const UPDATE_CARD = gql`
    mutation UpdateCreditCard($id: ID!, $input: UpdateCreditCardInput!) {
        updateCreditCard(id: $id, input: $input) {
            id
        }
    }
`;

const { result: cardResult, loading: loadingCard } = useQuery(
    CARD_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    accounts.value.map((account) => ({ value: account.id, label: account.name }))
);
const typeOptions = [
    { value: 'charge', label: 'Charge' },
    { value: 'revolving', label: 'Revolving' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'closed', label: 'Closed' },
];
const methodOptions = [
    { value: 'daily_balance', label: 'Daily Balance Method' },
    { value: 'direct_monthly', label: 'Direct Monthly Method' },
];
const isRevolving = computed(() => form.value.type === 'revolving');

watch(
    cardResult,
    (value) => {
        if (value?.creditCard) {
            const card = value.creditCard;
            form.value = {
                account_id: card.account_id ?? '',
                name: card.name,
                type: card.type,
                credit_limit: card.credit_limit,
                fixed_payment: card.fixed_payment,
                interest_rate: card.interest_rate,
                stamp_duty_amount: card.stamp_duty_amount,
                statement_day: card.statement_day,
                due_day: card.due_day,
                skip_weekends: card.skip_weekends ?? true,
                current_balance: card.current_balance ?? 0,
                status: card.status,
                start_date: card.start_date ?? today,
                interest_calculation_method: card.interest_calculation_method ?? 'daily_balance',
            };
        }
    },
    { immediate: true }
);

watch(
    () => form.value.type,
    (type) => {
        if (type === 'revolving') {
            form.value.interest_calculation_method ||= 'daily_balance';
            return;
        }

        form.value.fixed_payment = '';
        form.value.interest_rate = '';
        form.value.interest_calculation_method = '';
    },
    { immediate: true }
);

const { mutate: createCard, loading: creating } = useMutation(CREATE_CARD);
const { mutate: updateCard, loading: updating } = useMutation(UPDATE_CARD);
const deleting = ref(false);
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

function parseOptionalFloat(value) {
    return value === '' || value === null ? null : parseFloat(value);
}

async function handleSubmit() {
    const input = {
        account_id: form.value.account_id,
        name: form.value.name,
        type: form.value.type,
        credit_limit: parseOptionalFloat(form.value.credit_limit),
        fixed_payment: parseOptionalFloat(form.value.fixed_payment),
        interest_rate: parseOptionalFloat(form.value.interest_rate),
        stamp_duty_amount: parseOptionalFloat(form.value.stamp_duty_amount) ?? 0,
        statement_day: parseInt(form.value.statement_day, 10),
        due_day: parseInt(form.value.due_day, 10),
        skip_weekends: form.value.skip_weekends,
        current_balance: parseOptionalFloat(form.value.current_balance) ?? 0,
        status: form.value.status,
        start_date: form.value.start_date || null,
        interest_calculation_method: form.value.interest_calculation_method || null,
    };

    try {
        if (isEdit.value) {
            await updateCard({
                id: route.params.id,
                input,
            });
            addToast('Card updated successfully.', 'success');
        } else {
            await createCard({ input });
            addToast('Card saved successfully.', 'success');
        }

        router.push('/credit-cards');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    deleting.value = true;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}`, {
            method: 'DELETE',
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to delete card.');
        }

        addToast('Card deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/credit-cards');
    } catch {
        addToast('Could not delete card. Please try again.', 'error');
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    void fetchAccounts();
});
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Card' : 'Add Card' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingCard" class="py-16" />

        <form v-else class="max-w-4xl" @submit.prevent="handleSubmit">
            <div class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Anagrafica</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Name *"
                            v-model="form.name"
                            placeholder="e.g. Bank + card nickname"
                        />
                        <FormSelect
                            label="Settlement account *"
                            v-model="form.account_id"
                            :options="accountOptions"
                            placeholder="Select account"
                        />
                        <FormSelect
                            label="Type *"
                            v-model="form.type"
                            :options="typeOptions"
                        />
                        <FormSelect
                            label="Status *"
                            v-model="form.status"
                            :options="statusOptions"
                        />
                        <FormInput
                            label="Start date"
                            v-model="form.start_date"
                            type="date"
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Rules</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Credit limit"
                            v-model="form.credit_limit"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <FormInput
                            label="Max monthly installment"
                            v-model="form.fixed_payment"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :disabled="!isRevolving"
                        />
                        <FormInput
                            label="Interest rate (%)"
                            v-model="form.interest_rate"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0.00"
                            :disabled="!isRevolving"
                        />
                        <FormInput
                            label="Stamp duty"
                            v-model="form.stamp_duty_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <FormInput
                            label="Statement day *"
                            v-model="form.statement_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                        />
                        <FormInput
                            label="Due day *"
                            v-model="form.due_day"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                        />
                        <FormInput
                            label="Current balance *"
                            v-model="form.current_balance"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <FormSelect
                            label="Interest calculation method"
                            v-model="form.interest_calculation_method"
                            :options="methodOptions"
                            :disabled="!isRevolving"
                        />
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <input
                            id="skip_weekends_card"
                            v-model="form.skip_weekends"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                        >
                        <label for="skip_weekends_card" class="text-sm font-medium text-gray-700">Skip weekends</label>
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
                    Delete card
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/credit-cards"
                        class="flex h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="h-10 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ saving ? 'Saving…' : (isEdit ? 'Update Card' : 'Save Card') }}
                    </button>
                </div>
            </div>
        </form>

        <ConfirmModal
            :open="showDeleteModal"
            title="Delete card?"
            message="This will remove the card and all billing cycle data."
            confirm-label="Delete Card"
            :loading="deleting"
            @confirm="handleDelete"
            @cancel="showDeleteModal = false"
        />
    </AppLayout>
</template>
