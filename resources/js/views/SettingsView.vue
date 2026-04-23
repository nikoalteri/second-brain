<script setup>
import { computed, ref } from 'vue';
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useToast } from '@/composables/useToast.js';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const { addToast } = useToast();
const { settings } = useUserPreferences();
const saving = ref(false);
const saveMessage = ref('');
const settingsForm = ref({ ...settings.value });

const previewRows = computed(() => [
    {
        label: 'Theme',
        description: settingsForm.value.theme === 'light'
            ? 'The SPA stays in light mode.'
            : settingsForm.value.theme === 'dark'
                ? 'The SPA stays in dark mode.'
                : 'The SPA follows your system light/dark preference.',
    },
    {
        label: 'Language',
        description: settingsForm.value.language === 'it'
            ? 'Months and currency use Italian formatting.'
            : 'Months and currency use English formatting.',
    },
    {
        label: 'Notifications',
        description: settingsForm.value.notifications === 'important_only'
            ? 'Success toasts are muted; errors still appear.'
            : 'Success and error toasts are both shown.',
    },
    {
        label: 'Privacy',
        description: settingsForm.value.privacy === 'private'
            ? 'Your profile hides email and user ID.'
            : 'Your profile shows email and user ID.',
    },
]);

async function saveSettings() {
    if (!auth.accessToken) {
        return;
    }

    saving.value = true;
    saveMessage.value = '';

    try {
        const response = await fetch('/api/v1/auth/settings', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${auth.accessToken}`,
            },
            body: JSON.stringify(settingsForm.value),
        });

        const data = await response.json();

        if (!response.ok) {
            addToast('Could not save your settings. Please try again.', 'error');
            return;
        }

        auth.setUser(data.user ?? auth.user);
        settingsForm.value = { ...(data.user?.settings ?? settings.value) };
        saveMessage.value = 'Settings saved.';
        addToast('Settings updated.', 'success');
    } catch {
        addToast('Could not save your settings. Please try again.', 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Settings</h1>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <Cog6ToothIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Preferences</h2>
                    </div>
                </div>

                <div class="mt-6 space-y-5">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Theme</span>
                        <select
                            v-model="settingsForm.theme"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                            <option value="system">System</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Language</span>
                        <select
                            v-model="settingsForm.language"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="en">English</option>
                            <option value="it">Italiano</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Notifications</span>
                        <select
                            v-model="settingsForm.notifications"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="all">All toasts</option>
                            <option value="important_only">Errors only</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Privacy</span>
                        <select
                            v-model="settingsForm.privacy"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="visible">Show profile details</option>
                            <option value="private">Hide email and user ID</option>
                        </select>
                    </label>
                </div>

                <button
                    type="button"
                    class="mt-6 inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                    :disabled="saving"
                    @click="saveSettings"
                >
                    {{ saving ? 'Saving...' : 'Save settings' }}
                </button>
                <p v-if="saveMessage" class="mt-3 text-sm text-emerald-600">{{ saveMessage }}</p>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Preview</h2>

                    <dl class="mt-4 space-y-4">
                        <div
                            v-for="row in previewRows"
                            :key="row.label"
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4"
                        >
                            <dt class="text-sm font-medium text-gray-900">{{ row.label }}</dt>
                            <dd class="mt-1 text-sm text-gray-500">{{ row.description }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
