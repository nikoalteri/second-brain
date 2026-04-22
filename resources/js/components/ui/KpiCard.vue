<script setup>
const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    color: { type: String, default: 'blue' },
    delta: { type: String, default: null },
});

const colorMap = {
    blue: 'text-blue-400',
    emerald: 'text-emerald-400',
    red: 'text-red-400',
    amber: 'text-amber-400',
    purple: 'text-purple-400',
};
</script>

<template>
    <div class="flex min-h-[100px] flex-col gap-2 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium uppercase tracking-wide text-gray-500">{{ label }}</p>
        <p class="font-mono text-3xl font-semibold" :class="colorMap[props.color] || 'text-gray-900'">
            {{ value }}
        </p>
        <p v-if="delta" class="text-sm font-normal text-gray-500">{{ delta }}</p>
    </div>
</template>
