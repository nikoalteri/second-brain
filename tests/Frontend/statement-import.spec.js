import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/stores/auth.js', () => ({
    useAuthStore: () => ({ accessToken: 'token-a', user: { settings: {} } }),
}));

const { amountToMinor, columnOptions, guessMapping, needsDecision, rowNotes, isPostable } = await import('@/utils/statementImport.js');
const { useStatementImportStore } = await import('@/stores/statementImport.js');

const preview = (...rows) => rows.map((cells, index) => ({ row_number: index + 1, cells }));
const json = (body, status = 200) => ({ ok: status < 400, status, json: async () => body });

describe('guessMapping', () => {
    it('reads an English header with ISO dates and dot decimals', () => {
        const mapping = guessMapping(preview(['Date', 'Amount', 'Description'], ['2026-10-01', '-12.50', 'Coffee']));

        expect(mapping).toMatchObject({
            header_row: 1, date_column: 0, amount_column: 1, description_column: 2,
            date_format: 'Y-m-d', decimal_separator: '.', amount_mode: 'single',
        });
    });

    it('reads an Italian header with split debit/credit and European numbers', () => {
        const mapping = guessMapping(preview(
            ['Data', 'Descrizione', 'Dare', 'Avere'],
            ['31/10/2026', 'Affitto', '1.234,56', ''],
        ));

        expect(mapping).toMatchObject({
            header_row: 1, date_column: 0, description_column: 1, debit_column: 2, credit_column: 3,
            amount_mode: 'split', date_format: 'd/m/Y', decimal_separator: ',', thousands_separator: '.',
        });
    });

    it('recognises month-first dates and a file without a header', () => {
        const mapping = guessMapping(preview(['10/31/2026', '-5.00', 'x']));

        expect(mapping.header_row).toBe(0);
        expect(mapping.date_format).toBe('m/d/Y');
    });
});

describe('row helpers', () => {
    it('parses editable amounts strictly', () => {
        expect(amountToMinor('-12.5')).toBe(-1250);
        expect(amountToMinor('7')).toBe(700);
        expect(amountToMinor('1.234')).toBeNull();
        expect(amountToMinor('abc')).toBeNull();
    });

    it('labels columns from the header or sample', () => {
        expect(columnOptions(preview(['Date', 'Amount'], ['1', '2']), 1)).toEqual([
            { value: 0, label: 'A: Date' }, { value: 1, label: 'B: Amount' },
        ]);
    });

    it('flags rows that need a decision and explains why', () => {
        const flagged = { status: 'draft', normalized: {}, duplicate_class: 'exact', flags: { codes: ['possible_transfer'] } };
        const clean = { status: 'draft', normalized: {}, duplicate_class: 'none', flags: { codes: ['within_file_duplicate'] } };

        expect(needsDecision(flagged)).toBe(true);
        expect(needsDecision(clean)).toBe(false);
        expect(rowNotes(flagged)).toEqual(['Likely duplicate of an existing entry', 'Looks like a transfer between your accounts']);
        expect(isPostable({ status: 'draft', normalized: {}, error: null })).toBe(true);
        expect(isPostable({ status: 'draft', normalized: null, error: 'bad' })).toBe(false);
        expect(isPostable({ status: 'confirmed', normalized: {} })).toBe(false);
    });
});

describe('statement import store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useStatementImportStore();
    });

    const batch = (status = 'uploaded') => ({ id: 7, status, currency: 'EUR', preview: preview(['Date'], ['2026-10-01']) });

    it('uploads the file with the bearer token and moves to the mapping step', async () => {
        const fetchMock = vi.fn().mockResolvedValue(json({ data: batch(), summary: { rows: 2 }, saved_mapping: null }, 201));
        globalThis.fetch = fetchMock;

        await store.upload({ file: new File(['a'], 's.csv'), accountId: '3', endingBalance: '10.5' });

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toBe('/api/v1/import-batches');
        expect(options.headers.Authorization).toBe('Bearer token-a');
        expect(options.body.get('account_id')).toBe('3');
        expect(options.body.get('statement_ending_balance')).toBe('10.5');
        expect(options.body.has('credit_card_id')).toBe(false);
        expect(store.step).toBe('mapping');
    });

    it('applies a mapping, loads the rows and reaches review, then done after the last confirmation', async () => {
        store.batch = batch();
        globalThis.fetch = vi.fn()
            .mockResolvedValueOnce(json({ data: batch('mapped'), summary: { ready: 1 } }))
            .mockResolvedValueOnce(json({ data: [{ id: 1, status: 'draft' }], meta: { next_cursor: 'abc' } }));

        await store.applyMapping({ date_column: 0 });
        expect(store.step).toBe('review');
        expect(store.rows).toHaveLength(1);
        expect(store.nextCursor).toBe('abc');

        globalThis.fetch = vi.fn()
            .mockResolvedValueOnce(json({ data: { status: 'completed', outcomes: [{ row_id: 1, outcome: 'confirmed', reason: null }] }, summary: { confirmed: 1 } }))
            .mockResolvedValueOnce(json({ data: [{ id: 1, status: 'confirmed' }], meta: {} }));
        await store.confirm({ allReady: true });

        expect(JSON.parse(globalThis.fetch.mock.calls[0][1].body)).toEqual({ all_ready: true });
        expect(store.step).toBe('done');
        expect(store.outcomes[0].outcome).toBe('confirmed');
    });

    it('turns a validation error into messages without leaving the step', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(json({ message: 'The file is too large.', errors: { file: ['The file is too large.'] } }, 422));

        const result = await store.upload({ file: new File(['a'], 's.csv'), accountId: '3' });

        expect(result.ok).toBe(false);
        expect(store.error).toBe('The file is too large.');
        expect(store.fieldErrors.file).toEqual(['The file is too large.']);
        expect(store.step).toBe('upload');
    });

    it('discards a response that arrives after logout', async () => {
        let resolve;
        globalThis.fetch = vi.fn().mockReturnValue(new Promise((r) => { resolve = r; }));

        const pending = store.upload({ file: new File(['a'], 's.csv'), accountId: '3' });
        store.reset(); // logout
        resolve(json({ data: batch(), summary: null }, 201));
        await pending;

        expect(store.batch).toBeNull();
        expect(store.loading).toBe(false);
        expect(store.error).toBeNull();
    });

    it('deletes an unfinished batch from the server when starting over', async () => {
        store.batch = batch('mapped');
        globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 204, json: async () => { throw new Error('no body'); } });

        await store.discardBatch();

        expect(globalThis.fetch.mock.calls[0][1].method).toBe('DELETE');
        expect(store.batch).toBeNull();
        expect(store.step).toBe('upload');
    });
});
