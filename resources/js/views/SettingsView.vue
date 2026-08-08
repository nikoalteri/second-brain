<script setup>
import { computed, ref } from 'vue';
import { Cog6ToothIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';
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

const twoFactorEnabled = ref(!!auth.user?.two_factor_enabled);
const twoFactorStage = ref('idle'); // idle | enrolling | recovery-codes | disabling | regenerating
const twoFactorPending = ref(false);
const twoFactorSecret = ref('');
const twoFactorOtpAuthUrl = ref('');
const twoFactorCode = ref('');
const twoFactorPassword = ref('');
const recoveryCodes = ref([]);

function authHeaders(includeJson = false) {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
    };
}

async function startEnableTwoFactor() {
    twoFactorPending.value = true;

    try {
        const response = await fetch('/api/v1/auth/two-factor/enable', {
            method: 'POST',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to start enrollment');
        }

        const data = await response.json();
        twoFactorSecret.value = data.secret;
        twoFactorOtpAuthUrl.value = data.otpauth_url;
        twoFactorStage.value = 'enrolling';
    } catch {
        addToast('Could not start two-factor setup. Please try again.', 'error');
    } finally {
        twoFactorPending.value = false;
    }
}

async function confirmTwoFactor() {
    twoFactorPending.value = true;

    try {
        const response = await fetch('/api/v1/auth/two-factor/confirm', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ code: twoFactorCode.value }),
        });

        if (!response.ok) {
            addToast('Invalid code. Please try again.', 'error');
            return;
        }

        const data = await response.json();
        recoveryCodes.value = data.recovery_codes;
        twoFactorStage.value = 'recovery-codes';
        twoFactorEnabled.value = true;
        twoFactorCode.value = '';
    } catch {
        addToast('Could not confirm two-factor authentication. Please try again.', 'error');
    } finally {
        twoFactorPending.value = false;
    }
}

function finishRecoveryCodesStep() {
    recoveryCodes.value = [];
    twoFactorStage.value = 'idle';
    addToast('Two-factor authentication enabled.', 'success');
}

function cancelTwoFactorFlow() {
    twoFactorStage.value = 'idle';
    twoFactorCode.value = '';
    twoFactorPassword.value = '';
}

async function disableTwoFactor() {
    twoFactorPending.value = true;

    try {
        const response = await fetch('/api/v1/auth/two-factor/disable', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ password: twoFactorPassword.value }),
        });

        if (!response.ok) {
            addToast('Incorrect password.', 'error');
            return;
        }

        twoFactorEnabled.value = false;
        twoFactorStage.value = 'idle';
        twoFactorPassword.value = '';
        addToast('Two-factor authentication disabled.', 'success');
    } catch {
        addToast('Could not disable two-factor authentication. Please try again.', 'error');
    } finally {
        twoFactorPending.value = false;
    }
}

async function regenerateRecoveryCodes() {
    twoFactorPending.value = true;

    try {
        const response = await fetch('/api/v1/auth/two-factor/recovery-codes', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ password: twoFactorPassword.value }),
        });

        if (!response.ok) {
            addToast('Incorrect password.', 'error');
            return;
        }

        const data = await response.json();
        recoveryCodes.value = data.recovery_codes;
        twoFactorPassword.value = '';
        twoFactorStage.value = 'recovery-codes';
    } catch {
        addToast('Could not regenerate recovery codes. Please try again.', 'error');
    } finally {
        twoFactorPending.value = false;
    }
}

