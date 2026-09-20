import { ApolloClient, HttpLink, InMemoryCache, from } from '@apollo/client/core';
import { setContext } from '@apollo/client/link/context';
import { onError } from '@apollo/client/link/error';
import { fromPromise } from '@apollo/client/link/utils';
import { useAuthStore } from '@/stores/auth.js';

const authLink = setContext((_, { headers }) => {
    const token = localStorage.getItem('fluxa_access_token');

    return {
        headers: {
            ...headers,
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    };
});

// Captured before any patching below, and used for the refresh call itself, so a dead refresh
// token can never recurse back into the patched fetch() and try to "refresh" the refresh.
const originalFetch = window.fetch.bind(window);

function resetAuth() {
    localStorage.removeItem('fluxa_access_token');
    localStorage.removeItem('fluxa_refresh_token');
    window.location.href = '/login';
}

let refreshPromise = null;

/**
 * Single-flight token refresh shared by GraphQL requests (errorLink below) and REST requests (the
 * patched fetch() below), so a GraphQL call and a REST call that expire at the same moment don't
 * each try to rotate the refresh token and race each other.
 *
 * Resolves true once the session has a fresh access token the caller can retry with, or false if
 * the session is dead — resetAuth() has already redirected to /login in that case.
 */
async function performTokenRefresh() {
    if (refreshPromise) {
        return refreshPromise;
    }

    refreshPromise = (async () => {
        try {
            const refreshToken = localStorage.getItem('fluxa_refresh_token');

            if (!refreshToken) {
                throw new Error('No refresh token');
            }

            const response = await originalFetch('/api/v1/auth/refresh', {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${refreshToken}`,
                    Accept: 'application/json',
                },
            });

            if (response.status === 409) {
                // Another tab rotated the tokens a moment ago: it has stored the new ones, so wait
                // for them instead of treating this as a failed session.
                await new Promise((wait) => setTimeout(wait, 700));

                if (localStorage.getItem('fluxa_refresh_token') === refreshToken) {
                    throw new Error('Refresh failed');
                }

                return true;
            }

            if (!response.ok) {
                throw new Error('Refresh failed');
            }

            const data = await response.json();
            // Goes through the auth store (not a direct localStorage write) so every view reading
            // auth.accessToken picks up the new token immediately — a direct write here would
            // leave the store's reactive copy stale until a full page reload. The refresh token
            // is single use: keep the new one, or the next refresh looks like the reuse of a
            // stolen token and ends the session.
            useAuthStore().setTokens(data.access_token, data.refresh_token);

            return true;
        } catch {
            resetAuth();
            return false;
        } finally {
            refreshPromise = null;
        }
    })();

    return refreshPromise;
}

/**
 * True for both a transport-level 401 and a Lighthouse @guard failure — the latter comes back as
 * an HTTP 200 with an "Unauthenticated." error in the GraphQL response body, not a network error,
 * so it needs its own check or an expired token never triggers a refresh for GraphQL requests.
 */
function isAuthError({ networkError, graphQLErrors }) {
    const networkStatus = networkError?.statusCode ?? networkError?.response?.status ?? networkError?.status;

    if (networkStatus === 401) {
        return true;
    }

    return (graphQLErrors ?? []).some((error) => error.extensions?.guards || error.message === 'Unauthenticated.');
}

const errorLink = onError((errorResponse) => {
    if (!isAuthError(errorResponse)) {
        return undefined;
    }

    const { operation, forward } = errorResponse;

    // Must return an Observable, not a Promise — Apollo's onError link calls .subscribe() on
    // whatever comes back. fromPromise()/filter()/flatMap() is Apollo's own documented pattern
    // for "refresh a token, then retry" for exactly this reason.
    return fromPromise(performTokenRefresh())
        .filter((refreshed) => refreshed === true)
        .flatMap(() => forward(operation));
});

const httpLink = new HttpLink({ uri: '/graphql' });

export const apolloClient = new ApolloClient({
    link: from([authLink, errorLink, httpLink]),
    cache: new InMemoryCache(),
    defaultOptions: {
        watchQuery: {
            fetchPolicy: 'cache-and-network',
        },
    },
});

export function clearApolloCache() {
    return apolloClient.clearStore();
}

/**
 * Patches the global fetch() so every existing REST call site (/api/v1/...) transparently gets
 * the same refresh-and-retry treatment as GraphQL requests, without having to touch the ~20 views
 * that call fetch() directly. Plain fetch() has no such retry on its own, so once the access
 * token expires every REST-backed form (subscriptions, loans, credit cards, saving goals,
 * accounts, ...) failed outright until a full page reload.
 *
 * Only retries a request that already carried an Authorization header — login, register, and
 * similar pre-auth endpoints legitimately return 401 for bad credentials with no such header, and
 * must reach the caller as-is rather than being treated as an expired session.
 */
window.fetch = async function fluxaFetch(input, init = {}) {
    const response = await originalFetch(input, init);
    const hadAuthHeader = !!init?.headers?.Authorization;

    if (!hadAuthHeader || response.status !== 401) {
        return response;
    }

    const refreshed = await performTokenRefresh();

    if (!refreshed) {
        return response;
    }

    const freshToken = localStorage.getItem('fluxa_access_token');

    return originalFetch(input, {
        ...init,
        headers: {
            ...init.headers,
            ...(freshToken ? { Authorization: `Bearer ${freshToken}` } : {}),
        },
    });
};
