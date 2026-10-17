---
phase: 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers
plan: 02
status: complete
completed: 2026-10-17
branch: feat/notification-triggers
commit: 20a976d
depends_on: feat/notification-conditions (22-01)
---

# Summary 22-02 — financial triggers and daily digest

## Trigger behavior (`FinanceNotificationEvaluator`, explicit `userId` + date, read-only)

| Trigger | Condition | Severity | Subject / period |
|---|---|---|---|
| `budget` | `BudgetService::getMonthlyOverview()` `alert_status` for the date's month | warning / exceeded / critical as returned | category / `YYYY-MM` |
| `credit_card_limit` | active card, `credit_limit > 0`, `current_balance >= 90%` of limit | warning | card / `open` |
| `credit_card_payment` | pending payment, `due_date <= date`, active card owned by the user | warning (due today), critical (overdue) | payment / due date |
| `loan_payment` | pending payment, `due_date <= date + 3 days`, active loan owned by the user | warning, critical when overdue | payment / due date |
| `subscription_renewal` | active subscription, `next_renewal_date` in 0–3 days | warning | subscription / renewal date |

Payments are reached through their parent card/loan (no `user_id` on payment rows). No-limit and zero-limit cards never notify. A card payment due today that becomes overdue escalates to one more (critical) notification.

## Digest and schedule

- `finance:notifications-digest {--date=}` closure in `routes/console.php`, scheduled `dailyAt('02:05')` with `withoutOverlapping()`, after loans (01:50), subscriptions (01:55) and cards (02:00).
- `FinanceNotificationDigest` iterates active users by explicit id; evaluates first and reconciles only on success, so a failed evaluation resolves nothing. Per-owner failures are logged; the command returns failure if any owner failed.
- Retention: read notifications older than 90 days (by `read_at`, including soft-deleted) are permanently deleted. Unread notifications and condition rows are kept, so cleanup never reopens a condition.

## Evidence

- `tests/Feature/FinanceNotificationDigestTest.php`: 12 tests, green, real budget/transaction, card/payment, loan/payment and subscription rows for two owners. Covers thresholds and date edges, no duplicates on reruns, budget escalation/resolution/reactivation, payment and renewal resolution, a failing owner not stopping another (command exits failure), inactive users, retention, schedule, and running with no authenticated user.
- Full suite: 572 tests; only `ExampleTest` fails (missing `public/build/manifest.json`), identical without these changes. Runs need `APP_KEY` set (no `.env` locally).

## Gaps and notes

- No separate manual `artisan` dry run; the command is exercised by the tests only.
- The SPA has no dedicated budget page, so budget notifications link to `/home`; revisit in 22-03.
- Overdue loan installments left `pending` in real data will each raise one critical notification on the first run.
- MySQL concurrency gap from 22-01 still applies.
