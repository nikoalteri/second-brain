<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from 'vue-i18n';
import EmptyState from './EmptyState.vue';
import LoadingSpinner from './LoadingSpinner.vue';

const { t } = useI18n();

defineProps({
    loading: { type: Boolean, default: false },
    error: { type: Object, default: null },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: null },
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
            class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4"
        >
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
            <div class="flex-1">
                <p class="text-sm font-normal text-red-400">{{ t('common.errors.loadDataTitle') }}</p>
                <p class="mt-0.5 text-sm text-red-400/70">{{ t('common.errors.loadDataBody') }}</p>
            </div>
        </div>

        <div v-else-if="loading" class="flex items-center justify-center py-16 text-gray-500">
            <LoadingSpinner size="sm" />
            <span class="ml-2 text-sm">{{ t('common.loading') }}</span>
        </div>

        <EmptyState
            v-else-if="empty"
            :title="emptyTitle ?? t('common.emptyTitle')"
            :message="emptyMessage"
            :icon="icon || emptyIcon"
            :action-label="actionLabel"
            :action-to="actionTo"
        />

        <template v-else>
            <div class="hidden overflow-x-auto sm:block">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <slot name="thead" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <slot name="tbody" />
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2 sm:hidden">
                <slot name="mobile" />
            </div>

            <div
                v-if="lastPage > 1"
                class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4"
            >
                <p class="text-sm text-gray-500">
                    {{ t('common.pagination.showing', { from: (currentPage - 1) * perPage + 1, to: Math.min(currentPage * perPage, total), total }) }}
                </p>
                <div class="flex gap-2">
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="currentPage <= 1"
                        @click="$emit('page-change', currentPage - 1)"
                    >
                        {{ t('common.pagination.prev') }}
                    </button>
                    <button
                        class="h-9 rounded-lg px-3 text-sm text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="currentPage >= lastPage"
                        @click="$emit('page-change', currentPage + 1)"
                    >
                        {{ t('common.pagination.next') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
