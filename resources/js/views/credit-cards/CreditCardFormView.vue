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
    type: 'revolving',
    credit_limit: '',
    fixed_payment: '',
    interest_rate: '',
    stamp_duty_amount: '',
    statement_day: 1,
    due_day: 15,
    skip_weekends: false,
    status: 'active',
    start_date: today,
    interest_calculation_method: 'compound',
});

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

const CARD_QUERY = gql`
    query GetCreditCard($id: ID!) {
        creditCard(id: $id) {
            id
            name
            type
            credit_limit
            fixed_payment
            interest_rate
            stamp_duty_amount
            statement_day
            due_day
            skip_weekends
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

const DELETE_CARD = gql`
    mutation DeleteCreditCard($id: ID!) {
        deleteCreditCard(id: $id) {
            id
        }
    }
`;

const { result: accountsResult } = useQuery(ACCOUNTS_QUERY);
const { result: cardResult, loading: loadingCard } = useQuery(
    CARD_QUERY,
    () => ({ id: route.params.id }),
    () => ({ enabled: isEdit.value })
);

const accountOptions = computed(() =>
    (accountsResult.value?.accounts?.data ?? []).map((account) => ({ value: account.id, label: account.name }))
);
const typeOptions = ['revolving', 'charge'].map((value) => ({ value, label: value.charAt(0).toUpperCase() + value.slice(1) }));
const statusOptions = ['active', 'closed'].map((value) => ({ value, label: value.charAt(0).toUpperCase() + value.slice(1) }));
const methodOptions = ['compound', 'simple'].map((value) => ({ value, label: value.charAt(0).toUpperCase() + value.slice(1) }));

watch(
    cardResult,
    (value) => {
        if (value?.creditCard) {
            const card = value.creditCard;
            form.value = {
                account_id: '',
                name: card.name,
                type: card.type,
                credit_limit: card.credit_limit,
                fixed_payment: card.fixed_payment,
                interest_rate: card.interest_rate,
                stamp_duty_amount: card.stamp_duty_amount,
                statement_day: card.statement_day,
                due_day: card.due_day,
                skip_weekends: card.skip_weekends,
                status: card.status,
                start_date: card.start_date ?? today,
                interest_calculation_method: card.interest_calculation_method ?? 'compound',
            };
        }
    },
    { immediate: true }
);

const { mutate: createCard, loading: creating } = useMutation(CREATE_CARD);
const { mutate: updateCard, loading: updating } = useMutation(UPDATE_CARD);
const { mutate: deleteCard, loading: deleting } = useMutation(DELETE_CARD);
const saving = computed(() => creating.value || updating.value);

async function handleSubmit() {
    try {
        if (isEdit.value) {
            await updateCard({
                id: route.params.id,
                input: {
                    name: form.value.name,
                    credit_limit: parseFloat(form.value.credit_limit) || undefined,
                    fixed_payment: parseFloat(form.value.fixed_payment) || undefined,
                    interest_rate: parseFloat(form.value.interest_rate) || undefined,
                    statement_day: parseInt(form.value.statement_day),
                    due_day: parseInt(form.value.due_day),
                    skip_weekends: form.value.skip_weekends,
                    status: form.value.status,
                },
            });
            addToast('Card updated successfully.', 'success');
        } else {
            await createCard({
                input: {
                    account_id: form.value.account_id,
                    name: form.value.name,
                    type: form.value.type,
                    credit_limit: parseFloat(form.value.credit_limit) || undefined,
                    fixed_payment: parseFloat(form.value.fixed_payment) || undefined,
                    interest_rate: parseFloat(form.value.interest_rate) || undefined,
                    stamp_duty_amount: parseFloat(form.value.stamp_duty_amount) || undefined,
                    statement_day: parseInt(form.value.statement_day),
                    due_day: parseInt(form.value.due_day),
                    skip_weekends: form.value.skip_weekends,
                    status: form.value.status,
                    start_date: form.value.start_date,
                    interest_calculation_method: form.value.interest_calculation_method || undefined,
                },
            });
            addToast('Card saved successfully.', 'success');
        }

        router.push('/credit-cards');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    }
}

async function handleDelete() {
    try {
        await deleteCard({ id: route.params.id });
        addToast('Card deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/credit-cards');
    } catch {
        addToast('Could not delete card. Please try again.', 'error');
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ isEdit ? 'Edit Card' : 'Add Card' }}</h1>
        </div>

        <LoadingSpinner v-if="loadingCard" class="py-16" />

        <form v-else class="max-w-xl" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-4 rounded-xl border border-gray-700 bg-gray-800 p-6">
                <FormSelect
                    v-if="!isEdit"
                    label="Account *"
                    v-model="form.account_id"
                    :options="accountOptions"
                    placeholder="Select account"
                />
                <FormInput label="Card Name *" v-model="form.name" placeholder="e.g. Visa Gold" required />

                <div class="grid grid-cols-2 gap-4">
                    <FormSelect label="Card Type *" v-model="form.type" :options="typeOptions" />
                    <FormSelect label="Status *" v-model="form.status" :options="statusOptions" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Credit Limit" v-model="form.credit_limit" type="number" step="0.01" placeholder="0.00" />
                    <FormInput label="Fixed Payment" v-model="form.fixed_payment" type="number" step="0.01" placeholder="0.00" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Interest Rate (%)" v-model="form.interest_rate" type="number" step="0.01" placeholder="0.00" />
                    <FormInput label="Stamp Duty (EUR)" v-model="form.stamp_duty_amount" type="number" step="0.01" placeholder="0.00" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <FormInput label="Statement Day *" v-model="form.statement_day" type="number" min="1" max="31" placeholder="1" />
                    <FormInput label="Due Day *" v-model="form.due_day" type="number" min="1" max="31" placeholder="15" />
                </div>

                <template v-if="!isEdit">
                    <FormInput label="Start Date *" v-model="form.start_date" type="date" />
                    <FormSelect label="Interest Calculation" v-model="form.interest_calculation_method" :options="methodOptions" />
                </template>

                <div class="flex items-center gap-3">
                    <input
                        id="skip_weekends_card"
                        v-model="form.skip_weekends"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-blue-600 focus:ring-blue-500"
                    >
                    <label for="skip_weekends_card" class="text-sm text-gray-300">Skip weekends for billing dates</label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    v-if="isEdit"
                    type="button"
                    class="text-sm text-red-400 hover:text-red-300 focus:outline-none"
                    @click="showDeleteModal = true"
                >
                    Delete card
                </button>

                <div class="ml-auto flex gap-3">
                    <router-link
                        to="/credit-cards"
                        class="flex h-10 items-center rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors hover:bg-gray-600"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="h-10 rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
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
