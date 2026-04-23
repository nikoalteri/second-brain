<script setup>
import { computed, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { BanknotesIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useLocalizedLabels } from '@/composables/useLocalizedLabels.js';

const router = useRouter();
const { t } = useI18n();
const { formatCurrency } = useCurrency();
const { translateAccountType } = useLocalizedLabels();
const page = ref(1);

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

const { result, loading } = useQuery(ACCOUNTS_QUERY, () => ({ page: page.value }));
const accounts = computed(() => result.value?.accounts?.data ?? []);
const paginator = computed(() => result.value?.accounts?.paginatorInfo);
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Accounts</h1>
            </div>
            <router-link
                to="/accounts/new"
                class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm text-gray-900 transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white"
            >
                Add account
            </router-link>
        </div>

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
                            <span class="text-sm text-gray-500">{{ translateAccountType(account.type) }}</span>
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
