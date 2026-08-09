<script setup>
import { computed, onMounted, ref } from 'vue';
import { BanknotesIcon, CreditCardIcon, LockClosedIcon, LockOpenIcon, WalletIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import SensitiveVaultPanel from '@/components/vault/SensitiveVaultPanel.vue';
import VaultPanel from '@/components/vault/VaultPanel.vue';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';
import { useVaultStore } from '@/stores/vault.js';

const auth = useAuthStore();
const vault = useVaultStore();
const { addToast } = useToast();

const unlockCode = ref('');
const unlocking = ref(false);
const loadingData = ref(false);

const accounts = ref([]);
const creditCards = ref([]);
const vaultCards = ref([]);

const showAddForm = ref(false);
const savingCard = ref(false);
const editingCardId = ref(null);
const cardForm = ref(emptyCardForm());

function emptyCardForm() {
    return {
        name: '',
        type: 'debit',
        brand: 'visa',
        account_id: '',
        card_number: '',
        expiry_month: '',
        expiry_year: '',
    };
}

const typeOptions = [
    { value: 'debit', label: 'Bancomat' },
    { value: 'prepaid', label: 'Prepagata' },
];
const brandOptions = [
    { value: 'visa', label: 'Visa' },
    { value: 'mastercard', label: 'Mastercard' },
    { value: 'amex', label: 'American Express' },
];
const accountOptions = computed(() => [
    { value: '', label: 'Nessun conto collegato' },
    ...accounts.value.map((account) => ({ value: account.id, label: account.name })),
]);

function authHeaders() {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
    };
}

async function fetchAccounts() {
    const response = await fetch('/api/v1/accounts?per_page=100', { headers: authHeaders() });
    if (response.ok) {
        const data = await response.json();
        accounts.value = data.data ?? [];
    }
}

async function fetchCreditCards() {
    const response = await fetch('/api/v1/credit-cards?per_page=100', { headers: authHeaders() });
    if (response.ok) {
        const data = await response.json();
        creditCards.value = data.data ?? [];
    }
}

async function fetchVaultCards() {
    const response = await fetch('/api/v1/vault-cards', { headers: vault.headers() });
    if (response.status === 403) {
        vault.lock();
        return;
    }
    if (response.ok) {
        const data = await response.json();
        vaultCards.value = data.data ?? [];
    }
}

async function loadAll() {
    loadingData.value = true;

    try {
        await Promise.all([fetchAccounts(), fetchCreditCards(), fetchVaultCards()]);
    } finally {
        loadingData.value = false;
    }
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
    await loadAll();
}

function accountName(accountId) {
    return accounts.value.find((account) => account.id === accountId)?.name ?? null;
}

function startAdd() {
    editingCardId.value = null;
    cardForm.value = emptyCardForm();
    showAddForm.value = true;
}

function startEditCard(card) {
    editingCardId.value = card.id;
    cardForm.value = {
        name: card.name,
        type: card.type,
        brand: card.brand,
        account_id: card.account_id ?? '',
        card_number: card.card_number ?? '',
        expiry_month: card.expiry_month ?? '',
        expiry_year: card.expiry_year ?? '',
    };
    showAddForm.value = true;
}

async function saveCard() {
    savingCard.value = true;

    const payload = {
        name: cardForm.value.name,
        type: cardForm.value.type,
        brand: cardForm.value.brand,
        account_id: cardForm.value.account_id || null,
        card_number: cardForm.value.card_number || null,
        expiry_month: cardForm.value.expiry_month || null,
        expiry_year: cardForm.value.expiry_year || null,
    };

    try {
        const url = editingCardId.value ? `/api/v1/vault-cards/${editingCardId.value}` : '/api/v1/vault-cards';
        const response = await fetch(url, {
            method: editingCardId.value ? 'PUT' : 'POST',
            headers: vault.headers(true),
            body: JSON.stringify(payload),
        });

        if (response.status === 403) {
            vault.lock();
            addToast('Vault session expired. Unlock it again.', 'error');
            return;
        }

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            const message = Object.values(body.errors ?? {}).flat().join(' ') || 'Invalid data.';
            addToast(message, 'error');
            return;
        }

        showAddForm.value = false;
        addToast('Card saved.', 'success');
        await fetchVaultCards();
    } catch {
        addToast('Network error. Please try again.', 'error');
    } finally {
        savingCard.value = false;
    }
}

async function deleteCard(card) {
    if (!confirm(`Eliminare "${card.name}" dal vault?`)) {
        return;
    }

    try {
        const response = await fetch(`/api/v1/vault-cards/${card.id}`, {
            method: 'DELETE',
            headers: vault.headers(),
        });

        if (response.status === 403) {
            vault.lock();
            addToast('Vault session expired. Unlock it again.', 'error');
            return;
        }

        if (!response.ok) {
            throw new Error('Failed to delete card.');
        }

        addToast('Card deleted.', 'success');
        await fetchVaultCards();
    } catch {
        addToast('Could not delete card. Please try again.', 'error');
    }
}

