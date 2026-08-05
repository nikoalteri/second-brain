<script setup>
defineProps({
    replies: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

defineEmits(['select']);
</script>

<template>
    <div v-if="replies.length > 0" class="flex flex-wrap gap-2">
        <button
            v-for="reply in replies"
            :key="reply.id"
            type="button"
            :disabled="disabled"
            class="h-11 rounded-full border border-gray-200 bg-gray-100 px-4 text-xs font-semibold text-gray-900 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
            @click="$emit('select', reply)"
        >
            {{ reply.label }}
        </button>
    </div>
</template>
