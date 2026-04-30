<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ArrowTopRightOnSquareIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/components/layout/AppLayout.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { defaultPhoneCountryCode, phoneCountryCodeOptions } from '@/constants/phoneCountryCodes.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const { profileIsPrivate } = useUserPreferences();

const user = computed(() => auth.user ?? {});
const roles = computed(() => user.value.roles ?? []);
const emailDisplay = computed(() => profileIsPrivate.value ? 'Hidden by privacy setting' : (user.value.email ?? 'No email available'));
const userIdDisplay = computed(() => profileIsPrivate.value ? 'Hidden by privacy setting' : (user.value.id ?? '—'));
const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone_country_code: defaultPhoneCountryCode,
    phone_number: '',
    date_of_birth: '',
    tax_code: '',
});
const saveMessage = ref('');

auth.clearFeedback();

watch(user, (value) => {
    form.first_name = value.first_name ?? '';
    form.last_name = value.last_name ?? '';
    form.email = value.email ?? '';
    form.phone_country_code = value.phone_country_code ?? defaultPhoneCountryCode;
    form.phone_number = value.phone_number ?? '';
    form.date_of_birth = value.date_of_birth ?? '';
    form.tax_code = value.tax_code ?? '';
}, { immediate: true });

const initials = computed(() => {
    const name = user.value.name?.trim();

    if (!name) {
        return 'U';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

async function handleSubmit() {
    const ok = await auth.updateProfile(form);

    saveMessage.value = ok ? 'Profile updated successfully.' : '';
}

</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">User details</h1>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-100 text-2xl font-semibold text-amber-900">
                        {{ initials }}
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ user.name ?? 'User' }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ emailDisplay }}</p>
                    </div>
                </div>

                <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">User ID</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-900">{{ userIdDisplay }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Frontend access</dt>
                        <dd class="mt-2 text-sm font-medium text-emerald-700">Enabled</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-900">{{ user.phone || 'Not set' }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tax code</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-900">{{ user.tax_code || 'Not set' }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Date of birth</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-900">{{ user.date_of_birth || 'Not set' }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Roles</dt>
                        <dd class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="role in roles"
                                :key="role"
                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-sm font-medium text-amber-900"
                            >
                                {{ role }}
                            </span>
                            <span
                                v-if="!roles.length"
                                class="rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-500"
                            >
                                No roles assigned
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="mt-8 border-t border-gray-200 pt-8">
                    <h3 class="text-base font-semibold text-gray-900">Edit profile</h3>
                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="handleSubmit">
                        <div class="flex flex-col gap-1">
                            <label for="first_name" class="text-sm font-normal text-gray-700">First name</label>
                            <input id="first_name" v-model="form.first_name" type="text" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="last_name" class="text-sm font-normal text-gray-700">Last name</label>
                            <input id="last_name" v-model="form.last_name" type="text" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div class="flex flex-col gap-1 sm:col-span-2">
                            <label for="email" class="text-sm font-normal text-gray-700">Email</label>
                            <input id="email" v-model="form.email" type="email" required class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:col-span-2">
                            <FormSelect
                                label="Phone prefix"
                                v-model="form.phone_country_code"
                                :options="phoneCountryCodeOptions"
                            />
                            <div class="flex flex-col gap-1">
                                <label for="phone_number" class="text-sm font-normal text-gray-700">Phone</label>
                                <input id="phone_number" v-model="form.phone_number" type="tel" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="date_of_birth" class="text-sm font-normal text-gray-700">Date of birth</label>
                            <input id="date_of_birth" v-model="form.date_of_birth" type="date" class="h-10 rounded-lg border border-gray-200 bg-white px-3 text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>
                        <div class="flex flex-col gap-1 sm:col-span-2">
                            <label for="tax_code" class="text-sm font-normal text-gray-700">Tax code</label>
                            <input id="tax_code" v-model="form.tax_code" type="text" maxlength="16" class="h-10 rounded-lg border border-gray-200 bg-white px-3 uppercase text-base text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        </div>

                        <p v-if="auth.error" class="text-sm text-red-500 sm:col-span-2">{{ auth.error }}</p>
                        <p v-if="saveMessage" class="text-sm text-emerald-600 sm:col-span-2">{{ saveMessage }}</p>

                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="auth.loading"
                                class="flex h-10 items-center justify-center rounded-lg bg-amber-500 px-4 text-sm text-white transition-colors duration-150 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ auth.loading ? 'Saving…' : 'Save profile' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <UserCircleIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Quick actions</h2>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <a
                            v-if="auth.isAdmin"
                            href="/admin"
                            class="flex items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100"
                        >
                            <span>Open Admin</span>
                            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                        </a>

                        <router-link
                            to="/dashboard"
                            class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900"
                        >
                            <span>Back to dashboard</span>
                            <span aria-hidden="true">→</span>
                        </router-link>

                        <router-link
                            to="/settings"
                            class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900"
                        >
                            <span>Open settings</span>
                            <span aria-hidden="true">→</span>
                        </router-link>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
