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

            if (!response.ok) {
                throw new Error('Refresh failed');
            }

            const data = await response.json();
            localStorage.setItem('fluxa_access_token', data.access_token);
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
