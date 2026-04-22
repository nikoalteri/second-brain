<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { ChevronDownIcon, ChevronUpIcon, PencilIcon } from '@heroicons/vue/24/outline';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import ConfirmModal from '@/components/ui/ConfirmModal.vue';
import FormInput from '@/components/ui/FormInput.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { useCurrency } from '@/composables/useCurrency.js';
import { useToast } from '@/composables/useToast.js';
import { useAuthStore } from '@/stores/auth.js';

const route = useRoute();
const { formatCurrency } = useCurrency();
const { addToast } = useToast();
const auth = useAuthStore();
const loading = ref(false);
const savingCycle = ref(false);
const issuingCycleId = ref(null);
const deletingCycleId = ref(null);
const payingPaymentId = ref(null);
const deletingPaymentId = ref(null);
const savingExpense = ref(false);
const deletingExpenseId = ref(null);
const expandedCycles = ref(new Set());
const card = ref(null);
const editingCycleId = ref(null);
const showCycleForm = ref(false);
const showExpenseForm = ref(false);
const cycleToDelete = ref(null);
const expenseToDelete = ref(null);
const paymentToDelete = ref(null);
const editingExpenseId = ref(null);

const cycleStatusOptions = [
    { value: 'open', label: 'Open' },
    { value: 'issued', label: 'Issued' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
];

const emptyCycleForm = () => ({
    period_start_date: '',
    statement_date: '',
    due_date: '',
    total_spent: '0',
    status: 'open',
});

const cycleForm = ref(emptyCycleForm());
const cycles = computed(() => card.value?.cycles ?? []);
const payments = computed(() => card.value?.payments ?? []);
const expenses = computed(() => card.value?.expenses ?? []);
const isEditingCycle = computed(() => editingCycleId.value !== null);
const isEditingExpense = computed(() => editingExpenseId.value !== null);

const emptyExpenseForm = () => ({
    spent_at: '',
    posted_at: '',
    amount: '',
    description: '',
    notes: '',
});

const expenseForm = ref(emptyExpenseForm());

function authHeaders(includeJson = false) {
    return {
        Authorization: `Bearer ${auth.accessToken}`,
        Accept: 'application/json',
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
    };
}

async function fetchCard() {
    if (!auth.accessToken) {
        card.value = null;
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}`, {
            headers: authHeaders(),
        });

        if (!response.ok) {
            card.value = null;
            return;
        }

        const data = await response.json();
        card.value = data.data ?? null;
    } finally {
        loading.value = false;
    }
}

function toggleCycle(cycleId) {
    const next = new Set(expandedCycles.value);
    next.has(cycleId) ? next.delete(cycleId) : next.add(cycleId);
    expandedCycles.value = next;
}

function isCycleExpanded(cycleId) {
    return expandedCycles.value.has(cycleId);
}

function openCreateCycle() {
    editingCycleId.value = null;
    cycleForm.value = emptyCycleForm();
    showCycleForm.value = true;
}

function openEditCycle(cycle) {
    editingCycleId.value = cycle.id;
    cycleForm.value = {
        period_start_date: cycle.period_start_date ?? '',
        statement_date: cycle.statement_date ?? '',
        due_date: cycle.due_date ?? '',
        total_spent: String(cycle.total_spent ?? 0),
        status: cycle.status ?? 'open',
    };
    showCycleForm.value = true;
}

function closeCycleForm() {
    showCycleForm.value = false;
    editingCycleId.value = null;
    cycleForm.value = emptyCycleForm();
}

function openCreateExpense() {
    editingExpenseId.value = null;
    expenseForm.value = emptyExpenseForm();
    showExpenseForm.value = true;
}

function openEditExpense(expense) {
    editingExpenseId.value = expense.id;
    expenseForm.value = {
        spent_at: expense.spent_at ?? '',
        posted_at: expense.posted_at ?? '',
        amount: String(expense.amount ?? ''),
        description: expense.description ?? '',
        notes: expense.notes ?? '',
    };
    showExpenseForm.value = true;
}

function closeExpenseForm() {
    editingExpenseId.value = null;
    expenseForm.value = emptyExpenseForm();
    showExpenseForm.value = false;
}

function parseAmount(value) {
    return value === '' || value === null ? 0 : parseFloat(value);
}

async function saveCycle() {
    savingCycle.value = true;

    const isEditing = isEditingCycle.value;
    const url = isEditing
        ? `/api/v1/credit-cards/${route.params.id}/cycles/${editingCycleId.value}`
        : `/api/v1/credit-cards/${route.params.id}/cycles`;
    const method = isEditing ? 'PUT' : 'POST';
    const payload = {
        period_start_date: cycleForm.value.period_start_date,
        statement_date: cycleForm.value.statement_date,
        due_date: cycleForm.value.due_date || null,
        total_spent: parseAmount(cycleForm.value.total_spent),
        status: cycleForm.value.status,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: authHeaders(true),
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Failed to save cycle.');
        }

        await fetchCard();
        closeCycleForm();
        addToast(isEditing ? 'Cycle updated.' : 'Cycle created.', 'success');
    } catch {
        addToast('Could not save the cycle. Please try again.', 'error');
    } finally {
        savingCycle.value = false;
    }
}

async function issueCycle(cycleId) {
    issuingCycleId.value = cycleId;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}/cycles/${cycleId}/issue`, {
            method: 'POST',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to issue cycle.');
        }

        await fetchCard();
        addToast('Cycle issued.', 'success');
    } catch {
        addToast('Could not issue the cycle. Check the card configuration and try again.', 'error');
    } finally {
        issuingCycleId.value = null;
    }
}

