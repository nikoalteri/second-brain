# Phase 22: Proactive notifications - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

The `Notification` model and `notifications` table already exist (`app/Models/Notification.php`, fields: `title`, `message`, `type`, `read_at`, `action_url`, `related_model`, `related_id`) but nothing in the codebase writes to them — confirmed via `grep -rn "Notification::create" app/` returning zero matches. This phase wires four real financial triggers into actual `Notification` rows and gives the user (single-user, personal-use deployment) a way to see them in-app. No new capability beyond these four triggers + in-app surfacing — email delivery, per-user-configurable thresholds, and additional trigger types are explicitly deferred (see below).

</domain>

<decisions>
## Implementation Decisions

### Triggers (all four in scope for this phase)
- **D-01:** Budget threshold — reuse `BudgetAlertService::resolveStatus()` unchanged. Notify when a category's status is `warning` (ratio >= 0.8) or worse (`exceeded`, `critical`). No new threshold logic.
- **D-02:** Credit card — notify when `current_balance >= 0.90 * credit_limit` (fixed 90% threshold, not configurable), and when a `CreditCardPayment` is at or past its due date and not yet `PAID`.
- **D-03:** Loan — notify when a `LoanPayment` is due within 3 days (fixed threshold, not configurable).
- **D-04:** Subscription — notify when a `Subscription` renewal is due within 3 days (fixed threshold, not configurable). This also lays the groundwork Phase 23 (price-hike detection) will reuse.

### Delivery channel
- **D-05:** In-app only — SPA (bell/badge) + visible in Filament admin. No email. Rationale: personal single-user tool, no need for email infrastructure.

### Timing
- **D-06:** Daily digest via one new scheduled Artisan command, following the existing pattern (`loans:sync-installments` 01:50, `subscriptions:sync-renewals` 01:55, `credit-cards:generate-cycles --issue-ready` 02:00). No real-time/observer-based notification generation in this phase — everything is evaluated once a day.

### Deduplication
- **D-07:** One notification per *active* condition, not one per day. While a condition remains true (e.g., a category still over budget in the same month, a card still >=90% of its limit), the digest must not create a new `Notification` row every run. The planner/researcher must design an idempotency key (e.g., a composite of trigger type + subject id + period, checked against existing unread-or-recent `Notification.related_model`/`related_id` before inserting) — exact mechanism is implementation discretion, not decided here.
- Once a condition resolves (e.g., budget no longer exceeded, card balance drops below 90%) and later re-triggers, a new notification is expected — this is a "one active notification per open condition," not a permanent one-time-ever alert.

### Read/unread lifecycle
- **D-08:** Clicking/opening a notification marks it read (`read_at` set) — no bulk "mark all read" requirement stated, planner may add if trivial.
- **D-09:** Automatic cleanup of old **read** notifications after a retention window. Exact window (e.g., 30/60/90 days) is the agent's discretion — pick a reasonable default and document it; this is not user-facing configuration in this phase.

### The agent's Discretion
- Exact idempotency-key mechanism for D-07 (dedup)
- Retention window for D-09 (read notification cleanup)
- Whether cleanup runs as part of the new digest command or a separate scheduled command
- Exact Filament UI presentation (dedicated resource page vs. a widget) as long as notifications are visibly reachable from the admin panel
- Exact SPA bell/badge component structure, as long as it shows an unread count and a list

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing (unused) notification infrastructure
- `app/Models/Notification.php` — model to write to; already has `HasUserScoping`, `SoftDeletes`, `read_at` cast to datetime

### Trigger data sources
- `app/Services/BudgetAlertService.php` — `resolveStatus()` returns `none|ok|warning|exceeded|critical`; reuse directly, do not reimplement threshold logic
- `app/Services/BudgetService.php` — `getMonthlyOverview()` shows how budget-vs-spend is already aggregated per user/category/month
- `app/Models/CreditCard.php`, `app/Models/CreditCardPayment.php` — `current_balance`, `credit_limit`, payment `status`/due date fields
- `app/Models/Loan.php`, `app/Models/LoanPayment.php` — installment due-date fields
- `app/Models/Subscription.php` — renewal due-date fields

