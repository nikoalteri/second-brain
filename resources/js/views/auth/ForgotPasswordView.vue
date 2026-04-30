<script setup>
import { ref } from 'vue';
import AuthLayout from '@/components/layout/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const email = ref('');
const successMessage = ref('');

auth.clearFeedback();

async function handleSubmit() {
    const result = await auth.requestPasswordReset(email.value);

    if (result.ok) {
        successMessage.value = result.message;
    }
}
</script>

<template>
    <AuthLayout>
        <h1 class="mb-3 text-xl font-semibold text-gray-900">Reset your password</h1>
        <p class="mb-6 text-sm text-gray-500">Enter your email and we'll send you a reset link if the account exists.</p>

        <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-normal text-gray-700">Email</label>
                <input id="email" v-model="email" type="email" required placeholder="you@example.com" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <p v-if="auth.error" class="text-center text-sm text-red-400">{{ auth.error }}</p>
            <p v-if="successMessage" class="text-center text-sm text-emerald-600">{{ successMessage }}</p>

            <button
                type="submit"
                :disabled="auth.loading"
                class="flex h-10 w-full items-center justify-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ auth.loading ? 'Sending link…' : 'Send reset link' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            Remembered it?
            <router-link to="/login" class="font-medium text-amber-700 hover:text-amber-800">
                Back to sign in
            </router-link>
        </p>
    </AuthLayout>
</template>
