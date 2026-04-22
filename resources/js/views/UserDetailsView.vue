<script setup>
import { computed } from 'vue';
import { ArrowTopRightOnSquareIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();

const user = computed(() => auth.user ?? {});
const roles = computed(() => user.value.roles ?? []);
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
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">User details</h1>
            <p class="mt-1 text-sm text-gray-500">Your profile information and session shortcuts.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-amber-100 text-2xl font-semibold text-amber-900">
                        {{ initials }}
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ user.name ?? 'User' }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ user.email ?? 'No email available' }}</p>
                    </div>
                </div>

                <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">User ID</dt>
                        <dd class="mt-2 text-sm font-medium text-gray-900">{{ user.id ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Frontend access</dt>
                        <dd class="mt-2 text-sm font-medium text-emerald-700">Enabled</dd>
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
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <UserCircleIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Quick actions</h2>
                            <p class="mt-1 text-sm text-gray-500">Shortcuts for your current session.</p>
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
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
