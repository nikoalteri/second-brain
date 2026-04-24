<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    open: Boolean,
    title: { type: String, default: null },
    message: { type: String, default: null },
    confirmLabel: { type: String, default: null },
    loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="$emit('cancel')"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">{{ title ?? t('common.confirm.title') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ message ?? t('common.confirm.message') }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-amber-300"
                        @click="$emit('cancel')"
                    >
                        {{ t('common.actions.cancel') }}
                    </button>
                    <button
                        class="h-10 rounded-lg bg-red-600 px-4 text-sm text-white transition-colors duration-150 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loading"
                        @click="$emit('confirm')"
                    >
                        {{ loading ? t('common.actions.deleting') : (confirmLabel ?? t('common.actions.confirm')) }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
