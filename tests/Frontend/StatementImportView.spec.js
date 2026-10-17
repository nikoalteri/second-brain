import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/stores/auth.js', () => ({
    useAuthStore: () => ({ accessToken: 'token-a', user: { settings: {} } }),
}));
vi.mock('@/i18n/index.js', () => ({ resolveBrowserLocale: () => 'en-US' }));

const { default: StatementImportView } = await import('@/views/transactions/StatementImportView.vue');
const { useStatementImportStore } = await import('@/stores/statementImport.js');

const stubs = { AppLayout: { template: '<div><slot /></div>' }, 'router-link': { template: '<a><slot /></a>' } };

function render(state) {
    const store = useStatementImportStore();
    vi.spyOn(store, 'loadTargets').mockResolvedValue();
    vi.spyOn(store, 'loadRows').mockResolvedValue({ ok: true });
    Object.assign(store, state);

    return { store, wrapper: mount(StatementImportView, { global: { stubs } }) };
}

const row = (id, overrides = {}) => ({
    id, row_number: id + 1, status: 'draft', error: null, duplicate_class: 'none', flags: { codes: [] },
    normalized: { date: '2026-10-01', description: `Row ${id}`, amount_minor: -1250 },
    ...overrides,
});

describe('StatementImportView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('starts at the upload step with accounts and cards to choose from and no submit until ready', async () => {
        const { wrapper } = render({
            targets: { accounts: [{ id: 1, name: 'Main', currency: 'EUR' }], cards: [{ id: 2, name: 'Visa' }] },
        });

        expect(wrapper.text()).toContain('Nothing is added to your accounts until you confirm each row.');
        const options = wrapper.findAll('option').map((o) => o.text());
        expect(options).toContain('Main (EUR)');
        expect(options).toContain('Visa (credit card)');
        expect(options).toContain('Automatic');
        expect(options).toContain('Semicolon ( ; )');
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('shows the API error in an alert', () => {
        const { wrapper } = render({ error: 'The selected account is not available for import.', fieldErrors: { account_id: ['The selected account is not available for import.'] } });

        expect(wrapper.find('[role="alert"]').text()).toContain('The selected account is not available for import.');
    });

    it('lets the user map columns from a preview', () => {
        const { wrapper } = render({
            batch: { id: 1, status: 'uploaded', currency: 'EUR' },
            preview: [{ row_number: 1, cells: ['Date', 'Amount', 'Description'] }, { row_number: 2, cells: ['2026-10-01', '-12.50', 'Coffee'] }],
        });

        expect(wrapper.text()).toContain('A: Date');
        expect(wrapper.text()).toContain('Coffee');
        expect(wrapper.text()).toContain('Apply mapping');
        expect(wrapper.text()).toContain('Reverse the sign');
    });

    it('splits review rows into ready and needs-decision, and confirms only what the user selects', async () => {
        const { store, wrapper } = render({
            batch: { id: 1, status: 'mapped', currency: 'EUR' },
            rows: [
                row(1),
                row(2, { duplicate_class: 'exact', flags: { codes: ['already_imported'] } }),
                row(3, { normalized: null, error: 'The date does not match the chosen format.' }),
            ],
        });
        const confirm = vi.spyOn(store, 'confirm').mockResolvedValue({ ok: true });

        expect(wrapper.text()).toContain('Confirm all ready (1)');
        expect(wrapper.find('[data-row="2"]').exists()).toBe(true);
        expect(wrapper.find('[data-row="3"]').exists()).toBe(false);

        await wrapper.findAll('button').find((b) => b.text() === 'Need decision').trigger('click');
        expect(wrapper.text()).toContain('Likely duplicate of an existing entry');
        expect(wrapper.text()).toContain('Already imported from an earlier file');

        await wrapper.find('input[aria-label="Select row 3"]').setValue(true);
        await wrapper.findAll('button').find((b) => b.text().startsWith('Confirm selected')).trigger('click');
        expect(confirm).toHaveBeenCalledWith({ rowIds: [2] });

        await wrapper.findAll('button').find((b) => b.text() === 'Errors').trigger('click');
        expect(wrapper.text()).toContain('The date does not match the chosen format.');
        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
    });

    it('confirms all ready rows with one click and reports rows that were not posted', async () => {
        const { store, wrapper } = render({
            batch: { id: 1, status: 'mapped', currency: 'EUR' },
            rows: [row(1)],
            outcomes: [{ row_id: 1, outcome: 'failed', reason: 'This billing cycle has already been issued.' }],
        });
        const confirm = vi.spyOn(store, 'confirm').mockResolvedValue({ ok: true });

        expect(wrapper.text()).toContain('Some rows were not posted');
        expect(wrapper.text()).toContain('This billing cycle has already been issued.');

        await wrapper.findAll('button').find((b) => b.text().startsWith('Confirm all ready')).trigger('click');
        expect(confirm).toHaveBeenCalledWith({ allReady: true });
    });

    it('edits a description and discards a row through the store', async () => {
        const { store, wrapper } = render({ batch: { id: 1, status: 'mapped', currency: 'EUR' }, rows: [row(1)] });
        const update = vi.spyOn(store, 'updateRow').mockResolvedValue({ ok: true });

        await wrapper.find('input[aria-label="Description of row 2"]').setValue('Lunch');
        await wrapper.find('input[aria-label="Description of row 2"]').trigger('change');
        expect(update).toHaveBeenCalledWith(1, { description: 'Lunch' });

        await wrapper.findAll('button').find((b) => b.text() === 'Discard').trigger('click');
        expect(update).toHaveBeenCalledWith(1, { status: 'discarded' });
    });

    it('shows the finished state with the counts', () => {
        const { wrapper } = render({ batch: { id: 1, status: 'completed', currency: 'EUR' }, summary: { confirmed: 4, discarded: 1 } });

        expect(wrapper.text()).toContain('Import finished');
        expect(wrapper.text()).toContain('4 rows were added, 1 discarded.');
    });
});
