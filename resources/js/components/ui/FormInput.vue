<script setup>
defineProps({
    label: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    placeholder: String,
    error: String,
    disabled: Boolean,
    required: Boolean,
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label v-if="label" class="text-sm font-normal text-gray-300">
            {{ label }}<span v-if="required" class="ml-1 text-red-400">*</span>
        </label>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            class="h-10 w-full rounded-lg border bg-gray-900 px-3 text-base text-gray-100 placeholder:text-gray-500 transition-colors duration-150 focus:outline-none focus:ring-1 disabled:cursor-not-allowed disabled:border-gray-800 disabled:bg-gray-800 disabled:text-gray-600"
            :class="error
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                : 'border-gray-700 focus:border-blue-500 focus:ring-blue-500'"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
    </div>
</template>
