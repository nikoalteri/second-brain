<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import EmptyState from './EmptyState.vue';
import LoadingSpinner from './LoadingSpinner.vue';

defineProps({
    loading: { type: Boolean, default: false },
    error: { type: Object, default: null },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'No items found' },
    emptyMessage: { type: String, default: '' },
    emptyIcon: { type: Object, default: null },
    icon: { type: Object, default: null },
    actionLabel: { type: String, default: null },
    actionTo: { type: String, default: null },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    total: { type: Number, default: 0 },
    perPage: { type: Number, default: 20 },
});

defineEmits(['page-change']);
</script>

<template>
    <div>
        <div
            v-if="error"
            class="mb-6 flex items-start gap-3 rounded-lg border border-red-500/20 bg-red-500/10 p-4"
        >
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
            <div class="flex-1">
                <p class="text-sm font-normal text-red-400">Couldn't load data</p>
                <p class="mt-0.5 text-sm text-red-400/70">Check your connection and try again.</p>
            </div>
        </div>

        <div v-else-if="loading" class="flex items-center justify-center py-16 text-gray-400">
            <LoadingSpinner size="sm" />
            <span class="ml-2 text-sm">Loading…</span>
        </div>

        <EmptyState
            v-else-if="empty"
            :title="emptyTitle"
            :message="emptyMessage"
            :icon="icon || emptyIcon"
            :action-label="actionLabel"
            :action-to="actionTo"
        />

        <template v-else>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <slot name="thead" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <slot name="tbody" />
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2 sm:hidden">
                <slot name="mobile" />
            </div>

            <div
                v-if="lastPage > 1"
                class="mt-4 flex items-center justify-between border-t border-gray-700 pt-4"
            >
                <p class="text-sm text-gray-500">
                    Showing {{ (currentPage - 1) * perPage + 1 }}–{{ Math.min(currentPage * perPage, total) }} of {{ total }}
                </p>
                <div class="flex gap-2">
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="currentPage <= 1"
                        @click="$emit('page-change', currentPage - 1)"
                    >
                        Prev
                    </button>
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="currentPage >= lastPage"
                        @click="$emit('page-change', currentPage + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