function confirmDeleteCycle(cycle) {
    cycleToDelete.value = cycle;
}

async function deleteCycle() {
    if (!cycleToDelete.value) return;

    deletingCycleId.value = cycleToDelete.value.id;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}/cycles/${cycleToDelete.value.id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to delete cycle.');
        }

        await fetchCard();
        cycleToDelete.value = null;
        addToast('Cycle deleted.', 'success');
    } catch {
        addToast('Could not delete the cycle. Please try again.', 'error');
    } finally {
        deletingCycleId.value = null;
    }
}

async function saveExpense() {
    savingExpense.value = true;

    const isEditing = isEditingExpense.value;
    const url = isEditing
        ? `/api/v1/credit-cards/${route.params.id}/expenses/${editingExpenseId.value}`
        : `/api/v1/credit-cards/${route.params.id}/expenses`;
    const method = isEditing ? 'PUT' : 'POST';
    const payload = {
        spent_at: expenseForm.value.spent_at,
        posted_at: expenseForm.value.posted_at || null,
        amount: parseAmount(expenseForm.value.amount),
        description: expenseForm.value.description,
        notes: expenseForm.value.notes || null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: authHeaders(true),
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Failed to save expense.');
        }

        await fetchCard();
        closeExpenseForm();
        addToast(isEditing ? 'Expense updated.' : 'Expense saved.', 'success');
    } catch {
        addToast('Could not save the expense. Please try again.', 'error');
    } finally {
        savingExpense.value = false;
    }
}

function confirmDeleteExpense(expense) {
    expenseToDelete.value = expense;
}

async function deleteExpense() {
    if (!expenseToDelete.value) return;

    deletingExpenseId.value = expenseToDelete.value.id;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}/expenses/${expenseToDelete.value.id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to delete expense.');
        }

        await fetchCard();
        expenseToDelete.value = null;
        addToast('Expense deleted.', 'success');
    } catch {
        addToast('Could not delete the expense. Please try again.', 'error');
    } finally {
        deletingExpenseId.value = null;
    }
}

