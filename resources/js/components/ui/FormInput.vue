<script setup>
defineProps({
    label: String,
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    placeholder: String,
    error: String,
    helper: String,
    disabled: Boolean,
    required: Boolean,
    readonly: Boolean,
    min: [String, Number],
    max: [String, Number],
    step: [String, Number],
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label v-if="label" class="text-sm font-medium text-gray-700">
            {{ label }}<span v-if="required" class="ml-1 text-red-400">*</span>
        </label>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :readonly="readonly"
            :min="min"
            :max="max"
            :step="step"
            class="h-10 w-full rounded-lg border bg-white px-3 text-base text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:outline-none focus:ring-1 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500 read-only:border-gray-200 read-only:bg-gray-50 read-only:text-gray-600"
            :class="error
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                : 'border-gray-300 focus:border-amber-500 focus:ring-amber-500'"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <p v-if="helper && !error" class="text-sm text-gray-500">{{ helper }}</p>
        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
    </div>
</template>
