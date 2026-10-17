<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useStatementImportStore } from '@/stores/statementImport.js';
import {
    DATE_FORMATS,
    amountToMinor,
    columnOptions,
    guessMapping,
    isPostable,
    minorToAmount,
    needsDecision,
    rowNotes,
} from '@/utils/statementImport.js';

const store = useStatementImportStore();
const { formatCurrency } = useCurrency();

const target = ref('');
const file = ref(null);
const endingBalance = ref('');
const periodStart = ref('');
const periodEnd = ref('');
const mapping = reactive({});
const filter = ref('ready');
const selected = ref(new Set());

const targetOptions = computed(() => [
    ...store.targets.accounts.map((account) => ({ value: `account:${account.id}`, label: `${account.name} (${account.currency})` })),
    ...store.targets.cards.map((card) => ({ value: `card:${card.id}`, label: `${card.name} (credit card)` })),
]);
const columns = computed(() => columnOptions(store.preview, Number(mapping.header_row) || 0));
const columnChoices = computed(() => columns.value);
const optionalColumns = computed(() => [{ value: '', label: 'None' }, ...columns.value]);
const currency = computed(() => store.batch?.currency ?? 'EUR');
const stepLabels = { upload: 'Upload', mapping: 'Map columns', review: 'Review', done: 'Done' };
const stepOrder = ['upload', 'mapping', 'review', 'done'];

const filtered = computed(() => store.rows.filter((row) => {
    switch (filter.value) {
        case 'ready': return isPostable(row) && !needsDecision(row);
        case 'decision': return isPostable(row) && needsDecision(row);
        case 'errors': return row.status === 'draft' && !!row.error;
        case 'discarded': return row.status === 'discarded';
        case 'confirmed': return row.status === 'confirmed';
        default: return row.status !== 'skipped';
    }
}));
const counts = computed(() => ({
    ready: store.rows.filter((row) => isPostable(row) && !needsDecision(row)).length,
    decision: store.rows.filter((row) => isPostable(row) && needsDecision(row)).length,
    errors: store.rows.filter((row) => row.status === 'draft' && !!row.error).length,
    discarded: store.rows.filter((row) => row.status === 'discarded').length,
    confirmed: store.rows.filter((row) => row.status === 'confirmed').length,
}));
const selectedIds = computed(() => [...selected.value].filter((id) => store.rows.some((row) => row.id === id && isPostable(row))));
const problemOutcomes = computed(() => store.outcomes.filter((outcome) => outcome.outcome !== 'confirmed'));

function initMapping() {
    const initial = store.savedMapping ?? guessMapping(store.preview);
    Object.keys(mapping).forEach((key) => delete mapping[key]);
    Object.assign(mapping, initial);
}

function pickFile(event) {
    file.value = event.target.files?.[0] ?? null;
}

async function submitUpload() {
    const [kind, id] = target.value.split(':');

    await store.upload({
        file: file.value,
        accountId: kind === 'account' ? id : null,
        creditCardId: kind === 'card' ? id : null,
        endingBalance: endingBalance.value,
        periodStart: periodStart.value,
        periodEnd: periodEnd.value,
    });
}

function mappingPayload() {
    const orNull = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

    return {
        header_row: Number(mapping.header_row) || 0,
        date_column: Number(mapping.date_column),
        date_format: mapping.date_format,
        amount_mode: mapping.amount_mode,
        amount_column: mapping.amount_mode === 'single' ? orNull(mapping.amount_column) : null,
        debit_column: mapping.amount_mode === 'split' ? orNull(mapping.debit_column) : null,
        credit_column: mapping.amount_mode === 'split' ? orNull(mapping.credit_column) : null,
        decimal_separator: mapping.decimal_separator,
        thousands_separator: mapping.thousands_separator ?? '',
        invert_sign: !!mapping.invert_sign,
        description_column: Number(mapping.description_column),
        category_column: orNull(mapping.category_column),
        currency_column: orNull(mapping.currency_column),
    };
}

