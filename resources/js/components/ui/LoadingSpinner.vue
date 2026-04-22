<script setup>
defineProps({
    fullPage: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
});

const sizeMap = { sm: 'h-6 w-6', md: 'h-8 w-8', lg: 'h-12 w-12' };
</script>

<template>
    <div :class="fullPage ? 'absolute inset-0 flex items-center justify-center bg-gray-950' : 'flex items-center justify-center'">
        <svg
            :class="[sizeMap[size] || sizeMap.md, 'animate-spin text-blue-500']"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-label="Loading"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
    </div>
</template>