async function markPaymentAsPaid(paymentId) {
    payingPaymentId.value = paymentId;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}/payments/${paymentId}/mark-paid`, {
            method: 'POST',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to record payment.');
        }

        await fetchCard();
        addToast('Payment recorded and synced to transactions.', 'success');
    } catch {
        addToast('Could not record the payment. Please try again.', 'error');
    } finally {
        payingPaymentId.value = null;
    }
}

function confirmDeletePayment(payment) {
    paymentToDelete.value = payment;
}

async function deletePayment() {
    if (!paymentToDelete.value) return;

    deletingPaymentId.value = paymentToDelete.value.id;

    try {
        const response = await fetch(`/api/v1/credit-cards/${route.params.id}/payments/${paymentToDelete.value.id}`, {
            method: 'DELETE',
            headers: authHeaders(),
        });

        if (!response.ok) {
            throw new Error('Failed to delete payment.');
        }

        await fetchCard();
        paymentToDelete.value = null;
        addToast('Payment deleted.', 'success');
    } catch {
        addToast('Could not delete the payment. Please try again.', 'error');
    } finally {
        deletingPaymentId.value = null;
    }
}

function cycleStatusClass(status) {
    const map = {
        open: 'bg-blue-500/10 text-blue-400',
        issued: 'bg-amber-500/10 text-amber-400',
        closed: 'bg-gray-500/10 text-gray-500',
        paid: 'bg-emerald-500/10 text-emerald-400',
        overdue: 'bg-red-500/10 text-red-400',
    };

    return map[status?.toLowerCase()] ?? 'bg-gray-500/10 text-gray-500';
}

function paymentStatusClass(status) {
    return cycleStatusClass(status);
}

function availablePct(currentCard) {
    if (!currentCard?.credit_limit) return 100;
    return Math.round(((currentCard.available_credit ?? 0) / currentCard.credit_limit) * 100);
}

watch(() => route.params.id, () => {
    void fetchCard();
});

onMounted(() => {
    void fetchCard();
});
</script>

<template>
    <AppLayout>
        <LoadingSpinner v-if="loading" class="py-16" />

        <template v-else-if="card">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ card.name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ card.type }} card · {{ card.type === 'revolving' ? `${card.interest_rate ?? 0}% interest` : 'no interest' }}</p>
                </div>
                <router-link
                    :to="`/credit-cards/${card.id}/edit`"
                    class="flex h-10 items-center gap-2 rounded-lg border border-gray-600 bg-gray-100 px-4 text-sm text-gray-900 transition-colors hover:bg-gray-50"
                >
                    <PencilIcon class="h-4 w-4" />
                    Edit
                </router-link>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 grid grid-cols-2 gap-6 md:grid-cols-3">
                    <div>
                        <p class="text-sm text-gray-500">Current Balance</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-purple-400">{{ formatCurrency(card.current_balance) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Credit Limit</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-gray-900">{{ formatCurrency(card.credit_limit ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Available</p>
                        <p class="mt-1 font-mono text-xl font-semibold text-purple-400">{{ formatCurrency(card.available_credit ?? 0) }}</p>
                    </div>
                </div>

                <div class="mb-1 h-2 w-full rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-purple-500" :style="{ width: `${availablePct(card)}%` }" />
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>{{ availablePct(card) }}% available</span>
                    <span>Statement day {{ card.statement_day }} · Due day {{ card.due_day }}</span>
                </div>
            </div>

            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Billing Cycles</h2>
                    <p class="mt-1 text-sm text-gray-500">Same cycle records as Filament: period start, statement date, due date, total spent, and issue action.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100"
                    @click="openCreateCycle"
                >
                    New cycle
                </button>
            </div>

            <section v-if="showCycleForm" class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ isEditingCycle ? 'Edit cycle' : 'New cycle' }}</h3>
                        <p class="mt-1 text-sm text-gray-500">Mirror the backend cycle manager. Leave due date empty to apply the backend default.</p>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-medium text-gray-500 transition-colors hover:text-gray-700"
                        @click="closeCycleForm"
                    >
                        Cancel
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <FormInput
                        label="Period start"
                        v-model="cycleForm.period_start_date"
                        type="date"
                    />
                    <FormInput
                        label="Statement date"
                        v-model="cycleForm.statement_date"
                        type="date"
                    />
                    <FormInput
                        label="Due date"
                        v-model="cycleForm.due_date"
                        type="date"
                        helper="Optional. When empty, the backend applies its default due-date rule."
                    />
                    <FormInput
                        label="Total spent"
                        v-model="cycleForm.total_spent"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <FormSelect
                        label="Status"
                        v-model="cycleForm.status"
                        :options="cycleStatusOptions"
                    />
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="savingCycle"
                        @click="saveCycle"
                    >
                        {{ savingCycle ? 'Saving…' : (isEditingCycle ? 'Update cycle' : 'Create cycle') }}
                    </button>
                </div>
            </section>

            <div v-if="!cycles.length" class="py-8 text-center text-sm text-gray-500">
                No billing cycles yet.
            </div>

            <div class="flex flex-col gap-3">
                <div
                    v-for="cycle in cycles"
                    :key="cycle.id"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white"
                >
                    <button
                        class="flex w-full items-center justify-between p-4 transition-colors hover:bg-gray-100/40"
                        @click="toggleCycle(cycle.id)"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="min-w-0 text-left">
                                <p class="text-sm font-normal text-gray-900">{{ cycle.period_month ?? cycle.period_start_date }}</p>
                                <p class="mt-0.5 text-sm text-gray-500">{{ cycle.period_start_date ?? '—' }} → {{ cycle.statement_date ?? '—' }}</p>
                            </div>
                            <span :class="cycleStatusClass(cycle.status)" class="shrink-0 rounded px-2 py-0.5 text-sm capitalize">
                                {{ cycle.status }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4">
                            <p class="font-mono text-sm text-purple-400">{{ formatCurrency(cycle.total_due || cycle.total_spent) }}</p>
                            <component :is="isCycleExpanded(cycle.id) ? ChevronUpIcon : ChevronDownIcon" class="h-4 w-4 shrink-0 text-gray-500" />
                        </div>
                    </button>

                    <div v-if="isCycleExpanded(cycle.id)" class="border-t border-gray-200 px-4 py-3">
                        <div class="mb-4 flex flex-wrap items-center gap-3">
                            <button
                                v-if="cycle.can_issue"
                                type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="issuingCycleId === cycle.id"
                                @click.stop="issueCycle(cycle.id)"
                            >
                                {{ issuingCycleId === cycle.id ? 'Issuing…' : 'Issue cycle' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                @click.stop="openEditCycle(cycle)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-red-200 bg-red-50 px-3 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                                @click.stop="confirmDeleteCycle(cycle)"
                            >
                                Delete
                            </button>
                        </div>

                        <div class="mb-4 grid gap-4 md:grid-cols-4">
                            <div>
                                <p class="text-sm text-gray-500">Period start</p>
                                <p class="mt-1 text-sm text-gray-900">{{ cycle.period_start_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Statement date</p>
                                <p class="mt-1 text-sm text-gray-900">{{ cycle.statement_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Due date</p>
                                <p class="mt-1 text-sm text-gray-900">{{ cycle.due_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total due</p>
                                <p class="mt-1 font-mono text-sm text-gray-900">{{ formatCurrency(cycle.total_due ?? 0) }}</p>
                            </div>
                        </div>

                        <div v-if="!cycle.expenses?.length" class="py-4 text-center text-sm text-gray-500">
                            No expenses in this cycle.
                        </div>
                        <div v-else class="divide-y divide-gray-700/50">
                            <div
                                v-for="expense in cycle.expenses"
                                :key="expense.id"
                                class="flex items-center justify-between py-3"
                            >
                                <div>
                                    <p class="text-sm text-gray-900">{{ expense.description ?? 'Expense' }}</p>
                                    <p class="text-sm text-gray-500">{{ expense.spent_at }}</p>
                                </div>
                                <p class="font-mono text-sm text-purple-400">{{ formatCurrency(expense.amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Payments</h2>
                        <p class="mt-1 text-sm text-gray-500">Same as the backend payments relation: mark a pending payment as paid and it posts automatically to transactions.</p>
                    </div>
                </div>

                <div v-if="!payments.length" class="py-6 text-center text-sm text-gray-500">
                    No payments yet. Issue a cycle to generate them.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Due</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Paid on</th>
                                <th class="pb-3 pr-4 text-right font-medium text-gray-500">Total</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Status</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Transactions</th>
                                <th class="pb-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="payment in payments" :key="payment.id">
                                <td class="py-3 pr-4 text-gray-900">{{ payment.due_date ?? '—' }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ payment.actual_date ?? '—' }}</td>
                                <td class="py-3 pr-4 text-right font-mono text-gray-900">{{ formatCurrency(payment.total_amount ?? 0) }}</td>
                                <td class="py-3 pr-4">
                                    <span :class="paymentStatusClass(payment.status)" class="rounded px-2 py-0.5 text-sm capitalize">
                                        {{ payment.status }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-xs" :class="payment.transaction_posted ? 'text-emerald-600' : 'text-gray-500'">
                                    {{ payment.transaction_posted ? 'Posted' : 'Pending' }}
                                </td>
                                <td class="py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="payment.status === 'pending'"
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-100 disabled:opacity-60"
                                            :disabled="payingPaymentId === payment.id"
                                            @click="markPaymentAsPaid(payment.id)"
                                        >
                                            {{ payingPaymentId === payment.id ? 'Recording…' : 'Mark as paid' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                                            @click="confirmDeletePayment(payment)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Expenses</h2>
                        <p class="mt-1 text-sm text-gray-500">Same fields as the backend expenses relation. Cycle assignment stays automatic.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100"
                        @click="openCreateExpense"
                    >
                        New expense
                    </button>
                </div>

                <section v-if="showExpenseForm" class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ isEditingExpense ? 'Edit expense' : 'New expense' }}</h3>
                        <button type="button" class="text-sm font-medium text-gray-500 hover:text-gray-700" @click="closeExpenseForm">Cancel</button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInput label="Transaction date" v-model="expenseForm.spent_at" type="date" />
                        <FormInput
                            label="Posted date"
                            v-model="expenseForm.posted_at"
                            type="date"
                            helper="Leave empty if it matches the transaction date."
                        />
                        <FormInput label="Amount" v-model="expenseForm.amount" type="number" min="0.01" step="0.01" />
                        <FormInput label="Description" v-model="expenseForm.description" />
                        <FormInput label="Notes" v-model="expenseForm.notes" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="savingExpense"
                            @click="saveExpense"
                        >
                            {{ savingExpense ? 'Saving…' : (isEditingExpense ? 'Update expense' : 'Create expense') }}
                        </button>
                    </div>
                </section>

                <div v-if="!expenses.length" class="py-6 text-center text-sm text-gray-500">
                    No expenses yet.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Date</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Posted</th>
                                <th class="pb-3 pr-4 text-right font-medium text-gray-500">Amount</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Description</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Cycle</th>
                                <th class="pb-3 pr-4 text-left font-medium text-gray-500">Notes</th>
                                <th class="pb-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="expense in expenses" :key="expense.id">
                                <td class="py-3 pr-4 text-gray-900">{{ expense.spent_at ?? '—' }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ expense.posted_at ?? '= transaction date' }}</td>
                                <td class="py-3 pr-4 text-right font-mono text-gray-900">{{ formatCurrency(expense.amount ?? 0) }}</td>
                                <td class="py-3 pr-4 text-gray-900">{{ expense.description }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ expense.cycle?.period_month ?? 'Auto' }}</td>
                                <td class="py-3 pr-4 text-gray-500">{{ expense.notes ?? '—' }}</td>
                                <td class="py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                                            @click="openEditExpense(expense)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition-colors hover:bg-red-100"
                                            @click="confirmDeleteExpense(expense)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <ConfirmModal
            :open="!!cycleToDelete"
            title="Delete cycle?"
            message="This cycle will be removed from the card."
            confirm-label="Delete cycle"
            :loading="deletingCycleId !== null"
            @confirm="deleteCycle"
            @cancel="cycleToDelete = null"
        />
        <ConfirmModal
            :open="!!expenseToDelete"
            title="Delete expense?"
            message="This expense will be removed from the card and its cycle totals will be recalculated."
            confirm-label="Delete expense"
            :loading="deletingExpenseId !== null"
            @confirm="deleteExpense"
            @cancel="expenseToDelete = null"
        />
        <ConfirmModal
            :open="!!paymentToDelete"
            title="Delete payment?"
            message="This payment record will be removed from the card."
            confirm-label="Delete payment"
            :loading="deletingPaymentId !== null"
            @confirm="deletePayment"
            @cancel="paymentToDelete = null"
        />
    </AppLayout>
</template>
