import assert from 'node:assert/strict';
import { register } from 'node:module';
import { pathToFileURL } from 'node:url';

// Resolve the "@/stores/auth.js" alias to a stub so the store loads without the SPA.
register('data:text/javascript,' + encodeURIComponent(`
export async function resolve(specifier, context, next) {
    if (specifier === '@/stores/auth.js') {
        return { url: 'data:text/javascript,' + encodeURIComponent('export const useAuthStore = () => ({ accessToken: "token-a" });'), shortCircuit: true };
    }
    return next(specifier, context);
}
`), pathToFileURL('./'));

const { createPinia, setActivePinia } = await import('pinia');
const { useCashFlowForecastStore } = await import('../../resources/js/stores/cashFlowForecast.js');
const { coverageMessages, currencyRows, hasEstimate, isIncomplete } = await import('../../resources/js/utils/cashFlowForecast.js');

setActivePinia(createPinia());
const store = useCashFlowForecastStore();

const jsonResponse = (body) => ({ ok: true, status: 200, json: async () => ({ data: body }) });
function deferred() {
    let resolve;
    const promise = new Promise((r) => { resolve = r; });
    return { promise, resolve };
}

const forecast = {
    as_of: '2026-10-17',
    through_date: '2026-11-16',
    horizon_days: 30,
    currencies: [
        { currency: 'EUR', available_balance: '1000.00', counted_commitments: '250.00', estimated_remaining: '750.00', included_account_count: 1, excluded_account_count: 0, obligations: [{ id: 'loan:1:2026-10-25', cash_outflow_amount: '250.00', face_amount: '250.00', reason: null }], overdue_obligations: [], coverage_flags: [], complete: false },
        { currency: 'USD', available_balance: '40.00', counted_commitments: '100.00', estimated_remaining: '-60.00', included_account_count: 1, excluded_account_count: 1, obligations: [{ id: 'loan:2:2026-10-26', cash_outflow_amount: '0.00', face_amount: '70.00', reason: 'already_in_balance' }], overdue_obligations: [{ id: 'loan:3:2026-10-01', face_amount: '10.00', reason: 'overdue_unsettled' }], coverage_flags: ['overdue_obligations'], complete: false },
    ],
    coverage: { complete: false, flags: [{ code: 'overdue_obligations', count: 1 }, { code: 'bank_data_not_reconciled', count: 1 }] },
};

// Currencies stay separate rows; a negative remainder is preserved, not clamped.
const rows = currencyRows(forecast);
assert.deepEqual(rows.map((row) => row.currency), ['EUR', 'USD']);
assert.equal(rows[0].remaining, 750);
assert.equal(rows[1].remaining, -60);
assert.equal(rows[1].obligations[0].reasonLabel, 'Already in the balance');
assert.equal(rows[1].overdue[0].face, 10);

// A partial estimate is never presented as complete, and the gaps are explained.
assert.equal(isIncomplete(forecast), true);
assert.equal(isIncomplete({ coverage: { complete: true } }), false);
assert.equal(coverageMessages(forecast).length, 2);
assert.match(coverageMessages(forecast)[0].text, /Overdue/);
assert.match(coverageMessages({ coverage: { flags: [{ code: 'paid_without_posting', count: 1 }] } })[0].text, /marked paid/);

// No spendable account: an honest empty state instead of a fabricated zero.
assert.equal(hasEstimate({ currencies: [] }), false);
assert.equal(hasEstimate({ currencies: [{ currency: 'EUR', included_account_count: 0 }] }), false);
assert.equal(hasEstimate(forecast), true);

// A response that arrives after logout must not repopulate the store.
const pending = deferred();
globalThis.fetch = () => pending.promise;
const inFlight = store.fetchForecast();
store.reset();
pending.resolve(jsonResponse(forecast));
await inFlight;
assert.equal(store.forecast, null);
assert.equal(store.loading, false);
assert.equal(store.error, false);

// A failure from the previous session must not flag an error for the next one.
const pendingFail = deferred();
globalThis.fetch = () => pendingFail.promise;
const failing = store.fetchForecast();
store.reset();
pendingFail.resolve({ ok: false, status: 500, json: async () => ({}) });
await failing;
assert.equal(store.error, false);

// A normal load stores the forecast; a later failure clears it and sets the isolated error flag.
globalThis.fetch = async () => jsonResponse(forecast);
await store.fetchForecast();
assert.equal(store.forecast.currencies.length, 2);
globalThis.fetch = async () => ({ ok: false, status: 500, json: async () => ({}) });
await store.fetchForecast();
assert.equal(store.forecast, null);
assert.equal(store.error, true);

console.log('cash-flow-forecast: ok');
