<script setup>
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthLayout from '@/components/layout/AuthLayout.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import { defaultPhoneCountryCode, phoneCountryCodeOptions } from '@/constants/phoneCountryCodes.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone_country_code: defaultPhoneCountryCode,
    phone_number: '',
    date_of_birth: '',
    tax_code: '',
    password: '',
    password_confirmation: '',
});

auth.clearFeedback();

async function handleSubmit() {
    const ok = await auth.register(form);

    if (ok) {
        router.push(typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard');
    }
}
</script>

<template>
    <AuthLayout width-class="max-w-3xl">
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Create your Fluxa account</h1>
        <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="first_name" class="text-sm font-normal text-gray-700">First name</label>
                    <input id="first_name" v-model="form.first_name" type="text" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="last_name" class="text-sm font-normal text-gray-700">Last name</label>
                    <input id="last_name" v-model="form.last_name" type="text" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-normal text-gray-700">Email</label>
                <input id="email" v-model="form.email" type="email" required placeholder="you@example.com" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-4 sm:col-span-1 sm:grid-cols-[130px_minmax(0,1fr)]">
                    <FormSelect
                        label="Phone prefix"
                        v-model="form.phone_country_code"
                        :options="phoneCountryCodeOptions"
                    />
                    <div class="flex flex-col gap-1">
                        <label for="phone_number" class="text-sm font-normal text-gray-700">Phone (optional)</label>
                        <input id="phone_number" v-model="form.phone_number" type="tel" placeholder="123456789" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="date_of_birth" class="text-sm font-normal text-gray-700">Date of birth (optional)</label>
                    <input id="date_of_birth" v-model="form.date_of_birth" type="date" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label for="tax_code" class="text-sm font-normal text-gray-700">Tax code (optional)</label>
                <input id="tax_code" v-model="form.tax_code" type="text" maxlength="16" placeholder="RSSMRA80A01H501U" class="h-10 rounded-lg border border-gray-200 bg-white px-3 uppercase text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="password" class="text-sm font-normal text-gray-700">Password</label>
                    <input id="password" v-model="form.password" type="password" required placeholder="••••••••" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
                <div class="flex flex-col gap-1">
                    <label for="password_confirmation" class="text-sm font-normal text-gray-700">Confirm password</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" required placeholder="••••••••" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>
            </div>

            <p v-if="auth.error" class="text-center text-sm text-red-400">{{ auth.error }}</p>

            <button
                type="submit"
                :disabled="auth.loading"
                class="flex h-10 w-full items-center justify-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ auth.loading ? 'Creating account…' : 'Create account' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            Already have an account?
            <router-link to="/login" class="font-medium text-amber-700 hover:text-amber-800">
                Sign in
            </router-link>
        </p>
    </AuthLayout>
</template>
