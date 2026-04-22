<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

defineProps({
    open: Boolean,
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: 'This action cannot be undone.' },
    confirmLabel: { type: String, default: 'Confirm' },
    loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="$emit('cancel')"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-700 bg-gray-800 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/10">
                        <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white">{{ title }}</h3>
                        <p class="mt-1 text-sm text-gray-400">{{ message }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="h-10 rounded-lg border border-gray-600 bg-gray-700 px-4 text-sm text-gray-100 transition-colors duration-150 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        @click="$emit('cancel')"
                    >
                        No, go back
                    </button>
                    <button
                        class="h-10 rounded-lg bg-red-600 px-4 text-sm text-white transition-colors duration-150 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loading"
                        @click="$emit('confirm')"
                    >
                        {{ loading ? 'Deleting…' : confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
