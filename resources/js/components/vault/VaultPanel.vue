<script setup>
import { ref, watch } from 'vue';
import { EyeIcon, EyeSlashIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/24/outline';
import { useToast } from '@/composables/useToast.js';
import { useVaultStore } from '@/stores/vault.js';

const props = defineProps({
    title: { type: String, default: 'Vault' },
    fetchUrl: { type: String, required: true },
    updateUrl: { type: String, required: true },
    // Each: { key, label, type: 'text'|'number', maskChar }
    fields: { type: Array, required: true },
});

const vault = useVaultStore();
const { addToast } = useToast();

const unlockCode = ref('');
const unlocking = ref(false);
const loadingData = ref(false);
const saving = ref(false);
const editing = ref(false);
const revealed = ref(false);
const data = ref({});
const form = ref({});

function emptyForm() {
    return Object.fromEntries(props.fields.map((field) => [field.key, '']));
}

async function handleUnlock() {
    unlocking.value = true;

    const result = await vault.unlock(unlockCode.value);

    unlocking.value = false;

    if (!result.ok) {
        addToast(result.message, 'error');
        return;
    }

    unlockCode.value = '';
    await fetchData();
}

async function fetchData() {
    loadingData.value = true;

    try {
        const response = await fetch(props.fetchUrl, { headers: vault.headers() });

        if (response.status === 403) {
            vault.lock();
            return;
        }

        if (!response.ok) {
            throw new Error('Failed to load vault data');
        }

        const { data: payload } = await response.json();
        data.value = payload;
        form.value = { ...emptyForm(), ...payload };
    } catch {
        addToast('Could not load vault data. Please try again.', 'error');
    } finally {
        loadingData.value = false;
    }
}

function startEditing() {
    form.value = { ...emptyForm(), ...data.value };
    editing.value = true;
}

async function saveData() {
    saving.value = true;

    try {
        const response = await fetch(props.updateUrl, {
            method: 'PUT',
            headers: vault.headers(true),
            body: JSON.stringify(form.value),
        });

        if (response.status === 403) {
            vault.lock();
            addToast('Vault session expired. Unlock it again.', 'error');
            return;
        }

        if (response.status === 422) {
            const body = await response.json();
            const message = Object.values(body.errors ?? {}).flat().join(' ') || 'Invalid data.';
            addToast(message, 'error');
            return;
        }

        if (!response.ok) {
            throw new Error('Failed to save vault data');
        }

        const { data: payload } = await response.json();
        data.value = payload;
        editing.value = false;
        addToast('Vault updated.', 'success');
    } catch {
        addToast('Could not save vault data. Please try again.', 'error');
    } finally {
        saving.value = false;
    }
}

function maskedValue(field) {
    const value = data.value[field.key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (revealed.value) {
        return value;
    }

    const mask = field.maskChar ?? '•';
    return mask.repeat(Math.min(String(value).length, 12));
}

watch(() => vault.isUnlocked, (unlocked) => {
    if (unlocked && !Object.keys(data.value).length) {
        void fetchData();
    }
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <LockClosedIcon v-if="!vault.isUnlocked" class="h-5 w-5 text-gray-400" />
                <LockOpenIcon v-else class="h-5 w-5 text-emerald-500" />
                <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
            </div>
            <button
                v-if="vault.isUnlocked && !editing"
                type="button"
                class="text-sm text-gray-500 hover:text-gray-700"
                @click="revealed = !revealed"
            >
                <span class="inline-flex items-center gap-1">
                    <component :is="revealed ? EyeSlashIcon : EyeIcon" class="h-4 w-4" />
                    {{ revealed ? 'Hide' : 'Reveal' }}
                </span>
            </button>
        </div>

        <div v-if="!vault.isUnlocked" class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-sm font-medium text-gray-700">Enter your two-factor code to unlock</span>
                <input
                    v-model="unlockCode"
                    type="text"
                    inputmode="numeric"
                    placeholder="123456"
                    class="mt-1 block w-40 rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
            </label>
            <button
                type="button"
                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                :disabled="unlocking || !unlockCode"
                @click="handleUnlock"
            >
                {{ unlocking ? 'Unlocking…' : 'Unlock' }}
            </button>
        </div>

        <div v-else-if="loadingData" class="py-6 text-center text-sm text-gray-500">
            Loading…
        </div>

        <div v-else-if="!editing" class="space-y-3">
            <div v-for="field in fields" :key="field.key" class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0">
                <span class="text-sm text-gray-500">{{ field.label }}</span>
                <span class="font-mono text-sm text-gray-900">{{ maskedValue(field) }}</span>
            </div>
            <button
                type="button"
                class="mt-2 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                @click="startEditing"
            >
                Edit
            </button>
        </div>

        <div v-else class="space-y-4">
            <label v-for="field in fields" :key="field.key" class="block">
                <span class="text-sm font-medium text-gray-700">{{ field.label }}</span>
                <input
                    v-model="form[field.key]"
                    :type="field.type === 'number' ? 'number' : 'text'"
                    class="mt-1 block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                >
            </label>
            <div class="flex gap-3">
                <button
                    type="button"
                    class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                    :disabled="saving"
                    @click="saveData"
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
    </section>
</template>
