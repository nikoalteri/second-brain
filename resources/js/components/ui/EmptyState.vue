<template>
    <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
        <div v-if="icon" class="mb-4 h-12 w-12 text-gray-400">
            <component :is="icon" class="h-12 w-12" />
        </div>
        <h3 class="mb-1 text-xl font-semibold text-gray-900">{{ title }}</h3>
        <p v-if="message" class="mb-6 max-w-xs text-sm text-gray-500">{{ message }}</p>
        <RouterLink
            v-if="actionLabel && actionTo"
            :to="actionTo"
            class="flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-white"
        >
            {{ actionLabel }}
        </RouterLink>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    message: { type: String, default: '' },
    icon: { type: Object, default: null },
    actionLabel: { type: String, default: null },
    actionTo: { type: String, default: null },
});
</script>