onMounted(() => {
    if (vault.isUnlocked) {
        void loadAll();
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center gap-2">
            <LockClosedIcon v-if="!vault.isUnlocked" class="h-6 w-6 text-gray-400" />
            <LockOpenIcon v-else class="h-6 w-6 text-emerald-500" />
            <h1 class="text-xl font-semibold text-gray-900">Vault</h1>
        </div>

        <div v-if="!vault.isUnlocked" class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex flex-wrap items-end gap-3">
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
        </div>

        <LoadingSpinner v-else-if="loadingData" class="py-16" />

        <div v-else class="space-y-8">
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    <BanknotesIcon class="h-4 w-4" /> Conti
                </h2>
                <EmptyState v-if="!accounts.length" title="Nessun conto" message="Non hai ancora nessun conto." />
                <div v-else class="space-y-4">
                    <VaultPanel
                        v-for="account in accounts"
                        :key="`account-${account.id}`"
                        :title="account.name"
                        :fetch-url="`/api/v1/accounts/${account.id}/vault`"
                        :update-url="`/api/v1/accounts/${account.id}/vault`"
                        :fields="[{ key: 'iban', label: 'IBAN' }]"
                    />
                </div>
            </section>

            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    <CreditCardIcon class="h-4 w-4" /> Carte di credito
                </h2>
                <EmptyState v-if="!creditCards.length" title="Nessuna carta di credito" message="Non hai ancora nessuna carta di credito." />
                <div v-else class="space-y-4">
                    <div v-for="card in creditCards" :key="`credit-card-${card.id}`" class="rounded-xl border border-gray-200 bg-white p-6">
                        <VaultPanel
                            :title="card.name"
                            :fetch-url="`/api/v1/credit-cards/${card.id}/vault`"
                            :update-url="`/api/v1/credit-cards/${card.id}/vault`"
                            :fields="[
                                { key: 'card_number', label: 'Card number' },
                                { key: 'expiry_month', label: 'Expiry month', type: 'number' },
                                { key: 'expiry_year', label: 'Expiry year', type: 'number' },
                            ]"
                        />
                        <SensitiveVaultPanel
                            :reveal-url="`/api/v1/credit-cards/${card.id}/vault/sensitive/reveal`"
                            :update-url="`/api/v1/credit-cards/${card.id}/vault/sensitive`"
                            :brand="card.brand"
                        />
                    </div>
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        <WalletIcon class="h-4 w-4" /> Bancomat e prepagate
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100"
                        @click="startAdd"
                    >
                        Aggiungi carta
                    </button>
                </div>

                <div v-if="showAddForm" class="mb-4 rounded-xl border border-gray-200 bg-white p-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInput label="Nome *" v-model="cardForm.name" placeholder="es. Postepay" />
                        <FormSelect label="Tipo *" v-model="cardForm.type" :options="typeOptions" />
                        <FormSelect label="Brand *" v-model="cardForm.brand" :options="brandOptions" />
                        <FormSelect
                            label="Conto collegato"
                            v-model="cardForm.account_id"
                            :options="accountOptions"
                            helper="Opzionale — per tracciare il saldo di una prepagata puoi collegarla a un conto esistente."
                        />
                        <FormInput label="Numero carta" v-model="cardForm.card_number" placeholder="1234 5678 9012 3456" />
                        <FormInput label="Mese scadenza" v-model="cardForm.expiry_month" type="number" min="1" max="12" />
                        <FormInput label="Anno scadenza" v-model="cardForm.expiry_year" type="number" />
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button
                            type="button"
                            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:cursor-not-allowed disabled:bg-amber-300"
                            :disabled="savingCard || !cardForm.name"
                            @click="saveCard"
                        >
                            {{ savingCard ? 'Salvataggio…' : 'Salva' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            @click="showAddForm = false"
                        >
                            Annulla
                        </button>
                        <router-link
                            to="/accounts/new"
                            class="ml-auto text-sm text-amber-700 hover:text-amber-800"
                        >
                            + Crea un nuovo conto
                        </router-link>
                    </div>
                </div>

                <EmptyState v-if="!vaultCards.length && !showAddForm" title="Nessun bancomat o prepagata" message="Aggiungi un bancomat o una prepagata al vault." />
                <div v-else class="space-y-4">
                    <div v-for="card in vaultCards" :key="`vault-card-${card.id}`" class="rounded-xl border border-gray-200 bg-white p-6">
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">{{ card.name }}</h3>
                                <p class="text-sm text-gray-500">
                                    {{ typeOptions.find((option) => option.value === card.type)?.label }}
                                    · {{ brandOptions.find((option) => option.value === card.brand)?.label }}
                                    <span v-if="accountName(card.account_id)"> · {{ accountName(card.account_id) }}</span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="startEditCard(card)">Modifica</button>
                                <button type="button" class="text-sm text-red-500 hover:text-red-600" @click="deleteCard(card)">Elimina</button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-100 py-2">
                            <span class="text-sm text-gray-500">Numero carta</span>
                            <span class="font-mono text-sm text-gray-900">{{ card.card_number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-gray-100 py-2">
                            <span class="text-sm text-gray-500">Scadenza</span>
                            <span class="font-mono text-sm text-gray-900">
                                {{ card.expiry_month && card.expiry_year ? `${card.expiry_month}/${card.expiry_year}` : '—' }}
                            </span>
                        </div>
                        <SensitiveVaultPanel
                            :reveal-url="`/api/v1/vault-cards/${card.id}/sensitive/reveal`"
                            :update-url="`/api/v1/vault-cards/${card.id}/sensitive`"
                            :brand="card.brand"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
