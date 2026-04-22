<script setup>
defineProps({
    label: String,
    modelValue: [String, Number, null],
    options: { type: Array, default: () => [] },
    placeholder: String,
    error: String,
    disabled: Boolean,
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label v-if="label" class="text-sm font-normal text-gray-300">{{ label }}</label>
        <div class="relative">
            <select
                :value="modelValue"
                :disabled="disabled"
                class="h-10 w-full cursor-pointer appearance-none rounded-lg border bg-gray-900 px-3 pr-10 text-base text-gray-100 transition-colors duration-150 focus:outline-none focus:ring-1 disabled:border-gray-800 disabled:bg-gray-800 disabled:text-gray-600"
                :class="error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-700 focus:border-blue-500 focus:ring-blue-500'"
                @change="$emit('update:modelValue', $event.target.value)"
            >
                <option v-if="placeholder" value="" disabled :selected="!modelValue">{{ placeholder }}</option>
                <option v-for="opt in options" :key="String(opt.value)" :value="opt.value">{{ opt.label }}</option>
            </select>
            <svg
                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
    </div>
</template>
