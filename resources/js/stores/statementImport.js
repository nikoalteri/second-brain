import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { useAuthStore } from '@/stores/auth.js';

// Steps of the flow, in order.
export const STEPS = ['upload', 'mapping', 'review', 'done'];

export const useStatementImportStore = defineStore('statementImport', () => {
    const targets = ref({ accounts: [], cards: [] });
    const batch = ref(null);
    const preview = ref([]);
    const savedMapping = ref(null);
    const summary = ref(null);
    const rows = ref([]);
    const nextCursor = ref(null);
    const outcomes = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const fieldErrors = ref({});
    // Bumped on reset(): responses that began under an earlier session are discarded.
    let session = 0;

    const step = computed(() => {
        if (!batch.value) return 'upload';
        if (batch.value.status === 'completed') return 'done';
        if (batch.value.status === 'mapped') return 'review';

        return 'mapping';
    });

    function headers(extra = {}) {
        const auth = useAuthStore();

        return { Authorization: `Bearer ${auth.accessToken}`, Accept: 'application/json', ...extra };
    }

    // One request helper: guards against a session change and turns API errors into state.
    async function request(path, { method = 'GET', json = null, form = null } = {}) {
        const startedIn = session;
        loading.value = true;
        error.value = null;
        fieldErrors.value = {};

        try {
            const response = await fetch(`/api/v1${path}`, {
                method,
                headers: headers(json ? { 'Content-Type': 'application/json' } : {}),
                body: form ?? (json ? JSON.stringify(json) : undefined),
            });
            const body = response.status === 204 ? {} : await response.json().catch(() => ({}));

            if (startedIn !== session) {
                return { stale: true };
            }

            if (!response.ok) {
                fieldErrors.value = body.errors ?? {};
                error.value = body.message ?? `Request failed (${response.status}).`;

                return { ok: false, status: response.status };
            }

            return { ok: true, body };
        } catch {
            if (startedIn === session) {
                error.value = 'The request could not be completed. Check your connection and try again.';
            }

            return { ok: false, status: 0 };
        } finally {
            if (startedIn === session) {
                loading.value = false;
            }
        }
    }

    function adopt(body) {
        batch.value = body.data;
        preview.value = body.data.preview ?? preview.value;
        summary.value = body.summary ?? summary.value;
        savedMapping.value = body.saved_mapping ?? savedMapping.value;
    }

    async function loadTargets() {
        const [accounts, cards] = await Promise.all([
            request('/accounts?per_page=100'),
            request('/credit-cards?per_page=100'),
        ]);

        if (accounts.ok) {
            targets.value.accounts = (accounts.body.data ?? []).filter((account) => account.is_active);
        }

        if (cards.ok) {
            targets.value.cards = cards.body.data ?? [];
        }
    }

    async function upload({ file, accountId = null, creditCardId = null, delimiter = '', endingBalance = '', periodStart = '', periodEnd = '' }) {
        const form = new FormData();
        form.append('file', file);

        if (accountId) form.append('account_id', accountId);
        if (creditCardId) form.append('credit_card_id', creditCardId);
        if (delimiter) form.append('delimiter', delimiter);
        if (endingBalance !== '') form.append('statement_ending_balance', endingBalance);
        if (periodStart) form.append('period_start', periodStart);
        if (periodEnd) form.append('period_end', periodEnd);

        const result = await request('/import-batches', { method: 'POST', form });

        if (result.ok) {
            rows.value = [];
            outcomes.value = [];
            adopt(result.body);
        }

        return result;
    }

    async function applyMapping(mapping) {
        const result = await request(`/import-batches/${batch.value.id}/mapping`, { method: 'PUT', json: mapping });

        if (result.ok) {
            adopt(result.body);
            await loadRows();
        }

        return result;
    }

    async function loadRows({ more = false, filters = {} } = {}) {
        const query = new URLSearchParams({ per_page: '100', ...filters });

        if (more && nextCursor.value) {
            query.set('cursor', nextCursor.value);
        }

        const result = await request(`/import-batches/${batch.value.id}/rows?${query}`);

        if (result.ok) {
            rows.value = more ? [...rows.value, ...result.body.data] : result.body.data;
            nextCursor.value = result.body.meta?.next_cursor ?? null;
        }

        return result;
    }

    async function updateRow(rowId, changes) {
        const result = await request(`/import-batches/${batch.value.id}/rows/${rowId}`, { method: 'PATCH', json: changes });

        if (result.ok) {
            rows.value = rows.value.map((row) => (row.id === rowId ? result.body.data : row));

            // Discarding or restoring (or a re-classification after an edit) can finish the import or
            // change the counts: take the batch's status and summary from the server.
            await refreshBatch();
        }

        return result;
    }

    async function refreshBatch() {
        const result = await request(`/import-batches/${batch.value.id}`);

        if (result.ok) {
            adopt(result.body);
        }

        return result;
    }

    // Ends a partially confirmed import: what is still waiting is discarded and the batch is kept as history.
    async function closeImport() {
        const result = await request(`/import-batches/${batch.value.id}/close`, { method: 'POST' });

        if (result.ok) {
            adopt(result.body);
            await loadRows();
        }

        return result;
    }

    async function confirm({ rowIds = null, allReady = false }) {
        // An explicit choice carries, per row, the review token the server gave with that row: it binds the
        // choice to what the owner was shown, so a row that changed meanwhile is held back instead of posted.
        const reviewed = rowIds
            ? Object.fromEntries(rowIds.map((id) => [id, rows.value.find((row) => row.id === id)?.review_token]).filter(([, token]) => token))
            : null;
        const json = rowIds ? { row_ids: rowIds, reviewed } : { all_ready: allReady };
        const result = await request(`/import-batches/${batch.value.id}/confirm`, { method: 'POST', json });

        if (result.ok) {
            outcomes.value = result.body.data.outcomes;
            summary.value = result.body.summary;
            batch.value = { ...batch.value, status: result.body.data.status };
            await loadRows();
        }

        return result;
    }

    async function discardBatch() {
        if (!batch.value) return { ok: true };

        const result = await request(`/import-batches/${batch.value.id}`, { method: 'DELETE' });

        if (result.ok) {
            clearBatch();
        }

        return result;
    }

    function clearBatch() {
        batch.value = null;
        preview.value = [];
        savedMapping.value = null;
        summary.value = null;
        rows.value = [];
        nextCursor.value = null;
        outcomes.value = [];
        error.value = null;
        fieldErrors.value = {};
    }

    function reset() {
        session += 1;
        targets.value = { accounts: [], cards: [] };
        loading.value = false;
        clearBatch();
    }

    return {
        targets, batch, preview, savedMapping, summary, rows, nextCursor, outcomes, loading, error, fieldErrors,
        step, loadTargets, upload, applyMapping, loadRows, updateRow, refreshBatch, closeImport, confirm, discardBatch, clearBatch, reset,
    };
});
