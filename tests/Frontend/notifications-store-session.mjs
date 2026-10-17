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
const { useNotificationsStore } = await import('../../resources/js/stores/notifications.js');

setActivePinia(createPinia());
const store = useNotificationsStore();

function deferred() {
    let resolve;
    const promise = new Promise((r) => { resolve = r; });
    return { promise, resolve };
}

const jsonResponse = (body) => ({ ok: true, status: 200, json: async () => body });

// A request started by user A that completes after logout must not repopulate the store.
const pendingList = deferred();
globalThis.fetch = () => pendingList.promise;

const inFlight = store.fetchNotifications();
store.reset(); // logout of user A
pendingList.resolve(jsonResponse({ data: [{ id: 1, title: 'A private alert', read_at: null }], unread_count: 1 }));
await inFlight;

assert.deepEqual(store.items, []);
assert.equal(store.unreadCount, 0);
assert.equal(store.loading, false);
assert.equal(store.error, false);

// A failure from the previous session must not flag an error for the next one.
const pendingFail = deferred();
globalThis.fetch = () => pendingFail.promise;
const failing = store.fetchNotifications();
store.reset();
pendingFail.resolve({ ok: false, status: 500, json: async () => ({}) });
await failing;
assert.equal(store.error, false);

// A mark-read response from the previous session must not touch the next session's list.
globalThis.fetch = () => Promise.resolve(jsonResponse({ data: [{ id: 5, title: 'B', read_at: null }], unread_count: 1 }));
await store.fetchNotifications();
const pendingRead = deferred();
globalThis.fetch = () => pendingRead.promise;
const marking = store.markRead({ id: 5, read_at: null });
store.reset();
globalThis.fetch = () => Promise.resolve(jsonResponse({ data: [{ id: 5, title: 'B', read_at: null }], unread_count: 1 }));
await store.fetchNotifications();
pendingRead.resolve(jsonResponse({ data: { id: 5, title: 'B', read_at: '2026-10-17T00:00:00Z' } }));
await marking;
assert.equal(store.items[0].read_at, null);
assert.equal(store.unreadCount, 1);

// The current session still works normally.
globalThis.fetch = () => Promise.resolve(jsonResponse({ data: [{ id: 9, title: 'Fresh', read_at: null }], unread_count: 1 }));
await store.fetchNotifications();
assert.equal(store.items.length, 1);
assert.equal(store.unreadCount, 1);

console.log('Notifications store session test passed.');
