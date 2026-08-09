<script setup>
import { computed, ref, watch } from 'vue';
import { EyeSlashIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import { useToast } from '@/composables/useToast.js';
import { useVaultStore } from '@/stores/vault.js';

const props = defineProps({
    title: { type: String, default: 'CVV / PIN' },
    revealUrl: { type: String, required: true },
    updateUrl: { type: String, required: true },
    // 'visa' | 'mastercard' | 'amex' — Amex has a 4-digit CVV plus a separate 3-digit security code.
    brand: { type: String, default: null },
});

const vault = useVaultStore();
const { addToast } = useToast();

const isAmex = computed(() => props.brand === 'amex');

const hasVaultPin = ref(null);
const checkingPin = ref(false);

const pinPassword = ref('');
const newPin = ref('');
const settingPin = ref(false);

const enteredPin = ref('');
const revealing = ref(false);
const revealed = ref(false);
const sensitive = ref({ cvv: '', pin: '', security_code: '' });

const editing = ref(false);
const saving = ref(false);
const editForm = ref({ cvv: '', pin: '', security_code: '' });

async function checkVaultPin() {
    checkingPin.value = true;

    try {
        const response = await fetch('/api/v1/vault/pin', { headers: vault.headers() });
        const data = await response.json();
        hasVaultPin.value = !!data.has_vault_pin;
    } catch {
        hasVaultPin.value = null;
    } finally {
        checkingPin.value = false;
    }
}

watch(
    () => vault.isUnlocked,
    (unlocked) => {
        if (unlocked && hasVaultPin.value === null) {
            void checkVaultPin();
        }
    },
    { immediate: true }
);

async function handleSetPin() {
    settingPin.value = true;

    try {
        const response = await fetch('/api/v1/vault/pin', {
            method: 'POST',
            headers: vault.headers(true),
            body: JSON.stringify({ password: pinPassword.value, pin: newPin.value }),
        });

        if (!response.ok) {
            const body = await response.json();
            addToast(body.message || 'Could not set the vault PIN.', 'error');
            return;
        }

        pinPassword.value = '';
        newPin.value = '';
        hasVaultPin.value = true;
        addToast('Vault PIN set.', 'success');
    } catch {
        addToast('Network error. Please try again.', 'error');
    } finally {
        settingPin.value = false;
    }
}

async function handleReveal() {
    revealing.value = true;

    try {
        const response = await fetch(props.revealUrl, {
            method: 'POST',
            headers: vault.headers(true),
            body: JSON.stringify({ vault_pin: enteredPin.value }),
        });

        if (response.status === 403) {
            vault.lock();
            addToast('Vault session expired. Unlock it again.', 'error');
            return;
        }

        if (response.status === 429) {
            addToast('Too many incorrect attempts. Try again in a few minutes.', 'error');
            return;
        }

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            addToast(body.message || 'Incorrect vault PIN.', 'error');
            return;
        }

        const { data } = await response.json();
        sensitive.value = { cvv: data.cvv ?? '', pin: data.pin ?? '', security_code: data.security_code ?? '' };
        revealed.value = true;
        enteredPin.value = '';
    } catch {
        addToast('Network error. Please try again.', 'error');
    } finally {
        revealing.value = false;
    }
}

function startEditing() {
    editForm.value = { cvv: sensitive.value.cvv, pin: sensitive.value.pin, security_code: sensitive.value.security_code };
    enteredPin.value = '';
    editing.value = true;
}

async function saveEditing() {
    saving.value = true;

    try {
        const payload = { vault_pin: enteredPin.value, cvv: editForm.value.cvv || null, pin: editForm.value.pin || null };

        if (isAmex.value) {
            payload.security_code = editForm.value.security_code || null;
        }

        const response = await fetch(props.updateUrl, {
            method: 'PUT',
            headers: vault.headers(true),
            body: JSON.stringify(payload),
        });

        if (response.status === 403) {
            vault.lock();
            addToast('Vault session expired. Unlock it again.', 'error');
            return;
        }

        if (response.status === 429) {
            addToast('Too many incorrect attempts. Try again in a few minutes.', 'error');
            return;
        }

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            const message = Object.values(body.errors ?? {}).flat().join(' ') || body.message || 'Invalid data.';
            addToast(message, 'error');
            return;
        }

        const { data } = await response.json();
        sensitive.value = { cvv: data.cvv ?? '', pin: data.pin ?? '', security_code: data.security_code ?? '' };
        revealed.value = true;
        editing.value = false;
        enteredPin.value = '';
        addToast('Updated.', 'success');
    } catch {
        addToast('Network error. Please try again.', 'error');
    } finally {
        saving.value = false;
    }
}

