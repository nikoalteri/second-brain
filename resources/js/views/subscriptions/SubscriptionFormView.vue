<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useQuery } from '@vue/apollo-composable';
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
const loadingSub = ref(false);
const deleting = ref(false);
const saving = ref(false);
const accounts = ref([]);
const creditCards = ref([]);
const frequencies = ref([]);
const defaultRenewal = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

const form = ref({
    account_id: '',
    credit_card_id: '',
    category_id: '',
    name: '',
    billing_amount: '',
    subscription_frequency_id: '',
    day_of_month: 1,
    next_renewal_date: defaultRenewal,
    auto_create_transaction: false,
    status: 'active',
    notes: '',
});
const errors = ref({});

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

const { result: categoriesResult } = useQuery(CATEGORIES_QUERY);

const accountOptions = computed(() =>
    accounts.value.map((account) => ({ value: String(account.id), label: account.name }))
);
const creditCardOptions = computed(() =>
    creditCards.value.map((card) => ({ value: String(card.id), label: card.name }))
);
const frequencyOptions = computed(() =>
    frequencies.value.map((frequency) => ({
        value: String(frequency.id),
        label: frequency.name,
    }))
);
const selectedFrequency = computed(() =>
    frequencies.value.find((frequency) => String(frequency.id) === String(form.value.subscription_frequency_id))
);
const amountLabel = computed(() => {
    const monthsInterval = selectedFrequency.value?.months_interval ?? 1;

    return monthsInterval === 1 ? 'Monthly charge *' : 'Renewal amount *';
});
const amountHelper = computed(() => {
    const monthsInterval = selectedFrequency.value?.months_interval ?? 1;

    return monthsInterval === 1
        ? 'Amount charged every month.'
        : `Amount charged every ${monthsInterval} month${monthsInterval === 1 ? '' : 's'}.`;
});
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
                value: String(parent.id),
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
                value: String(child.id),
                label: `${parent.name} › ${child.name}`,
            });
        }
    }

    return options;
});
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'cancelled', label: 'Cancelled' },
];

watch(
    () => form.value.account_id,
    (value) => {
        if (value) {
            form.value.credit_card_id = '';
        }
    }
);

