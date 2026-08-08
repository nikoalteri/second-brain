<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '@/components/layout/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const twoFactorStep = ref(false);
const code = ref('');

auth.clearFeedback();

async function handleSubmit() {
    const result = await auth.login(email.value, password.value);

    if (result === 'two_factor_required') {
        twoFactorStep.value = true;
        return;
    }

    if (result) {
        router.push(typeof route.query.redirect === 'string' ? route.query.redirect : '/home');
    }
}

async function handleVerifyTwoFactor() {
    const ok = await auth.verifyTwoFactor(code.value);

    if (ok) {
        router.push(typeof route.query.redirect === 'string' ? route.query.redirect : '/home');
    }
}

function backToLogin() {
    twoFactorStep.value = false;
    code.value = '';
    auth.clearFeedback();
}
</script>

<template>
    <AuthLayout width-class="max-w-lg">
        <template v-if="!twoFactorStep">
            <h1 class="mb-6 text-xl font-semibold text-gray-900">Sign in to Fluxa</h1>
            <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
                <div class="flex flex-col gap-1">
                    <label for="email" class="text-sm font-normal text-gray-700">Email</label>
                    <input
                        id="email"
                        v-model="email"
                        type="email"
                        required
                        placeholder="you@example.com"
                        class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 placeholder:text-gray-500 transition-colors duration-150 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>

                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-normal text-gray-700">Password</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 placeholder:text-gray-500 transition-colors duration-150 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>

                <p v-if="auth.error" class="text-center text-sm text-red-400">{{ auth.error }}</p>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50 hover:bg-amber-600"
                >
                    <svg
                        v-if="auth.loading"
                        class="h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    {{ auth.loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <div class="mt-6 space-y-2 text-center text-sm">
                <router-link to="/forgot-password" class="font-medium text-amber-700 hover:text-amber-800">
                    Forgot your password?
                </router-link>
                <p class="text-gray-500">
                    New here?
                    <router-link to="/register" class="font-medium text-amber-700 hover:text-amber-800">
                        Create an account
                    </router-link>
                </p>
            </div>
        </template>

        <template v-else>
            <h1 class="mb-2 text-xl font-semibold text-gray-900">Two-factor authentication</h1>
            <p class="mb-6 text-sm text-gray-500">
                Enter the 6-digit code from your authenticator app, or one of your recovery codes.
            </p>
            <form class="flex flex-col gap-4" @submit.prevent="handleVerifyTwoFactor">
                <div class="flex flex-col gap-1">
                    <label for="code" class="text-sm font-normal text-gray-700">Code</label>
                    <input
                        id="code"
                        v-model="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        required
                        placeholder="123456"
                        class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-center text-lg tracking-widest text-gray-900 placeholder:text-gray-400 transition-colors duration-150 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                    >
                </div>

                <p v-if="auth.error" class="text-center text-sm text-red-400">{{ auth.error }}</p>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="flex h-10 w-full items-center justify-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ auth.loading ? 'Verifying…' : 'Verify' }}
                </button>
                <button
                    type="button"
                    class="text-sm font-medium text-gray-500 hover:text-gray-700"
                    @click="backToLogin"
                >
                    Back to sign in
                </button>
            </form>
        </template>
    </AuthLayout>
</template>
