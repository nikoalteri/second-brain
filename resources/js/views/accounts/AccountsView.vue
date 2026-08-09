<script setup>
import { computed, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { ArrowsRightLeftIcon, BanknotesIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { accountTypeIcons } from '@/icons/domainIcons.js';
import { useLocalizedLabels } from '@/composables/useLocalizedLabels.js';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const { t } = useI18n();
const { formatCurrency } = useCurrency();
const { translateAccountType } = useLocalizedLabels();
const { addToast } = useToast();
const auth = useAuthStore();
const page = ref(1);
const showTransferForm = ref(false);
const submittingTransfer = ref(false);
const transferForm = ref({ from_account_id: '', to_account_id: '', amount: '', date: new Date().toISOString().slice(0, 10), description: '' });

const ACCOUNTS_QUERY = gql`
    query GetAccounts($page: Int) {
        accounts(first: 20, page: $page) {
            data {
                id
                name
                type
                balance
                currency
                is_active
            }
            paginatorInfo {
                currentPage
                lastPage
                total
            }
        }
    }
`;

const { result, loading, refetch } = useQuery(ACCOUNTS_QUERY, () => ({ page: page.value }));
const accounts = computed(() => result.value?.accounts?.data ?? []);
const paginator = computed(() => result.value?.accounts?.paginatorInfo);

function openTransferForm() {
    transferForm.value = {
        from_account_id: accounts.value[0]?.id ?? '',
        to_account_id: accounts.value[1]?.id ?? '',
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        description: '',
    };
    showTransferForm.value = true;
}

async function submitTransfer() {
    const amount = Number(transferForm.value.amount);

    if (!transferForm.value.from_account_id || !transferForm.value.to_account_id) {
        addToast('Choose both accounts.', 'error');
        return;
    }

    if (transferForm.value.from_account_id === transferForm.value.to_account_id) {
        addToast('Source and destination accounts must be different.', 'error');
        return;
    }

    if (Number.isNaN(amount) || amount <= 0) {
        addToast('Enter a valid amount.', 'error');
        return;
    }

    submittingTransfer.value = true;

    try {
        const response = await fetch('/api/v1/transfers', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                from_account_id: transferForm.value.from_account_id,
                to_account_id: transferForm.value.to_account_id,
                amount,
                date: transferForm.value.date,
                description: transferForm.value.description || undefined,
            }),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            const message = body?.errors ? Object.values(body.errors).flat().join(' ') : 'Could not complete the transfer.';
            addToast(message, 'error');
            return;
        }

        await refetch();
        showTransferForm.value = false;
        addToast('Transfer completed.', 'success');
    } catch {
        addToast('Could not complete the transfer. Please try again.', 'error');
    } finally {
        submittingTransfer.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Accounts</h1>
            </div>
            <div class="flex gap-2">
                <button
                    v-if="accounts.length >= 2"
                    type="button"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors duration-150 hover:bg-gray-50"
                    @click="openTransferForm"
                >
                    <ArrowsRightLeftIcon class="h-4 w-4" />
                    Transfer
                </button>
                <router-link
                    to="/accounts/new"
                    class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-gray-900 transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white"
                >
                    Add account
                </router-link>
            </div>
        </div>

        <section v-if="showTransferForm" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Transfer money</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">From</span>
                    <select
                        v-model="transferForm.from_account_id"
                        class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    >
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">To</span>
                    <select
                        v-model="transferForm.to_account_id"
                        class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    >
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Amount</span>
                    <input
                        v-model="transferForm.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    />
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Date</span>
                    <input
                        v-model="transferForm.date"
                        type="date"
                        class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    />
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-gray-700">Description (optional)</span>
                    <input
                        v-model="transferForm.description"
                        type="text"
                        class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    />
                </label>
            </div>
            <div class="mt-4 flex gap-3">
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                    :disabled="submittingTransfer"
                    @click="submitTransfer"
                >
                    {{ submittingTransfer ? 'Transferring…' : 'Transfer' }}
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="showTransferForm = false"
                >
                    Cancel
                </button>
            </div>
        </section>

        <LoadingSpinner v-if="loading" class="py-16" />

        <EmptyState
            v-else-if="!accounts.length"
            title="No accounts yet"
            message="Add your first account to start tracking your finances."
            :icon="BanknotesIcon"
            action-label="Add account"
            action-to="/accounts/new"
        />

        <template v-else>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 transition-colors duration-150 hover:border-gray-300"
                    @click="router.push(`/accounts/${account.id}`)"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-normal text-gray-900">{{ account.name }}</h3>
                            <span class="inline-flex items-center gap-1 text-sm text-gray-500">
                                <component :is="accountTypeIcons[account.type]" class="h-4 w-4" />
                                {{ translateAccountType(account.type) }}
                            </span>
                        </div>
                        <span
                            class="rounded px-2 py-0.5 text-sm"
                            :class="account.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-500'"
                        >
                            {{ account.is_active ? t('labels.status.active') : t('labels.status.inactive') }}
                        </span>
                    </div>

                    <p class="font-mono text-xl font-semibold text-blue-400">
                        {{ formatCurrency(account.balance, account.currency) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">{{ account.currency }}</p>
                </div>
            </div>

            <div
                v-if="paginator?.lastPage > 1"
                class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4"
            >
                <p class="text-sm text-gray-500">Page {{ paginator.currentPage }} of {{ paginator.lastPage }}</p>
                <div class="flex gap-2">
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-500 transition-colors hover:bg-white hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page <= 1"
                        @click="page--"
                    >
                        Prev
                    </button>
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-500 transition-colors hover:bg-white hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page >= paginator.lastPage"
                        @click="page++"
                    >
                        Next
                    </button>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
