<script setup>
const props = defineProps({
    widthClass: {
        type: String,
        default: 'max-w-lg',
    },
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div
            class="w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm sm:p-10"
            :class="props.widthClass"
        >
            <slot />
        </div>
    </div>
</template>
