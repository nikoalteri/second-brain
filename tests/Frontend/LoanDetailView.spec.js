import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/stores/auth.js', () => ({
    useAuthStore: () => ({ accessToken: 'token-a', user: { settings: {} } }),
}));
vi.mock('@/i18n/index.js', () => ({ resolveBrowserLocale: () => 'en-US' }));
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '7' } }) }));

const { default: LoanDetailView } = await import('@/views/loans/LoanDetailView.vue');

const json = (body, status = 200) => ({ ok: status < 400, status, json: async () => body });

const loan = (statuses) => ({
    id: 7, name: 'Car loan', status: 'active', interest_rate: 3, total_installments: statuses.length,
    paid_installments: statuses.filter((s) => s === 'paid').length, remaining_amount: 200,
    payments: statuses.map((status, index) => ({ id: 100 + index, due_date: `2026-1${index}-01`, actual_date: status === 'paid' ? '2026-10-01' : null, amount: 100, status })),
});

async function render(initial) {
    setActivePinia(createPinia());
    globalThis.fetch = vi.fn().mockResolvedValueOnce(json({ data: initial }));
    const wrapper = mount(LoanDetailView, {
        global: { stubs: { AppLayout: { template: '<div><slot /></div>' }, 'router-link': { template: '<a><slot /></a>' } } },
    });
    await flushPromises();

    return wrapper;
}

describe('LoanDetailView installments', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('offers "Mark paid" only on pending installments', async () => {
        const wrapper = await render(loan(['paid', 'pending', 'pending']));

        const buttons = wrapper.findAll('button[aria-label^="Mark installment"]');
        // Rendered twice: the table (sm and up) and the mobile list.
        expect(buttons.map((b) => b.attributes('aria-label'))).toEqual([
            'Mark installment 2 as paid', 'Mark installment 3 as paid',
            'Mark installment 2 as paid', 'Mark installment 3 as paid',
        ]);
    });

    it('confirms one installment with the API and shows the updated schedule', async () => {
        const wrapper = await render(loan(['pending', 'pending']));
        globalThis.fetch.mockResolvedValueOnce(json({ data: loan(['paid', 'pending']) }));

        await wrapper.find('button[aria-label="Mark installment 1 as paid"]').trigger('click');
        await flushPromises();

        const [url, options] = globalThis.fetch.mock.calls[1];
        expect(url).toBe('/api/v1/loans/7/payments/100/mark-paid');
        expect(options.method).toBe('POST');
        expect(options.headers.Authorization).toBe('Bearer token-a');
        expect(wrapper.findAll('button[aria-label^="Mark installment"]').map((b) => b.attributes('aria-label')))
            .toEqual(['Mark installment 2 as paid', 'Mark installment 2 as paid']);
        expect(wrapper.text()).toContain('1 paid');
    });

    it('keeps the installment pending when the request fails', async () => {
        const wrapper = await render(loan(['pending']));
        globalThis.fetch.mockResolvedValueOnce(json({ message: 'nope' }, 500));

        await wrapper.find('button[aria-label="Mark installment 1 as paid"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('button[aria-label="Mark installment 1 as paid"]').exists()).toBe(true);
        expect(wrapper.find('button[aria-label="Mark installment 1 as paid"]').attributes('disabled')).toBeUndefined();
    });
});
