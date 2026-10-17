---
phase: 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers
plan: 01
status: complete
completed: 2026-10-17
branch: feat/notification-conditions
commit: 6de1431
---

# Summary 22-01 — durable notification condition lifecycle

## Schema

- `notification_conditions`: owner FK (cascade), `trigger` (40), `subject_type` (60), `subject_id`, `period_key` (32, non-null), `active`, `current_severity`, `max_notified_severity`, `episode` (default 1), `activated_at`, `resolved_at`. Unique `(user_id, trigger, subject_type, subject_id, period_key)`; index `(user_id, active)`.
- `notifications` gained nullable `related_model`, `related_id` (missing from the original migration), `notification_condition_id` (FK, `nullOnDelete`), `episode`, `severity`, with unique `(notification_condition_id, episode, severity)`. Existing rows and the `type` enum are untouched.

## Lifecycle rules (`NotificationConditionPublisher`)

- New condition: episode 1, one notification.
- Same or lower severity while active: no notification; `current_severity` follows the evaluation.
- Higher severity than `max_notified_severity` in the episode: one escalation notification.
- `reconcile($userId, $triggers, $evaluated)`: active conditions of those triggers missing from the evaluated set become resolved. Call it only after the owner's evaluation completed.
- Resolved condition true again: episode + 1, `max_notified_severity` reset, one fresh notification.
- Reading, soft-deleting or force-deleting a notification never changes condition state. `ConditionSpec` accepts only application-relative `action_url` values.
- Severity order: `warning < exceeded < critical` (`NotificationSeverity`).

## Evidence

- `tests/Feature/NotificationConditionPublisherTest.php`: 13 tests, 44 assertions, green. Covers same-day and next-day reruns, read, soft/force-deleted notification, escalation, falling severity, resolution and reactivation, other triggers untouched, owner separation, constant period key, URL validation, unique-key rejection, legacy rows.
- Full suite: 560 tests; the only failure is `ExampleTest` (missing `public/build/manifest.json`), which fails identically without this change. Run needs `APP_KEY` set (no `.env` locally).

## Gaps

- **MySQL not verified.** MySQL was unavailable locally and SQLite ignores `FOR UPDATE`. The invariant rests on the two unique keys plus a single retry on `UniqueConstraintViolationException`; the retry path and the lock are not covered by a test. A two-connection MySQL concurrency test remains a deployment verification item.
