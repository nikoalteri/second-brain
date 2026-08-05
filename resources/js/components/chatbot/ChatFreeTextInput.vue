<script setup>
import { ref } from 'vue';

defineProps({
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const value = ref('');

function onSubmit() {
    emit('submit', value.value.trim());
    value.value = '';
}
</script>

<template>
    <form class="flex items-center gap-2" @submit.prevent="onSubmit">
        <input
            v-model="value"
            type="text"
            :placeholder="placeholder"
            :disabled="disabled"
            class="h-11 flex-1 rounded-full border border-gray-200 bg-white px-4 text-sm font-normal text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-300"
        >
        <button
            type="submit"
            :disabled="disabled || value.trim() === ''"
            class="h-11 rounded-full bg-amber-500 px-4 text-xs font-semibold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Send
        </button>
    </form>
</template>
