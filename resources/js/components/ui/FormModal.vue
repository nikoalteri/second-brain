<script setup>
import { XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({
    open: Boolean,
    title: { type: String, default: '' },
});

defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="$emit('close')"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-amber-300"
                        @click="$emit('close')"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
