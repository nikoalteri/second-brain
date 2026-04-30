<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '@/components/layout/AuthLayout.vue';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const successMessage = ref('');
const form = reactive({
    email: typeof route.query.email === 'string' ? route.query.email : '',
    token: typeof route.query.token === 'string' ? route.query.token : '',
    password: '',
    password_confirmation: '',
});
const hasResetContext = computed(() => !!form.email && !!form.token);

auth.clearFeedback();

async function handleSubmit() {
    const result = await auth.resetPassword(form);

    if (result.ok) {
        successMessage.value = result.message;
        window.setTimeout(() => {
            router.push('/login');
        }, 1200);
    }
}
</script>

<template>
    <AuthLayout>
        <h1 class="mb-3 text-xl font-semibold text-gray-900">Choose a new password</h1>
        <p class="mb-6 text-sm text-gray-500">Use the reset link you received by email to complete the password change.</p>

        <p v-if="!hasResetContext" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            This reset link is incomplete. Open the link from your email again.
        </p>

        <form v-else class="flex flex-col gap-4" @submit.prevent="handleSubmit">
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-normal text-gray-700">Email</label>
                <input id="email" v-model="form.email" type="email" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-normal text-gray-700">New password</label>
                <input id="password" v-model="form.password" type="password" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="text-sm font-normal text-gray-700">Confirm new password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <p v-if="auth.error" class="text-center text-sm text-red-400">{{ auth.error }}</p>
            <p v-if="successMessage" class="text-center text-sm text-emerald-600">{{ successMessage }}</p>

            <button
                type="submit"
                :disabled="auth.loading"
                class="flex h-10 w-full items-center justify-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ auth.loading ? 'Resetting password…' : 'Reset password' }}
            </button>
        </form>
    </AuthLayout>
</template>
