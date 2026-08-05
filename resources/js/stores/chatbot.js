import { ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';

export const INTENT_CHIPS = [
    { id: 'balances', label: 'Check account balances', intent: 'account_balances', params: {} },
    { id: 'upcoming', label: 'See upcoming payments', nextStep: 'upcoming_days' },
    { id: 'spending', label: "View this month's spending", nextStep: 'spending_period' },
];

export const UPCOMING_DAYS_CHIPS = [
    { id: 'days-3', label: 'Next 3 days', intent: 'upcoming_payments', params: { days: 3 } },
    { id: 'days-7', label: 'Next 7 days', intent: 'upcoming_payments', params: { days: 7 } },
    { id: 'days-30', label: 'Next 30 days', intent: 'upcoming_payments', params: { days: 30 } },
];

export const SPENDING_PERIOD_CHIPS = [
    { id: 'this-month', label: 'This month', intent: 'monthly_spending', params: {} },
    { id: 'last-month', label: 'Last month', intent: 'monthly_spending', paramsFactory: 'previous_month' },
];

const MONTH_PATTERN = /^\d{4}-(0[1-9]|1[0-2])$/;

let nextMessageId = 1;

export const useChatbotStore = defineStore('chatbot', () => {
    const isOpen = ref(false);
    const messages = ref([]);
    const quickReplies = ref(INTENT_CHIPS);
    const activeStep = ref('intent_select');
    const freeTextEnabled = ref(false);
    const freeTextPlaceholder = ref('');
    const loading = ref(false);

    function pushMessage({ role, kind, text, subtext = null, answer = null }) {
        messages.value.push({
            id: nextMessageId++,
            role,
            kind,
            text,
            subtext,
            answer,
        });
    }

    function open() {
        isOpen.value = true;
    }

    function close() {
        isOpen.value = false;
    }

    function toggle() {
        isOpen.value = !isOpen.value;
    }

    function reset() {
        messages.value = [];
        quickReplies.value = INTENT_CHIPS;
        activeStep.value = 'intent_select';
        freeTextEnabled.value = false;
        freeTextPlaceholder.value = '';
    }

    function backToIntentSelect() {
        quickReplies.value = INTENT_CHIPS;
        activeStep.value = 'intent_select';
        freeTextEnabled.value = false;
        freeTextPlaceholder.value = '';
    }

    function previousMonth() {
        const now = new Date();
        let year = now.getFullYear();
        let month = now.getMonth(); // 0-indexed current month

        month -= 1;
        if (month < 0) {
            month = 11;
            year -= 1;
        }

        return `${year}-${String(month + 1).padStart(2, '0')}`;
    }

    function pushError(data) {
        const scopeMessage = data?.errors?.intent?.[0];

        if (scopeMessage) {
            pushMessage({
                role: 'bot',
                kind: 'out_of_scope',
                text: scopeMessage,
                subtext: 'Try one of the options below.',
            });
            return;
        }

        pushMessage({
            role: 'bot',
            kind: 'error',
            text: "Couldn't get an answer right now.",
            subtext: 'Check your connection and try again.',
        });
    }

    async function ask(intent, params) {
        const auth = useAuthStore();
        loading.value = true;

        try {
            const response = await fetch('/api/v1/chatbot/ask', {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${auth.accessToken}`,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ intent, params }),
            });

            const data = await response.json();

            if (!response.ok) {
                pushError(data);
                return;
            }

            pushMessage({
                role: 'bot',
                kind: 'answer',
                text: data.data.headline,
                subtext: data.data.empty_message,
                answer: data.data,
            });
        } catch {
            pushMessage({
                role: 'bot',
                kind: 'error',
                text: "Couldn't get an answer right now.",
                subtext: 'Check your connection and try again.',
            });
        } finally {
            loading.value = false;
            backToIntentSelect();
        }
    }

    async function selectQuickReply(chip) {
        pushMessage({ role: 'user', kind: 'text', text: chip.label });

        if (chip.nextStep === 'upcoming_days') {
            quickReplies.value = UPCOMING_DAYS_CHIPS;
            activeStep.value = 'upcoming_days';
            freeTextEnabled.value = false;
            pushMessage({ role: 'bot', kind: 'text', text: 'How far ahead should I look?' });
            return;
        }

        if (chip.nextStep === 'spending_period') {
            quickReplies.value = SPENDING_PERIOD_CHIPS;
            activeStep.value = 'spending_period';
            freeTextEnabled.value = true;
            freeTextPlaceholder.value = 'Or type a month, e.g. 2026-03';
            pushMessage({ role: 'bot', kind: 'text', text: 'Which month should I summarise?' });
            return;
        }

        const params = chip.paramsFactory === 'previous_month'
            ? { month: previousMonth() }
            : (chip.params ?? {});

        await ask(chip.intent, params);
    }

    async function submitFreeText(value) {
        const trimmed = (value ?? '').trim();

        if (trimmed === '') {
            return;
        }

        pushMessage({ role: 'user', kind: 'text', text: trimmed });

        if (MONTH_PATTERN.test(trimmed)) {
            await ask('monthly_spending', { month: trimmed });
            return;
        }

        pushMessage({
            role: 'bot',
            kind: 'out_of_scope',
            text: 'I can only help with balances, upcoming payments, and monthly spending right now.',
            subtext: 'Try one of the options below.',
        });
        backToIntentSelect();
    }

    return {
        isOpen,
        messages,
        quickReplies,
        activeStep,
        freeTextEnabled,
        freeTextPlaceholder,
        loading,
        open,
        close,
        toggle,
        reset,
        selectQuickReply,
        submitFreeText,
        ask,
    };
});