### Scheduled-command pattern to follow
- `routes/console.php` lines ~16-133 — existing `Artisan::command(...)` closures and the `Schedule::command(...)->dailyAt(...)` registrations for `loans:sync-installments`, `subscriptions:sync-renewals`, `credit-cards:generate-cycles --issue-ready`. The new digest command should follow this same closure-in-routes/console.php convention, not a new `app/Console/Commands/` class, unless the researcher finds a strong reason to diverge.

### Prior phase precedent for user-scoped background jobs
- `.planning/codebase/CONCERNS.md` — "Console command ambient-authentication narrowing" entry: the three existing scheduled commands use `->withoutUserScope()` and iterate all users explicitly, since cron has no authenticated user in scope. The new digest command must follow the same pattern — do not rely on `auth()->user()`.

No external specs beyond the codebase itself — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `BudgetAlertService::resolveStatus()` — direct reuse for the budget trigger, zero new logic needed
- `UserSetting` pattern (`app/Models/UserSetting.php`, used for display-currency in Phase 20) — available if a later phase wants to make thresholds configurable, deliberately NOT used in this phase (D-01/D-02 keep thresholds fixed)

### Established Patterns
- Scheduled Artisan closures in `routes/console.php`, each with `->withoutUserScope()` and explicit iteration over all users — this phase's new command must match
- `HasUserScoping` trait already applied to `Notification` — scoping for reads is already correct, only writes are missing

### Integration Points
- New digest command in `routes/console.php`, registered in the `Schedule::command(...)` block alongside the existing three
- SPA: needs a new endpoint (REST, matching the existing `/api/v1/*` surface) to list/mark-read notifications, plus a bell/badge component — exact routes are for research/planning to define
- Filament: needs a way to browse notifications — likely a new Filament Resource, following the existing resource patterns under `app/Filament/Resources/`

</code_context>

<specifics>
## Specific Ideas

No specific UI mockups or external references given — the user deferred exact SPA/Filament presentation to implementation discretion (see Decisions).

</specifics>

<deferred>
## Deferred Ideas

- Configurable per-user thresholds (via `UserSetting`, reusing the Phase 20 pattern) — noted as a natural follow-up if fixed thresholds prove wrong in practice, not committed now
- Email delivery — explicitly deferred; in-app only for this phase
- Real-time (observer-based) notification generation for critical events — explicitly deferred in favor of a single daily digest for simplicity
- Phase 23 (subscription price-hike detection) will build directly on this phase's notification delivery — not scoped here, kept as its own phase per ROADMAP.md

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase (it was added directly to ROADMAP.md during this session, not via `/gsd-new-milestone`).

</deferred>

## Audit-derived requirements (added 2026-09-18)

Source: the 2026-09-17 project audit (kept locally under `.planning/audits/2026-09-17/`, not tracked). These are **proposed requirements to settle during `/gsd-plan-phase`**, not decisions already taken; the discuss-phase decisions above stay authoritative unless the maintainer changes them. Foundations already delivered by the audit hardening are listed in ROADMAP.md ("Audit hardening").

- **Track the condition, not the notification row.** "Read" and "condition resolved" are different states. Using unread/recent notifications as the dedup key can re-create a notification right after it was read, or after a cleanup.
- Persist the state of each condition with a unique key per (owner, trigger, subject, period/episode). Reading a notification never reopens its condition.
- Define escalation (warning -> critical), resolution and re-activation explicitly.
- The daily digest must be idempotent (re-running it the same day creates nothing new), take an explicit owner ID instead of relying on ambient authentication, and honour the notification preferences that already exist.

---

*Phase: 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers*
*Context gathered: 2026-09-17*
