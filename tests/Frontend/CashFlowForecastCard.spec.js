import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

// The card only needs a token to exist; the auth store itself pulls in Apollo and the router.
vi.mock('@/stores/auth.js', () => ({
    useAuthStore: () => ({ accessToken: 'token-a', user: { settings: {} } }),
}));
vi.mock('@/i18n/index.js', () => ({ resolveBrowserLocale: () => 'en-US' }));

const { default: CashFlowForecastCard } = await import('@/components/dashboard/CashFlowForecastCard.vue');
const { useCashFlowForecastStore } = await import('@/stores/cashFlowForecast.js');

const obligation = (overrides = {}) => ({
    id: 'loan:1:2026-10-25',
    type: 'loan',
    name: 'Car loan',
    due_date: '2026-10-25',
    face_amount: '250.00',
    cash_outflow_amount: '250.00',
    reason: null,
    ...overrides,
});

const forecast = (overrides = {}) => ({
    as_of: '2026-10-17',
    through_date: '2026-11-16',
    horizon_days: 30,
    currencies: [
        {
            currency: 'EUR', available_balance: '1000.00', counted_commitments: '250.00', estimated_remaining: '750.00',
            included_account_count: 1, excluded_account_count: 1,
            obligations: [obligation(), obligation({ id: 'subscription:2:2026-10-28', type: 'subscription', name: 'Cloud', face_amount: '4.99', cash_outflow_amount: '0.00', reason: 'card_charge_projected' })],
            overdue_obligations: [obligation({ id: 'loan:9:2026-10-01', name: 'Old rate', due_date: '2026-10-01', face_amount: '10.00', reason: 'overdue_unsettled' })],
            coverage_flags: ['overdue_obligations'], complete: false,
        },
        {
            currency: 'USD', available_balance: '40.00', counted_commitments: '100.00', estimated_remaining: '-60.00',
            included_account_count: 1, excluded_account_count: 0,
            obligations: [], overdue_obligations: [], coverage_flags: [], complete: false,
        },
    ],
    unresolved_obligations: [],
    coverage: {
        complete: false,
        flags: [{ code: 'overdue_obligations', count: 1 }, { code: 'bank_data_not_reconciled', count: 1 }],
    },
    ...overrides,
});

function render(state) {
    const store = useCashFlowForecastStore();
    Object.assign(store, state);

    return { store, wrapper: mount(CashFlowForecastCard) };
}

describe('CashFlowForecastCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders one article per currency, each amount in its own currency, with no combined total', () => {
        const { wrapper } = render({ forecast: forecast() });

        const articles = wrapper.findAll('article');
        expect(articles.map((a) => a.attributes('data-currency'))).toEqual(['EUR', 'USD']);
        expect(articles[0].text()).toContain('€750.00');
        expect(articles[0].text()).toContain('€1,000.00');
        expect(articles[1].text()).toContain('-$60.00');
        expect(articles[1].text()).not.toContain('€');
        expect(wrapper.text()).not.toMatch(/total/i);
    });

    it('shows a negative remainder in the warning colour instead of clamping it', () => {
        const { wrapper } = render({ forecast: forecast() });

        const usd = wrapper.find('article[data-currency="USD"]');
        expect(usd.find('p.font-mono').classes()).toContain('text-red-600');
    });

    it('labels the estimate incomplete, lists overdue items apart and states what is not included', () => {
        const { wrapper } = render({ forecast: forecast() });

        expect(wrapper.text()).toContain('Incomplete estimate');
        expect(wrapper.text()).toContain('Overdue, not in the figure');
        expect(wrapper.text()).toContain('Old rate');
        expect(wrapper.text()).toContain('Overdue payments that are not settled are not part of the figure.');
        expect(wrapper.text()).toContain('Bank balances are not reconciled against statements.');
        expect(wrapper.text()).toContain('Future income and untracked spending are not included');
        expect(wrapper.text()).toContain('1 account set aside for savings goals is not included.');
    });

    it('marks a non-deducted item with its reason and shows its face amount in parentheses', () => {
        const { wrapper } = render({ forecast: forecast() });

        expect(wrapper.text()).toContain('Projected card charge, not a bank debit');
        expect(wrapper.text()).toContain('(€4.99)');
    });

    it('shows an honest empty state when no account can be spent from', () => {
        const { wrapper } = render({ forecast: forecast({ currencies: [] }) });

        expect(wrapper.text()).toContain('No forecast available');
        expect(wrapper.findAll('article')).toHaveLength(0);
    });

    it('shows an isolated error with a retry that refetches', async () => {
        const store = useCashFlowForecastStore();
        const fetchForecast = vi.spyOn(store, 'fetchForecast').mockResolvedValue();
        Object.assign(store, { error: true, forecast: null });
        const wrapper = mount(CashFlowForecastCard);

        expect(wrapper.text()).toContain('The forecast could not be loaded');
        expect(wrapper.text()).not.toContain('Incomplete estimate');

        await wrapper.find('button').trigger('click');
        expect(fetchForecast).toHaveBeenCalledTimes(1);
    });

    it('shows only the loading indicator while fetching', () => {
        const { wrapper } = render({ loading: true, forecast: null });

        expect(wrapper.findAll('article')).toHaveLength(0);
        expect(wrapper.text()).not.toContain('No forecast available');
    });
});
