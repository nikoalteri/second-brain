<script setup>
import AppNavbar from './AppNavbar.vue';
import { useToast } from '@/composables/useToast.js';

const { toasts, removeToast } = useToast();
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <AppNavbar />

        <main class="pt-14">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>

        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            <transition-group name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="flex min-w-[260px] items-center gap-3 rounded-xl px-4 py-3 text-sm shadow-lg"
                    :class="toast.type === 'success'
                        ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border border-red-200 bg-red-50 text-red-700'"
                >
                    <span class="flex-1">{{ toast.message }}</span>
                    <button class="shrink-0 opacity-70 hover:opacity-100" @click="removeToast(toast.id)">✕</button>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(1rem);
}
</style>