async function submitMapping() {
    const result = await store.applyMapping(mappingPayload());

    if (result?.ok) {
        filter.value = 'ready';
        selected.value = new Set();
    }
}

function toggle(id) {
    const next = new Set(selected.value);

    if (next.has(id)) next.delete(id); else next.add(id);
    selected.value = next;
}

async function editDescription(row, event) {
    const value = event.target.value.trim();

    if (value !== row.normalized.description) {
        await store.updateRow(row.id, { description: value });
    }
}

async function editAmount(row, event) {
    const minor = amountToMinor(event.target.value);

    if (minor === null || minor === 0) {
        event.target.value = minorToAmount(row.normalized.amount_minor).toFixed(2);

        return;
    }

    if (minor !== row.normalized.amount_minor) {
        await store.updateRow(row.id, { amount: (minor / 100).toFixed(2) });
    }
}

async function confirmSelected() {
    const ids = selectedIds.value;
    selected.value = new Set();
    await store.confirm({ rowIds: ids });
}

async function startOver() {
    if (store.batch && store.batch.status !== 'completed') {
        await store.discardBatch();
    } else {
        store.clearBatch();
    }

    file.value = null;
}

watch(() => store.step, (step) => {
    if (step === 'mapping') {
        initMapping();
    }
}, { immediate: true });

