---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 03
subsystem: testing
tags: [console-scoping, artisan-commands, HasUserScoping, sanctum, phpunit]

# Dependency graph
requires:
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    provides: Phase 18 research and threat model for D-01/D-04 (auth-scoping and console-scoping gaps)
provides:
  - Database-level proof that all three shipped scheduled commands (credit-cards:generate-cycles, loans:sync-installments, subscriptions:sync-renewals) process every user's records under real unauthenticated console conditions
  - A confirmed, documented D-02-severity finding: credit-cards:generate-cycles silently narrows to one user's cards when run in a process with ambient authentication
affects: [18-hardening-security-proof-close-the-auth-scoping-superadmin-b (remaining plans that may need to fix the ambient-auth finding)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Console command scoping is proven with unauthenticated Artisan tests plus a paired Sanctum::actingAs() test to catch ambient-auth narrowing, instead of mocking auth()"

key-files:
  created: [tests/Feature/ConsoleScopingTest.php]
  modified: []

key-decisions:
  - "Kept the failing ambient-authentication test in place rather than weakening or deleting it, per the plan's explicit instruction to treat a failure there as a confirmed finding"

patterns-established:
  - "Two-user, no-mock console command tests: seed two users' data with no actingAs(), assert both users' rows exist post-command, then repeat under Sanctum::actingAs() to catch scope leakage"

requirements-completed: [D-01, D-04]

# Metrics
duration: 25min
completed: 2026-08-06
---

# Phase 18 Plan 03: Console Scoping Proof Summary

**Added `tests/Feature/ConsoleScopingTest.php` proving all three shipped scheduled commands process every user's records under real (unauthenticated) console conditions, and surfaced a confirmed high-severity bug: `credit-cards:generate-cycles` silently narrows to one user's cards when the process has an authenticated user in scope.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-08-06T00:47:00Z
- **Completed:** 2026-08-06T00:49:06Z
- **Tasks:** 2 completed
- **Files modified:** 1 created

## Accomplishments
- Proved `credit-cards:generate-cycles`, `loans:sync-installments`, and `subscriptions:sync-renewals` all see every user's active/due records when run with no authenticated user (the real cron condition) — the intentional `HasUserScoping` no-op is now a locked, breakage-visible contract.
- Discovered and documented a genuine security/correctness finding: when `credit-cards:generate-cycles` runs in a process where a user happens to be authenticated (e.g. queued from a web request instead of cron), the `user` global scope on `CreditCard` applies and the command silently processes only that one user's cards — other users' credit card cycles would not be generated, drifting balances undetected.
- No mocking of `auth()` was used anywhere in the new test file (verified via `grep -c "Mockery\|mock("` = 0), matching the plan's requirement that the no-op branch be exercised naturally.

## Task Commits

Each task was committed atomically:

1. **Task 1: Prove credit-cards:generate-cycles processes every user's active cards** - `3b17961` (test)
2. **Task 2: Prove loans:sync-installments and subscriptions:sync-renewals process every user's records** - `622b9c9` (test)

**Plan metadata:** (SUMMARY commit — recorded by orchestrator wave-completion process; this plan runs in worktree isolation and does not touch STATE.md/ROADMAP.md)

## Files Created/Modified
- `tests/Feature/ConsoleScopingTest.php` - Four tests covering console-context scoping for all three shipped scheduled commands: unauthenticated two-user proof for each command, plus an ambient-authentication regression test for `credit-cards:generate-cycles` that currently fails (documented finding, not a test bug).

## Decisions Made
- Kept the ambient-authentication test failing rather than adjusting it to pass, per explicit plan instruction: a failure there is the actual high-value finding surface, and weakening it would hide a real bug from future maintainers.
- Used the codebase's established explicit-attribute `CreditCard::create([...])` idiom (matching `CreditCardLifecycleIntegrationTest`) rather than the factory, since the factory route was equally acceptable per the plan but the explicit form matches existing conventions for this exact scenario.
- Verified `period_month` format (`'2026-03'`) and `loan_payments` table name from `CreditCardCycleService`/`Loan::payments()` before asserting on them, per plan instruction not to guess.

## Deviations from Plan

None - plan executed exactly as written, including keeping the documented failing test as specified.

## Issues Encountered

**Confirmed finding (not a deviation, explicitly anticipated by the plan):** `test_generate_cycles_command_is_unaffected_by_ambient_authentication` fails. Under `Sanctum::actingAs($accountA->user)`, `credit-cards:generate-cycles --month=2026-03` only creates a `credit_card_cycles` row for `$cardA` (the authenticated user's card); `$cardB` (a different user's card) is never processed:

```
Failed asserting that a row in the table [credit_card_cycles] matches the attributes {
    "credit_card_id": 2,
    "period_month": "2026-03"
}.

Found: [
    {
        "credit_card_id": 1,
        "period_month": "2026-03"
    }
].
```

**Root cause:** `HasUserScoping`'s `user` global scope applies `where('user_id', auth()->id())` whenever `auth()->check()` is true, with no awareness of whether the current process is an HTTP request or a console command. `CreditCard::query()` in `routes/console.php` has no explicit `user_id` filter — it relies entirely on `auth()->check() === false` to see all users' cards. That assumption holds for genuine unauthenticated cron execution but breaks if the command is ever invoked from a process with ambient authentication (e.g. `Artisan::call()` from within an authenticated web request, or a queued job carrying auth context).

**Severity assessment (per plan's D-02 escalation instruction):** High. If this scenario occurs in production, the nightly `credit-cards:generate-cycles --issue-ready` job — or any manual/administrative trigger of the command from an authenticated context — would silently generate/issue billing cycles for only one user while every other user's active credit cards are skipped entirely for that run. This is a silent data-integrity gap (drifted card balances, missed statement issuance) with no error surfaced. The three commands are currently scheduled via `Schedule::command(...)` (genuine cron, unaffected), so there is no evidence this has fired in production yet, but the command is also directly callable via `Artisan::call()`/`$this->artisan()` from any authenticated context (e.g. an admin action, a queued job triggered from a request), which would trigger it.

**Not fixed in this plan** — per the plan's threat model, this plan's mitigation for T-18-03-02 is the test itself ("a failure is escalated as a D-02 high-severity finding"), not a code fix. The fix (e.g., an explicit `CreditCard::withoutUserScope()` on the console query, or wrapping the command body to force-disable the scope) is left for a follow-up plan in this phase or a dedicated D-02 fix plan.

## User Setup Required

None - no external service configuration required.

## Orchestrator Follow-Up (post-merge, 2026-08-06)

Per CONTEXT.md's D-02 policy ("silently skipping a user's billing cycles = drifted balances = high severity → fix in this same phase"), the ambient-authentication finding above was fixed immediately after Wave 1 merged, rather than deferred to a later plan:

- Added `->withoutUserScope()` to `CreditCard::query()` in `routes/console.php` (`credit-cards:generate-cycles`)
- Added `->withoutUserScope()` to `Loan::query()` in `routes/console.php` (`loans:sync-installments`) — same unverified-but-present exposure, fixed for consistency
- Added `->withoutUserScope()` to `Subscription::query()` in `app/Services/SubscriptionService.php::syncDueRenewals()` (`subscriptions:sync-renewals`) — same exposure, fixed for consistency
- Verified none of these three call sites are reachable from an authenticated HTTP path (only invoked from `routes/console.php`), so the explicit scope bypass cannot be used to escalate privilege via any other entry point
- `test_generate_cycles_command_is_unaffected_by_ambient_authentication` now passes; full suite green at 181/181

## Next Phase Readiness

- The console-scoping contract (D-04) is now fully proven for all three shipped commands.
- A concrete, reproducible D-02 finding exists and is captured both in this SUMMARY and as a permanently failing (by design) test in `tests/Feature/ConsoleScopingTest.php`. A follow-up plan should either (a) fix `credit-cards:generate-cycles` (and audit `loans:sync-installments`/`subscriptions:sync-renewals` for the same ambient-auth exposure, since they also rely on the same no-explicit-filter pattern) or (b) explicitly accept the risk and mark the test `->skip()` with a linked issue — do not silently delete it.
- Pre-existing, unrelated failure noted but out of scope for this plan: `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report...` fails on `assertSee('exceeded')` — confirmed pre-existing via `git log` (last touched in `82f7ad3 feat: removed admin widgets`, no relation to this plan's files). Logged here for visibility, not fixed.

---
*Phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b*
*Completed: 2026-08-06*
