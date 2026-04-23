<script setup>
import { computed, ref } from 'vue';
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useToast } from '@/composables/useToast.js';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

const auth = useAuthStore();
const { addToast } = useToast();
const { settings } = useUserPreferences();
const { t } = useI18n();
const saving = ref(false);
const saveMessage = ref('');
const settingsForm = ref({ ...settings.value });

const previewRows = computed(() => [
    {
        label: t('settings.fields.theme'),
        description: t(`settings.previewRows.theme.${settingsForm.value.theme}`),
    },
    {
        label: t('settings.fields.language'),
        description: t(`settings.previewRows.language.${settingsForm.value.language}`),
    },
    {
        label: t('settings.fields.notifications'),
        description: t(`settings.previewRows.notifications.${settingsForm.value.notifications}`),
    },
    {
        label: t('settings.fields.privacy'),
        description: t(`settings.previewRows.privacy.${settingsForm.value.privacy}`),
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
            addToast(t('settings.feedback.saveError'), 'error');
            return;
        }

        auth.setUser(data.user ?? auth.user);
        settingsForm.value = { ...(data.user?.settings ?? settings.value) };
        saveMessage.value = t('settings.feedback.saved');
        addToast(t('settings.feedback.updated'), 'success');
    } catch {
        addToast(t('settings.feedback.saveError'), 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">{{ t('settings.title') }}</h1>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <Cog6ToothIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ t('settings.preferences') }}</h2>
                    </div>
                </div>

                <div class="mt-6 space-y-5">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('settings.fields.theme') }}</span>
                        <select
                            v-model="settingsForm.theme"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="light">{{ t('settings.options.theme.light') }}</option>
                            <option value="dark">{{ t('settings.options.theme.dark') }}</option>
                            <option value="system">{{ t('settings.options.theme.system') }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('settings.fields.language') }}</span>
                        <select
                            v-model="settingsForm.language"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="en">{{ t('settings.options.language.en') }}</option>
                            <option value="it">{{ t('settings.options.language.it') }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('settings.fields.notifications') }}</span>
                        <select
                            v-model="settingsForm.notifications"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="all">{{ t('settings.options.notifications.all') }}</option>
                            <option value="important_only">{{ t('settings.options.notifications.important_only') }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('settings.fields.privacy') }}</span>
                        <select
                            v-model="settingsForm.privacy"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="visible">{{ t('settings.options.privacy.visible') }}</option>
                            <option value="private">{{ t('settings.options.privacy.private') }}</option>
                        </select>
                    </label>
                </div>

                <button
                    type="button"
                    class="mt-6 inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                    :disabled="saving"
                    @click="saveSettings"
                >
                    {{ saving ? t('settings.actions.saving') : t('settings.actions.save') }}
                </button>
                <p v-if="saveMessage" class="mt-3 text-sm text-emerald-600">{{ saveMessage }}</p>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">{{ t('settings.preview') }}</h2>

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
