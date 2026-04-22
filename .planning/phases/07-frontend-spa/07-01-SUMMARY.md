# 07-01 Summary — Infrastructure + Auth

## What shipped

- Vue SPA entrypoint via `resources/views/app.blade.php`, `resources/js/app.js`, and `resources/js/App.vue`
- SPA catch-all route in `routes/web.php`
- Vite Vue setup with JS entrypoint and `@` alias
- Apollo client, Pinia auth store, Vue Router route map, login view
- Shared layouts, navbar, UI primitives, currency/toast composables
- Stub view files for all later phase routes so the app builds incrementally

## Installed packages

From `package.json`:

- `@apollo/client`: `^3.14.1`
- `@heroicons/vue`: `^2.2.0`
- `@vitejs/plugin-vue`: `^6.0.6`
- `@vue/apollo-composable`: `^4.2.2`
- `chart.js`: `^4.5.1`
- `graphql`: `^16.13.2`
- `graphql-tag`: `^2.12.6`
- `pinia`: `^3.0.4`
- `vue`: `^3.5.33`
- `vue-chartjs`: `^5.3.3`
- `vue-router`: `^5.0.6`

## Vite config

- `laravel-vite-plugin` inputs: `resources/css/app.css`, `resources/js/app.js`
- Vue plugin: `@vitejs/plugin-vue`
- Alias: `@ -> resources/js`

## Apollo client configuration

- Import paths:
  - `@apollo/client/core`
  - `@apollo/client/link/context`
  - `@apollo/client/link/error`
- Link chain: `authLink -> errorLink -> httpLink`
- Auth source: `localStorage` keys `fluxa_access_token` and `fluxa_refresh_token`
- GraphQL endpoint: `/graphql`
- Default watch query policy: `cache-and-network`

## Auth store API

- State: `accessToken`, `refreshToken`, `user`, `loading`, `error`
- Computed: `isAuthenticated`
- Actions:
  - `login(email, password)`
  - `logout()`
  - `setTokens(access, refresh?)`
  - `clearTokens()`

## Router routes

- `login`
- `dashboard`
- `accounts`
- `accounts.create`
- `accounts.show`
- `accounts.edit`
- `transactions`
- `transactions.create`
- `transactions.edit`
- `loans`
- `loans.create`
- `loans.show`
- `loans.edit`
- `credit-cards`
- `credit-cards.create`
- `credit-cards.show`
- `credit-cards.edit`
- `subscriptions`
- `subscriptions.create`
- `subscriptions.edit`

## Shared component contracts

- `AppLayout.vue` — navbar + content shell + toast container
- `AuthLayout.vue` — centered auth card shell
- `AppNavbar.vue` — desktop nav + mobile hamburger overlay + logout
- `KpiCard.vue` — props: `label`, `value`, `color`, `delta`
- `DataTable.vue` — props: `loading`, `error`, `empty`, `emptyTitle`, `emptyMessage`, `emptyIcon`, `icon`, `actionLabel`, `actionTo`, `currentPage`, `lastPage`, `total`, `perPage`; emits `page-change`
- `FormInput.vue` — props: `label`, `modelValue`, `type`, `placeholder`, `error`, `disabled`, `required`; emits `update:modelValue`
- `FormSelect.vue` — props: `label`, `modelValue`, `options`, `placeholder`, `error`, `disabled`; emits `update:modelValue`
- `ConfirmModal.vue` — props: `open`, `title`, `message`, `confirmLabel`, `loading`; emits `confirm`, `cancel`
- `LoadingSpinner.vue` — props: `fullPage`, `size`
- `EmptyState.vue` — props: `title`, `message`, `icon`, `actionLabel`, `actionTo`
- `useToast()` — returns `toasts`, `addToast(message, type, duration)`, `removeToast(id)`
- `useCurrency()` — returns `formatCurrency(amount, currency)`, `colorClass(amount, context)`, `formatSigned(amount, currency)`

## Deviations from plan

1. **Apollo version correction:** the plan and research artifacts expected Apollo Client v4, but `@vue/apollo-composable@4.2.2` declares a peer dependency on `@apollo/client ^3.4.13` and failed to build against v4. The implementation uses Apollo Client `^3.14.1` to preserve the planned composable-based architecture without a broken bundle.
2. **Catch-all route fix:** `/{any?}` is optional instead of required so `/` is also served by the SPA entrypoint.