const previewRows = computed(() => [
    {
        label: t('settings.fields.theme'),
        description: t(`settings.previewRows.theme.${settingsForm.value.theme}`),
    },
    {
        label: t('settings.fields.notifications'),
        description: t(`settings.previewRows.notifications.${settingsForm.value.notifications}`),
    },
    {
        label: t('settings.fields.privacy'),
        description: t(`settings.previewRows.privacy.${settingsForm.value.privacy}`),
    },
    {
        label: t('settings.fields.displayCurrency'),
        description: t(`settings.options.displayCurrency.${settingsForm.value.display_currency}`),
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
            <div class="space-y-6">
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

                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">{{ t('settings.fields.displayCurrency') }}</span>
                        <select
                            v-model="settingsForm.display_currency"
                            class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                        >
                            <option value="EUR">{{ t('settings.options.displayCurrency.EUR') }}</option>
                            <option value="CZK">{{ t('settings.options.displayCurrency.CZK') }}</option>
                            <option value="USD">{{ t('settings.options.displayCurrency.USD') }}</option>
                            <option value="GBP">{{ t('settings.options.displayCurrency.GBP') }}</option>
                            <option value="CHF">{{ t('settings.options.displayCurrency.CHF') }}</option>
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

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <ShieldCheckIcon class="mt-0.5 h-5 w-5 text-amber-600" />
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Two-factor authentication</h2>
                        <p class="text-sm text-gray-500">
                            Require a code from an authenticator app when signing in.
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <!-- Steady state: not enabled -->
                    <div v-if="twoFactorStage === 'idle' && !twoFactorEnabled">
                        <button
                            type="button"
                            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                            :disabled="twoFactorPending"
                            @click="startEnableTwoFactor"
                        >
                            Enable two-factor authentication
                        </button>
                    </div>

                    <!-- Steady state: enabled -->
                    <div v-else-if="twoFactorStage === 'idle' && twoFactorEnabled" class="space-y-3">
                        <p class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                            Two-factor authentication is enabled
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                @click="twoFactorStage = 'regenerating'"
                            >
                                Regenerate recovery codes
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                                @click="twoFactorStage = 'disabling'"
                            >
                                Disable
                            </button>
                        </div>
                    </div>

                    <!-- Enrolling: show secret, ask for a confirmation code -->
                    <div v-else-if="twoFactorStage === 'enrolling'" class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-700">
                                Scan this into your authenticator app, or enter the key manually:
                            </p>
                            <p class="mt-2 break-all rounded-lg bg-gray-100 px-3 py-2 font-mono text-sm text-gray-900">
                                {{ twoFactorSecret }}
                            </p>
                        </div>
                        <label class="block max-w-xs">
                            <span class="text-sm font-medium text-gray-700">Enter the 6-digit code to confirm</span>
                            <input
                                v-model="twoFactorCode"
                                type="text"
                                inputmode="numeric"
                                placeholder="123456"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-center text-lg tracking-widest text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            >
                        </label>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                                :disabled="twoFactorPending || !twoFactorCode"
                                @click="confirmTwoFactor"
                            >
                                Confirm
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                @click="cancelTwoFactorFlow"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Recovery codes: shown once after confirm or regenerate -->
                    <div v-else-if="twoFactorStage === 'recovery-codes'" class="space-y-4">
                        <p class="text-sm font-medium text-amber-700">
                            Save these recovery codes now — each works once, and this is the only time they're shown.
                        </p>
                        <div class="grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-4 font-mono text-sm text-gray-900">
                            <span v-for="recoveryCode in recoveryCodes" :key="recoveryCode">{{ recoveryCode }}</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700"
                            @click="finishRecoveryCodesStep"
                        >
                            I've saved these codes
                        </button>
                    </div>

                    <!-- Disable: password confirmation -->
                    <div v-else-if="twoFactorStage === 'disabling'" class="space-y-4">
                        <label class="block max-w-xs">
                            <span class="text-sm font-medium text-gray-700">Confirm your password to disable</span>
                            <input
                                v-model="twoFactorPassword"
                                type="password"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            >
                        </label>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-red-300"
                                :disabled="twoFactorPending || !twoFactorPassword"
                                @click="disableTwoFactor"
                            >
                                Disable
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                @click="cancelTwoFactorFlow"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Regenerate recovery codes: password confirmation -->
                    <div v-else-if="twoFactorStage === 'regenerating'" class="space-y-4">
                        <label class="block max-w-xs">
                            <span class="text-sm font-medium text-gray-700">Confirm your password</span>
                            <input
                                v-model="twoFactorPassword"
                                type="password"
                                class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                            >
                        </label>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                                :disabled="twoFactorPending || !twoFactorPassword"
                                @click="regenerateRecoveryCodes"
                            >
                                Regenerate
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                @click="cancelTwoFactorFlow"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </section>
            </div>

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
