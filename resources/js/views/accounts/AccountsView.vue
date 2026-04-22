<script setup>
import { computed, ref } from 'vue';
import { useQuery } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import { BanknotesIcon } from '@heroicons/vue/24/outline';
import { useRouter } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';

const router = useRouter();
const { formatCurrency } = useCurrency();
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
                <h1 class="text-xl font-semibold text-white">Accounts</h1>
                <p class="mt-1 text-sm text-gray-400">All your bank accounts</p>
            </div>
            <router-link
                to="/accounts/new"
                class="flex h-10 items-center rounded-lg bg-blue-600 px-4 text-sm text-white transition-colors duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-950"
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
                    class="cursor-pointer rounded-xl border border-gray-700 bg-gray-800 p-4 transition-colors duration-150 hover:border-gray-600"
                    @click="router.push(`/accounts/${account.id}`)"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h3 class="text-base font-normal text-white">{{ account.name }}</h3>
                            <span class="capitalize text-sm text-gray-400">{{ account.type }}</span>
                        </div>
                        <span
                            class="rounded px-2 py-0.5 text-sm"
                            :class="account.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400'"
                        >
                            {{ account.is_active ? 'Active' : 'Inactive' }}
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
                class="mt-6 flex items-center justify-between border-t border-gray-700 pt-4"
            >
                <p class="text-sm text-gray-500">Page {{ paginator.currentPage }} of {{ paginator.lastPage }}</p>
                <div class="flex gap-2">
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page <= 1"
                        @click="page--"
                    >
                        Prev
                    </button>
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
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
