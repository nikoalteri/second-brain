import { ApolloClient, HttpLink, InMemoryCache, from } from '@apollo/client/core';
import { setContext } from '@apollo/client/link/context';
import { onError } from '@apollo/client/link/error';

const authLink = setContext((_, { headers }) => {
    const token = localStorage.getItem('fluxa_access_token');

    return {
        headers: {
            ...headers,
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    };
});

let isRefreshing = false;
let pendingRequests = [];

function resolvePendingRequests() {
    pendingRequests.forEach((resolve) => resolve());
    pendingRequests = [];
}

function resetAuth() {
    localStorage.removeItem('fluxa_access_token');
    localStorage.removeItem('fluxa_refresh_token');
    pendingRequests = [];
    window.location.href = '/login';
}

const errorLink = onError(({ networkError, operation, forward }) => {
    const statusCode =
        networkError?.statusCode ??
        networkError?.response?.status ??
        networkError?.status;

    if (statusCode !== 401) {
        return undefined;
    }

    if (isRefreshing) {
        return new Promise((resolve) => {
            pendingRequests.push(resolve);
        }).then(() => forward(operation));
    }

    isRefreshing = true;

    return new Promise(async (resolve) => {
        try {
            const refreshToken = localStorage.getItem('fluxa_refresh_token');

            if (!refreshToken) {
                throw new Error('No refresh token');
            }

            const response = await fetch('/api/v1/auth/refresh', {
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

                resolvePendingRequests();
                resolve(forward(operation));
                return;
            }

            if (!response.ok) {
                throw new Error('Refresh failed');
            }

            const data = await response.json();
            localStorage.setItem('fluxa_access_token', data.access_token);
            // The refresh token is single use: keep the new one or the next refresh would look
            // like the reuse of a stolen token and end the session.
            localStorage.setItem('fluxa_refresh_token', data.refresh_token);
            resolvePendingRequests();
            resolve(forward(operation));
        } catch {
            resetAuth();
        } finally {
            isRefreshing = false;
        }
    });
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
