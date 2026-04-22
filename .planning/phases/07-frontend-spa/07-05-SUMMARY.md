# 07-05 Summary — Subscriptions + Final Polish

## Subscription mutation name confirmation

- `createSubscription`
- `updateSubscription`
- `deleteSubscription`

`createServiceSubscription` is not used.

## Renewal threshold

- `isRenewingSoon` marks renewals due within **7 days**

## Human verification checkpoint result

- **Pending user verification**
- Automated build is green
- Manual verification should cover auth, routing, all five finance domains, mobile menu behavior, delete flows, and the frontend/backend admin handoff

## Verification fixes applied after first manual pass

1. Applied the existing Sanctum migration for `personal_access_tokens` so SPA login/logout works in the local environment.
2. Raised Lighthouse `max_query_complexity` from `100` to `500` so the planned first-party SPA list queries can execute.
3. Aligned SPA account types to the backend enum/Filament resource values: `bank`, `cash`, `investment`, `emergency_fund`, `debt`.
4. Brought SPA forms back into parity with the Filament resource forms across accounts, transactions, loans, credit cards, and subscriptions, including edit-mode fields that previously were not exposed through GraphQL.
5. Added authenticated user bootstrap data plus admin-only frontend/backend navigation, and forced light mode across the SPA shell and Filament panel.

## Full view/component files created across phase 7

### Views

- `resources/js/views/auth/LoginView.vue`
- `resources/js/views/DashboardView.vue`
- `resources/js/views/accounts/AccountsView.vue`
- `resources/js/views/accounts/AccountDetailView.vue`
- `resources/js/views/accounts/AccountFormView.vue`
- `resources/js/views/transactions/TransactionsView.vue`
- `resources/js/views/transactions/TransactionFormView.vue`
- `resources/js/views/loans/LoansView.vue`
- `resources/js/views/loans/LoanDetailView.vue`
- `resources/js/views/loans/LoanFormView.vue`
- `resources/js/views/credit-cards/CreditCardsView.vue`
- `resources/js/views/credit-cards/CreditCardDetailView.vue`
- `resources/js/views/credit-cards/CreditCardFormView.vue`
- `resources/js/views/subscriptions/SubscriptionsView.vue`
- `resources/js/views/subscriptions/SubscriptionFormView.vue`

### Components

- `resources/js/components/layout/AppLayout.vue`
- `resources/js/components/layout/AppNavbar.vue`
- `resources/js/components/layout/AuthLayout.vue`
- `resources/js/components/ui/KpiCard.vue`
- `resources/js/components/ui/DataTable.vue`
- `resources/js/components/ui/FormInput.vue`
- `resources/js/components/ui/FormSelect.vue`
- `resources/js/components/ui/ConfirmModal.vue`
- `resources/js/components/ui/LoadingSpinner.vue`
- `resources/js/components/ui/EmptyState.vue`

## Deviations from plan

1. The summary tracks the human-verification checkpoint as pending because it requires user confirmation outside the automated build.
2. Lighthouse's default `max_query_complexity` cap was too low for the planned first-party SPA list queries, so it was raised from `100` to `500` during verification.
3. The phase ended up with 25 view/component files rather than the plan note's count of 22; the file list above reflects the implemented codebase exactly.
4. Some Filament form capabilities required post-plan GraphQL surface expansion (`Update*Input` parity, transfer destination account support, editable credit-card/subscription fields, and role-aware auth bootstrap) so the SPA could match the existing admin contract instead of keeping a reduced write model.