watch(
    () => form.value.credit_card_id,
    (value) => {
        if (value) {
            form.value.account_id = '';
        }
    }
);

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: {
            Authorization: `Bearer ${auth.accessToken}`,
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${url}`);
    }

    return response.json();
}

async function fetchLookupData() {
    if (!auth.accessToken) {
        accounts.value = [];
        creditCards.value = [];
        frequencies.value = [];
        return;
    }

    const [accountsData, cardsData, frequenciesData] = await Promise.all([
        fetchJson('/api/v1/accounts?per_page=100'),
        fetchJson('/api/v1/credit-cards?per_page=100'),
        fetchJson('/api/v1/subscription-frequencies'),
    ]);

    accounts.value = accountsData.data ?? [];
    creditCards.value = cardsData.data ?? [];
    frequencies.value = frequenciesData.data ?? [];

    if (!form.value.subscription_frequency_id && frequencies.value.length) {
        form.value.subscription_frequency_id = String(frequencies.value[0].id);
    }
}

async function fetchSubscription() {
    if (!isEdit.value || !auth.accessToken) {
        return;
    }

    loadingSub.value = true;

    try {
        const data = await fetchJson(`/api/v1/subscriptions/${route.params.id}`);
        const subscription = data.data;

        form.value = {
            account_id: subscription.account_id ? String(subscription.account_id) : '',
            credit_card_id: subscription.credit_card_id ? String(subscription.credit_card_id) : '',
            category_id: subscription.category_id ? String(subscription.category_id) : '',
            name: subscription.name ?? '',
            billing_amount: String(subscription.billing_amount ?? ''),
            subscription_frequency_id: subscription.subscription_frequency_id ? String(subscription.subscription_frequency_id) : '',
            day_of_month: subscription.day_of_month ?? 1,
            next_renewal_date: subscription.next_renewal_date ?? defaultRenewal,
            auto_create_transaction: !!subscription.auto_create_transaction,
            status: subscription.status ?? 'active',
            notes: subscription.notes ?? '',
        };
    } finally {
        loadingSub.value = false;
    }
}

onMounted(async () => {
    try {
        await fetchLookupData();
        await fetchSubscription();
    } catch {
        addToast('Could not load subscription settings. Please refresh and try again.', 'error');
    }
});

function normalizeOptionalInt(value) {
    return value === '' || value === null ? null : parseInt(value, 10);
}

function parseOptionalFloat(value) {
    return value === '' || value === null ? null : parseFloat(value);
}

async function handleSubmit() {
    errors.value = {};

    if (!form.value.name) {
        errors.value.name = 'Name is required';
        return;
    }

    if (!form.value.account_id && !form.value.credit_card_id) {
        errors.value.account_id = 'Choose an account or a credit card';
        errors.value.credit_card_id = 'Choose an account or a credit card';
        return;
    }

    if (!form.value.subscription_frequency_id) {
        errors.value.subscription_frequency_id = 'Frequency is required';
        return;
    }

    saving.value = true;

    try {
        const payload = {
            account_id: normalizeOptionalInt(form.value.account_id),
            credit_card_id: normalizeOptionalInt(form.value.credit_card_id),
            category_id: normalizeOptionalInt(form.value.category_id),
            name: form.value.name,
            billing_amount: parseOptionalFloat(form.value.billing_amount),
            subscription_frequency_id: normalizeOptionalInt(form.value.subscription_frequency_id),
            day_of_month: parseInt(form.value.day_of_month, 10),
            next_renewal_date: form.value.next_renewal_date,
            auto_create_transaction: form.value.auto_create_transaction,
            status: form.value.status,
            notes: form.value.notes || null,
        };

        const response = await fetch(
            isEdit.value ? `/api/v1/subscriptions/${route.params.id}` : '/api/v1/subscriptions',
            {
                method: isEdit.value ? 'PUT' : 'POST',
                headers: {
                    Authorization: `Bearer ${auth.accessToken}`,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            }
        );

        if (response.status === 422) {
            const data = await response.json();
            errors.value = Object.fromEntries(
                Object.entries(data.errors ?? {}).map(([key, value]) => [key, value?.[0] ?? 'Invalid value'])
            );
            return;
        }

        if (!response.ok) {
            throw new Error('Failed to save subscription');
        }

        addToast(isEdit.value ? 'Subscription updated successfully.' : 'Subscription saved successfully.', 'success');
        router.push('/subscriptions');
    } catch {
        addToast("Something went wrong. Your changes weren't saved. Please try again.", 'error');
    } finally {
        saving.value = false;
    }
}

async function handleDelete() {
    deleting.value = true;

    try {
        const response = await fetch(`/api/v1/subscriptions/${route.params.id}`, {
            method: 'DELETE',
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to delete subscription');
        }

        addToast('Subscription deleted.', 'success');
        showDeleteModal.value = false;
        router.push('/subscriptions');
    } catch {
        addToast('Could not delete subscription. Please try again.', 'error');
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ isEdit ? 'Edit Subscription' : 'Add Subscription' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Use the same renewal, payment source, category, and automation settings available in the backend.</p>
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
                        <FormSelect
                            label="Frequency *"
                            v-model="form.subscription_frequency_id"
                            :options="frequencyOptions"
                            placeholder="Select frequency"
                            :error="errors.subscription_frequency_id"
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Charge</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            :label="amountLabel"
                            v-model="form.billing_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            :helper="amountHelper"
                            :error="errors.billing_amount"
                        />
                        <FormInput
                            label="Day of month *"
                            v-model="form.day_of_month"
                            type="number"
                            min="1"
                            max="31"
                            step="1"
                            helper="Day of month for renewal."
                            :error="errors.day_of_month"
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Renewal</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <FormInput
                            label="Next renewal date *"
                            v-model="form.next_renewal_date"
                            type="date"
                            helper="Next scheduled renewal."
                            :error="errors.next_renewal_date"
                        />
                        <FormSelect label="Status *" v-model="form.status" :options="statusOptions" :error="errors.status" />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Payment Source &amp; Category</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <FormSelect
                            label="Account"
                            v-model="form.account_id"
                            :options="accountOptions"
                            placeholder="Select account"
                            :error="errors.account_id"
                            helper="Use an account for direct debit."
                        />
                        <FormSelect
                            label="Credit card"
                            v-model="form.credit_card_id"
                            :options="creditCardOptions"
                            placeholder="Select credit card"
                            :error="errors.credit_card_id"
                            helper="Use a credit card to create card expenses automatically."
                        />
                        <FormSelect
                            label="Category"
                            v-model="form.category_id"
                            :options="categoryOptions"
                            placeholder="No category"
                            :error="errors.category_id"
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Settings</h2>
                    <div class="mt-4 flex items-center gap-3">
                        <input
                            id="auto_tx"
                            v-model="form.auto_create_transaction"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 bg-white text-amber-500 focus:ring-amber-400"
                        >
                        <label for="auto_tx" class="text-sm font-medium text-gray-700">Auto-create renewal entry on due date</label>
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
