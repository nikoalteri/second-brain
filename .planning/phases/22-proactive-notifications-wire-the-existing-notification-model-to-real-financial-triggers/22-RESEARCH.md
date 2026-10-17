# Phase 22 — implementation research

**Prepared:** 2026-10-17. Current source is authoritative over earlier planning notes.

## Confirmed integration points

- `app/Models/Notification.php` already uses `HasUserScoping` and `SoftDeletes`. Its fillable `related_model` and `related_id` fields are **absent** from `database/migrations/2026_03_31_211308_create_notifications_table.php`; an additive migration is required before using them.
- `app/Filament/Resources/Notifications/NotificationResource.php` and its list/form/table already exist. Phase 22 should adapt this resource for viewing and marking financial notifications read, rather than create another resource. Its current create/edit/delete actions and unrestricted `user_id` input need an ownership review.
- `BudgetService::getMonthlyOverview($userId, $year, $month)` returns category `alert_status` through `BudgetAlertService::resolveStatus()`. It supplies the current-month budget trigger without a second threshold implementation.
- Credit-card and loan payments store `due_date` and enum `status`; they do not carry `user_id` directly. Ownership must be checked through the parent card/loan. Subscriptions carry `user_id`, `status`, and `next_renewal_date`.
- `routes/console.php` schedules loan sync at 01:50, subscription renewal sync at 01:55, and card cycle generation at 02:00, all with `withoutOverlapping()`. Digest evaluation at 02:05 sees their latest state. Scheduled work must use explicit owner IDs and `withoutUserScope()` where needed.
- REST routes are split into authenticated read and write groups in `routes/api.php`. The SPA navbar is `resources/js/components/layout/AppNavbar.vue`; mobile navigation lives in the same component. The current `UserSetting::KEY_NOTIFICATIONS` choices are explicitly **toast** preferences (`All toasts` / `Errors only`), consumed by `useUserPreferences().allowsToast()`. They are not a preference for suppressing persistent financial notifications. Preserve this meaning; if the new center displays optional toasts, use `allowsToast()` for those toasts only.
- The Obsidian directory named in AGENTS.md is unavailable in this workspace. The checked-in Graphify report was consulted for relevant relationships; the source files above supplied the exact interfaces.

## Decisions for the plan

1. Persist one condition row per `(user_id, trigger, subject_type, subject_id, period_key)` with a database unique constraint, `active`, current severity, highest severity notified in the episode, and an episode counter. Budget period is `YYYY-MM`; a card-limit condition uses a constant period; payment conditions use their due date; subscription renewals use `next_renewal_date`. The row remains after notification cleanup.
2. Compare each owner's fully evaluated condition set with the active rows for the phase's triggers. A newly true condition creates one in-app notification in the same transaction; an unchanged true condition creates none; a severity higher than any previously notified in that episode creates one escalation notification; a condition absent from the latest evaluation becomes resolved; a later true evaluation starts a new episode. Marking a notification read never changes condition state. Uniqueness on notification condition/episode/severity plus row locking/unique-conflict retry makes concurrent manual and scheduled runs safe on MySQL. If evaluation fails before the set is complete, do not resolve any conditions for that owner.
3. Use `warning < exceeded < critical` for budgets. An unpaid card payment due today is `warning` and past due is `critical`. A card at or above 90% of a positive credit limit is `warning`. Loan installments and subscription renewals due in the next 0–3 calendar days are `warning`; overdue unpaid loan installments remain `critical` until paid, while a subscription date that has advanced or is no longer in the window resolves. Do not notify on zero or unlimited card limits.
4. Run the digest once daily at 02:05 with `withoutOverlapping()`, after the existing finance commands. Use an optional `--date=YYYY-MM-DD` argument for deterministic tests and manual recovery. One owner's failure is logged and does not stop other owners; the command exits nonzero if any owner fails.
5. Retain read notifications for 90 days after `read_at`, then delete them permanently during the digest. Unread notifications are retained. Condition rows remain so cleanup cannot reopen a condition.
6. Expose a bounded, newest-first owner-only REST list plus unread count and an idempotent mark-read action. The SPA bell is visible on desktop and mobile, with a finite list and safe internal action links. The existing Filament resource is owner-safe and visibly reachable.

## Risks to verify during implementation

- A digest run after renewal posting may see a subscription's next renewal date rather than today's date. The 3-day warning should have been emitted on earlier runs; a test should cover the date advancing and old condition resolution.
- The current global user scope allows superadmins to see all records. Notification API queries must explicitly filter `user_id = request user id`, including route-bound updates. Filament may deliberately use admin-wide visibility, but it must not offer arbitrary owner reassignment through a form.
- SQLite tests do not prove MySQL `FOR UPDATE` behavior. Add a MySQL two-connection concurrency test if the project test environment supports it; otherwise record this as a deployment verification item and rely on the unique constraint for the invariant.
