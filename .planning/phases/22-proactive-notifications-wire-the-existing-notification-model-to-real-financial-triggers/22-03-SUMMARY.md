---
phase: 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers
plan: 03
status: implemented
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

## Browser check (2026-10-17, local seeded app)

Verified with two seeded users: badge count, panel on desktop and 390 px width, opening an item marks it read and lowers the badge, valid link navigates, unknown-route and external stored links only mark read, the `important_only` toast preference does not hide the panel, and after logout/login as another user only that user's notifications appear. A review round then fixed a cross-session leak in the store, the renewal reminder amount, and a stale-evaluation resolution race (PR #66); a bell/user-menu overlap found in the check was fixed too.

## Gaps

- The read-only Filament list at `/hub/notifications` was not seen in a browser (needs an admin with MFA); it is covered by automated tests only.
- MySQL concurrency: the lifecycle's locking and the stale-evaluation guard are simulated sequentially; a two-connection MySQL test is still to do.
- No JS runner beyond small `.mjs` scripts: `isInternalPath` itself has no dedicated test.