function mask(value) {
    if (!value) {
        return '—';
    }

    return '•'.repeat(String(value).length);
}
</script>

<template>
    <div v-if="vault.isUnlocked" class="mt-4 border-t border-gray-100 pt-4">
        <div class="mb-3 flex items-center gap-2">
            <LockClosedIcon class="h-4 w-4 text-gray-400" />
            <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
        </div>

        <div v-if="checkingPin" class="text-sm text-gray-500">Loading…</div>

        <div v-else-if="hasVaultPin === false" class="max-w-sm space-y-3">
            <p class="text-sm text-gray-500">Set a 4-digit vault PIN to protect CVV/PIN reveal and edits.</p>
            <input
                v-model="pinPassword"
                type="password"
                placeholder="Current password"
                class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
            >
            <input
                v-model="newPin"
                type="text"
                inputmode="numeric"
                maxlength="4"
                placeholder="4-digit PIN"
                class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
            >
            <button
                type="button"
                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                :disabled="settingPin || !pinPassword || newPin.length !== 4"
                @click="handleSetPin"
            >
                {{ settingPin ? 'Saving…' : 'Set vault PIN' }}
            </button>
        </div>

        <div v-else-if="!revealed && !editing" class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-sm font-medium text-gray-700">Vault PIN</span>
                <input
                    v-model="enteredPin"
                    type="password"
                    inputmode="numeric"
                    maxlength="4"
                    placeholder="••••"
                    class="mt-1 block w-28 rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
            </label>
            <button
                type="button"
                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                :disabled="revealing || enteredPin.length !== 4"
                @click="handleReveal"
            >
                {{ revealing ? 'Checking…' : 'Reveal' }}
            </button>
        </div>

        <div v-else-if="!editing" class="space-y-3">
            <div class="flex items-center justify-between border-b border-gray-100 py-2">
                <span class="text-sm text-gray-500">CVV</span>
                <span class="font-mono text-sm text-gray-900">{{ mask(sensitive.cvv) }}</span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-100 py-2">
                <span class="text-sm text-gray-500">PIN</span>
                <span class="font-mono text-sm text-gray-900">{{ mask(sensitive.pin) }}</span>
            </div>
            <div v-if="isAmex" class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                <span class="text-sm text-gray-500">Security code</span>
                <span class="font-mono text-sm text-gray-900">{{ mask(sensitive.security_code) }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                    @click="revealed = false"
                >
                    <EyeSlashIcon class="h-4 w-4" /> Hide
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="startEditing"
                >
                    Edit
                </button>
            </div>
        </div>

        <div v-else class="max-w-sm space-y-3">
            <label class="block">
                <span class="text-sm font-medium text-gray-700">CVV</span>
                <input v-model="editForm.cvv" type="text" inputmode="numeric" class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700">PIN</span>
                <input v-model="editForm.pin" type="text" inputmode="numeric" class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </label>
            <label v-if="isAmex" class="block">
                <span class="text-sm font-medium text-gray-700">Security code</span>
                <input v-model="editForm.security_code" type="text" inputmode="numeric" class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </label>
            <label class="block">
                <span class="text-sm font-medium text-gray-700">Vault PIN</span>
                <input v-model="enteredPin" type="password" inputmode="numeric" maxlength="4" placeholder="••••" class="mt-1 block w-28 rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
            </label>
            <div class="flex gap-3">
                <button
                    type="button"
                    class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                    :disabled="saving || enteredPin.length !== 4"
                    @click="saveEditing"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    @click="editing = false"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>
