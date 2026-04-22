# Phase 7 Closeout Guide

This guide captures the last project steps before considering Phase 7 fully closed.

## 1. Final end-to-end verification

Run a final manual pass across both the SPA and Filament/admin flows and confirm that the same finance rules hold in both interfaces.

### Verify these areas

1. **Accounts**
   - Create, edit, and delete an account.
   - Confirm balances update correctly after transaction changes.
   - Confirm the SPA dashboard shows the correct total balance and account count.

2. **Transactions**
   - Create income, expense, cashback, and transfer transactions.
   - Confirm expenses stay negative, income stays positive, and transfers do not pollute spending/cashflow summaries.
   - Confirm category totals and spending highlights reflect expense activity only.

3. **Loans**
   - Create a loan and generate a schedule.
   - Confirm upcoming installments appear on the dashboard reminders.
   - Confirm due or paid installments post correctly to transactions.

4. **Credit cards**
   - Create a card, add expenses, issue a cycle, and verify issued cycles are frozen.
   - Confirm payments post correctly and dashboard reminders show upcoming card payments.
   - Confirm the SPA dashboard cashflow/payment metrics react correctly to card-payment transactions.

5. **Subscriptions**
   - Create one account-backed subscription and one credit-card-backed subscription.
   - Confirm frequency selection, payment source rules, and reminder behavior.
   - Confirm automatic renewal posting creates either a transaction or a credit-card expense depending on the configured source.

6. **Dashboard**
   - Confirm financial overview values are populated.
   - Confirm **Expenses this month** includes all outgoing transactions, including payment outflows.
   - Confirm cashflow comparison shows income, spending, and payments.
   - Confirm spending highlights exclude transfers and payment transactions.
   - Confirm the net-worth trend stays at zero before the first active month in test/demo datasets.

## 2. Scheduler readiness

Production is not ready unless the Laravel scheduler is running continuously.

### Required recurring jobs

- loan installment sync and posting
- subscription renewal sync and posting
- automatic credit-card cycle issuing

### Minimum check

1. Ensure the deployment environment runs:

```bash
php artisan schedule:work
```

2. Verify scheduled jobs are active for:
   - `loans:sync-installments`
   - `subscriptions:sync-renewals`
   - `credit-cards:generate-cycles --issue-ready`

3. Confirm notifications/postings continue to appear as expected after the scheduler has been running.

## 3. Documentation refresh

If you want generated API docs to reflect the new dashboard payloads and subscription-frequency endpoints, regenerate Scribe output.

```bash
php artisan scribe:generate
```

After generation, review the generated output for:

- dashboard reminder payloads
- dashboard chart payloads
- subscription frequency endpoints
- subscription request/response fields

## 4. Regression pass

Before a release or handoff, run the broader verification pass:

```bash
php artisan test
npm run build
```

If PHPUnit exits non-zero because of existing suite-wide deprecation warnings, review the actual failing tests separately before treating the run as a release blocker.

## 5. Phase 7 completion criteria

Phase 7 can be considered complete when all of the following are true:

- SPA and Filament flows behave consistently for accounts, transactions, loans, credit cards, subscriptions, and dashboard views
- scheduler-driven finance automation is running in the target environment
- generated docs are refreshed if they are part of the release deliverable
- regression checks are acceptable for release