onMounted(() => {
    void store.loadTargets();

    if (store.batch && store.rows.length === 0 && store.step === 'review') {
        void store.loadRows();
    }
});
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Import statement</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Nothing is added to your accounts until you confirm each row.
                </p>
            </div>
            <router-link to="/transactions" class="text-sm font-medium text-blue-600 hover:underline">Back to transactions</router-link>
        </div>

        <ol class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Import steps">
            <li
                v-for="name in stepOrder"
                :key="name"
                class="rounded-full px-3 py-1 font-medium"
                :class="name === store.step ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500'"
                :aria-current="name === store.step ? 'step' : undefined"
            >
                {{ stepLabels[name] }}
            </li>
        </ol>

        <div v-if="store.error" role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">{{ store.error }}</p>
            <ul v-if="Object.keys(store.fieldErrors).length" class="mt-1 list-disc pl-5">
                <li v-for="(messages, field) in store.fieldErrors" :key="field">{{ messages[0] }}</li>
            </ul>
        </div>

        <!-- 1. Upload -->
        <form v-if="store.step === 'upload'" class="grid max-w-2xl gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submitUpload">
            <FormSelect v-model="target" label="Import into" placeholder="Choose an account or card" :options="targetOptions" />
            <div class="flex flex-col gap-1">
                <label for="statement-file" class="text-sm font-medium text-gray-700">Statement file (CSV or XLSX)</label>
                <input id="statement-file" type="file" accept=".csv,.xlsx" class="text-sm text-gray-700" @change="pickFile">
                <p class="text-xs text-gray-500">Up to 5 MB and 5,000 rows. PDF statements are not supported.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormInput v-model="endingBalance" label="Ending balance (optional)" type="number" step="0.01" helper="As printed on the statement" />
                <FormInput v-model="periodStart" label="Period start" type="date" />
                <FormInput v-model="periodEnd" label="Period end" type="date" />
            </div>
            <div>
                <button
                    type="submit"
                    class="h-10 rounded-lg bg-amber-500 px-4 text-sm text-gray-900 hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!file || !target || store.loading"
                >
                    Upload and preview
                </button>
            </div>
        </form>

        <!-- 2. Mapping -->
        <section v-else-if="store.step === 'mapping'" class="grid gap-6">
            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th v-for="column in columnChoices" :key="column.value" class="px-3 py-2">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in store.preview" :key="row.row_number" class="border-t border-gray-100">
                            <td class="px-3 py-2 text-gray-400">{{ row.row_number }}</td>
                            <td v-for="(cell, index) in row.cells" :key="index" class="max-w-[14rem] truncate px-3 py-2 text-gray-900">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm md:grid-cols-2" @submit.prevent="submitMapping">
                <FormInput v-model="mapping.header_row" label="Header lines to skip" type="number" min="0" max="50" helper="Lines up to this one are not transactions" />
                <FormSelect v-model="mapping.date_column" label="Date column" :options="columnChoices" />
                <FormSelect v-model="mapping.date_format" label="Date format" :options="DATE_FORMATS.map((f) => ({ value: f.value, label: f.label }))" />
                <FormSelect v-model="mapping.amount_mode" label="Amounts are in" :options="[{ value: 'single', label: 'One signed column' }, { value: 'split', label: 'Separate debit and credit columns' }]" />
                <FormSelect v-if="mapping.amount_mode === 'single'" v-model="mapping.amount_column" label="Amount column" :options="columnChoices" />
                <template v-else>
                    <FormSelect v-model="mapping.debit_column" label="Debit (money out) column" :options="columnChoices" />
                    <FormSelect v-model="mapping.credit_column" label="Credit (money in) column" :options="columnChoices" />
                </template>
                <FormSelect v-model="mapping.decimal_separator" label="Decimal separator" :options="[{ value: '.', label: 'Dot (1234.56)' }, { value: ',', label: 'Comma (1234,56)' }]" />
                <FormSelect v-model="mapping.thousands_separator" label="Thousands separator" :options="[{ value: '', label: 'None' }, { value: ',', label: 'Comma' }, { value: '.', label: 'Dot' }, { value: ' ', label: 'Space' }, { value: '\'', label: 'Apostrophe' }]" />
                <FormSelect v-model="mapping.description_column" label="Description column" :options="columnChoices" />
                <FormSelect v-model="mapping.category_column" label="Category column (optional)" :options="optionalColumns" />
                <FormSelect v-model="mapping.currency_column" label="Currency column (optional)" :options="optionalColumns" />
                <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                    <input v-model="mapping.invert_sign" type="checkbox" class="rounded border-gray-300">
                    Reverse the sign (use for credit card statements that list charges as positive)
                </label>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button type="submit" class="h-10 rounded-lg bg-amber-500 px-4 text-sm text-gray-900 hover:bg-amber-600 disabled:opacity-50" :disabled="store.loading">Apply mapping</button>
                    <button type="button" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="startOver">Cancel import</button>
                </div>
            </form>
        </section>

        <!-- 3. Review -->
        <section v-else-if="store.step === 'review'" class="grid gap-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs uppercase text-gray-500">Ready</p><p class="text-2xl font-semibold text-gray-900">{{ counts.ready }}</p></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase text-amber-800">Need your decision</p><p class="text-2xl font-semibold text-amber-900">{{ counts.decision }}</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs uppercase text-gray-500">Could not be read</p><p class="text-2xl font-semibold text-gray-900">{{ counts.errors }}</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs uppercase text-gray-500">Confirmed</p><p class="text-2xl font-semibold text-gray-900">{{ counts.confirmed }}</p></div>
            </div>

            <div v-if="problemOutcomes.length" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                <p class="font-semibold">Some rows were not posted</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="outcome in problemOutcomes" :key="outcome.row_id">Row {{ store.rows.find((r) => r.id === outcome.row_id)?.row_number ?? outcome.row_id }}: {{ outcome.reason }}</li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="option in [['ready', 'Ready'], ['decision', 'Need decision'], ['errors', 'Errors'], ['discarded', 'Discarded'], ['confirmed', 'Confirmed'], ['all', 'All']]"
                    :key="option[0]"
                    type="button"
                    class="h-9 rounded-full px-3 text-sm"
                    :class="filter === option[0] ? 'bg-amber-100 font-medium text-amber-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    @click="filter = option[0]"
                >
                    {{ option[1] }}
                </button>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" class="h-10 rounded-lg bg-amber-500 px-4 text-sm text-gray-900 hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!counts.ready || store.loading" @click="store.confirm({ allReady: true })">
                    Confirm all ready ({{ counts.ready }})
                </button>
                <button type="button" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedIds.length || store.loading" @click="confirmSelected">
                    Confirm selected ({{ selectedIds.length }})
                </button>
                <button type="button" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="startOver">Cancel import</button>
            </div>

            <LoadingSpinner v-if="store.loading && !store.rows.length" class="py-10" />

            <div v-else-if="!filtered.length" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
                No rows in this view.
            </div>

            <div v-else class="grid gap-3">
                <article v-for="row in filtered" :key="row.id" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" :data-row="row.row_number">
                    <div class="flex flex-wrap items-start gap-3">
                        <input v-if="isPostable(row)" type="checkbox" class="mt-2 rounded border-gray-300" :checked="selected.has(row.id)" :aria-label="`Select row ${row.row_number}`" @change="toggle(row.id)">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-500">Row {{ row.row_number }}<template v-if="row.normalized"> · {{ row.normalized.date }}</template></p>
                            <template v-if="row.normalized && row.status === 'draft'">
                                <input class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1 text-sm text-gray-900" :value="row.normalized.description" :aria-label="`Description of row ${row.row_number}`" @change="editDescription(row, $event)">
                            </template>
                            <p v-else-if="row.normalized" class="mt-1 text-sm text-gray-900">{{ row.normalized.description }}</p>
                            <p v-else class="mt-1 text-sm text-red-700">{{ row.error }}</p>
                            <ul v-if="rowNotes(row).length" class="mt-2 flex flex-wrap gap-2">
                                <li v-for="note in rowNotes(row)" :key="note" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{{ note }}</li>
                            </ul>
                            <p v-if="row.failure_reason" class="mt-1 text-xs text-red-700">{{ row.failure_reason }}</p>
                        </div>
                        <div v-if="row.normalized" class="text-right">
                            <input
                                v-if="row.status === 'draft'"
                                class="w-28 rounded-lg border border-gray-200 px-2 py-1 text-right font-mono text-sm"
                                :value="minorToAmount(row.normalized.amount_minor).toFixed(2)"
                                :aria-label="`Amount of row ${row.row_number}`"
                                @change="editAmount(row, $event)"
                            >
                            <p v-else class="font-mono text-sm text-gray-900">{{ formatCurrency(minorToAmount(row.normalized.amount_minor), currency) }}</p>
                            <p class="text-xs text-gray-500">{{ currency }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="row.status === 'draft'" type="button" class="text-xs font-medium text-red-600 hover:underline" @click="store.updateRow(row.id, { status: 'discarded' })">Discard</button>
                            <button v-else-if="row.status === 'discarded'" type="button" class="text-xs font-medium text-blue-600 hover:underline" @click="store.updateRow(row.id, { status: 'draft' })">Restore</button>
                        </div>
                    </div>
                </article>
                <button v-if="store.nextCursor" type="button" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="store.loadRows({ more: true })">Load more rows</button>
            </div>
        </section>

        <!-- 4. Done -->
        <section v-else class="max-w-2xl rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900">
            <h2 class="text-lg font-semibold">Import finished</h2>
            <p class="mt-1 text-sm">
                {{ store.summary?.confirmed ?? 0 }} rows were added, {{ store.summary?.discarded ?? 0 }} discarded.
            </p>
            <div class="mt-4 flex gap-3">
                <router-link to="/transactions" class="h-10 rounded-lg bg-amber-500 px-4 text-sm leading-10 text-gray-900 hover:bg-amber-600">View transactions</router-link>
                <button type="button" class="h-10 rounded-lg border border-emerald-300 px-4 text-sm hover:bg-emerald-100" @click="startOver">Import another</button>
            </div>
        </section>
    </AppLayout>
</template>
