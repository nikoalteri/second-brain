---
phase: 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers
plan: 03
status: implemented-pending-browser-check
completed: 2026-10-17
branch: feat/notification-center
commit: c673f66
depends_on: feat/notification-triggers (22-02)
---

# Summary 22-03 — owner-facing REST, SPA bell, Filament view

## REST (`NotificationController`, `Api\NotificationResource`)

- `GET /api/v1/notifications` (read limiter): newest first, cursor-paginated, `per_page` default 20 and capped at 100, plus `unread_count`. In-app type only; soft-deleted rows hidden.
- `PATCH /api/v1/notifications/{id}/read` (write limiter, numeric id): sets `read_at` once; repeats keep the first timestamp. A foreign or unknown id returns 404.
- Both filter by `request->user()->id` explicitly (`withoutUserScope()`), so a superadmin only sees and changes their own. Client input (owner, title, URL) is ignored.
- Response fields: `id`, `title`, `message`, `type`, `severity`, `read_at`, `created_at`, `action_url` (null unless an application-relative path).

## SPA

- `NotificationBell.vue` in `AppNavbar.vue` (header cluster, visible on desktop and mobile): unread badge, finite list (20), loading/empty/error-with-retry states, `aria-expanded`, sr-only unread marker. Fetches on mount and on each open.
- Opening an item marks it read (badge decrements) and navigates only if the path is internal and the router resolves it; otherwise it just marks read.
- `stores/notifications.js` is reset on logout via `auth.clearTokens()`. English labels added to `i18n/messages/en.js`. `UserSetting::KEY_NOTIFICATIONS` is untouched (toast preference only; the center does not read it).

## Filament (existing `NotificationResource`, no duplicate)

- Index only: create/edit pages and the form were removed. Table: owner (superadmin only), severity badge, title, message, read state, created, unread filter. Nav group Finance with unread badge.
- New `NotificationPolicy`: viewAny for panel users, view own (superadmin any), create/update/delete/restore/forceDelete denied. Ordinary admins see only their own via the model scope; superadmin sees all read-only.
- "Mark read" (row and bulk) acts only on the signed-in user's own notifications.
- `AdminPanelScopingTest::test_panel_user_cannot_open_another_users_notification_record` previously opened the removed edit page; it now asserts the same isolation on the list and the edit URL returning 404.

## Evidence

- `tests/Feature/Api/NotificationApiTest.php` (7 tests) and `tests/Feature/Filament/NotificationResourceTest.php` (5 tests): guests, own list/count/order, safe fields, superadmin own-only and 404 on foreign id, idempotent read, ignored client fields, page-size bound and cursor paging, unsafe URLs hidden, panel reachability/isolation, mark-read, no create/edit/delete, panel MFA still enforced.
- Full backend suite: 584 tests, 2122 assertions, all green (needs `APP_KEY`; no `.env` locally). `npm run build` passes.

## Gaps

- **Browser check not done.** The bell and read flow were not exercised on desktop and mobile against seeded data, and the `important_only` toast preference was not checked in a browser (it is not read by the new code). Needs a local `.env`, database and seeded user.
- No JS unit test for `isInternalPath`/the store (no frontend test runner beyond the i18n smoke script).
- ROADMAP/STATE are not updated yet: per the plan, only after verification completes, so they wait for the browser check.
- MySQL concurrency gap from 22-01 still applies.
