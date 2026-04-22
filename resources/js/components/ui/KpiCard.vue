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
    <div class="flex min-h-[100px] flex-col gap-2 rounded-xl border border-gray-700 bg-gray-800 p-6">
        <p class="text-sm font-normal uppercase tracking-wide text-gray-400">{{ label }}</p>
        <p class="font-mono text-3xl font-semibold" :class="colorMap[props.color] || 'text-gray-100'">
            {{ value }}
        </p>
        <p v-if="delta" class="text-sm font-normal text-gray-400">{{ delta }}</p>
    </div>
</template>
