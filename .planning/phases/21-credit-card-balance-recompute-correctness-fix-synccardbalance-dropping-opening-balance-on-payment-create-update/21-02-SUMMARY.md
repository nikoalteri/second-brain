---
phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update
plan: 02
subsystem: payments
tags: [laravel, artisan, credit-cards, reporting]

requires:
  - phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update
    provides: "opening_balance column and corrected syncCardBalance() invariant (plan 21-01)"
provides:
  - "credit-cards:balance-audit manual Artisan command listing every active card's balance breakdown, flagging cards created before the bug's introduction date"
affects: []

tech-stack:
  added: []
  patterns: ["closure-style Artisan::command() in routes/console.php, matching the existing credit-cards:generate-cycles/loans:sync-installments convention — no Command class"]

key-files:
  created:
    - tests/Feature/CreditCardBalanceAuditCommandTest.php
  modified:
    - routes/console.php

key-decisions:
  - "At-risk criterion is card created_at relative to the bug's introduction date (2026-08-06), not payment-mutation history — refined during research after discovering the nightly credit-cards:generate-cycles job recomputes every active card unconditionally, regardless of payment activity"
  - "Not scheduled — deliberately a manual, one-off pre/post-deploy audit tool"

patterns-established: []

requirements-completed: [D-10]

duration: ~15min
completed: 2026-09-17
---

# Phase 21 Plan 02: credit-cards:balance-audit command Summary

**Added a manual Artisan command that lists every active credit card's current/opening balance breakdown and flags cards created before the balance-recompute bug's introduction date, so the card owner can manually reconcile against real statements after plan 21-01's fix ships.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-09-17
- **Tasks:** 1 of 1 completed
- **Files modified:** 1 modified, 1 created

## Accomplishments
- `php artisan credit-cards:balance-audit` lists active cards across all users (owner email, name, type, current/opening balance, expense/paid-principal breakdown) and flags pre-fix cards with a `CHECK` marker
- Verified manually against the local dev DB (real seeded card, correctly showed no CHECK flag since it postdates the fix) and via a synthetic-fixture Feature test
- Confirmed not wired into the scheduler (`Schedule::command` count unchanged at 3)

## Files Created/Modified
- `routes/console.php` - new `credit-cards:balance-audit` closure command, plus a top-level `use App\Enums\CreditCardPaymentStatus;` import (added during Pint lint cleanup to avoid an inline fully-qualified class name)
- `tests/Feature/CreditCardBalanceAuditCommandTest.php` - proves active cards are listed, inactive cards excluded, and pre-fix cards flagged, using `Artisan::call()` + `Artisan::output()` (not `$this->artisan()`, which doesn't capture `$this->table()` output the same way)

## Decisions Made
See `key-decisions` in frontmatter — both were carried over directly from 21-RESEARCH.md/21-CONTEXT.md, no new decisions made during execution.

## Deviations from Plan

Minor, non-functional:
- The plan's draft action used an inline `\App\Enums\CreditCardPaymentStatus::PAID` reference; switched to a proper `use` import after `./vendor/bin/pint --test` flagged it as a new lint issue not present in the pre-existing baseline (baseline-vs-diff comparison confirmed via `git stash`).
- The plan's draft test used `$this->artisan(...)->assertSuccessful()` then `Artisan::output()`; this returned an empty string in practice. Switched to `Artisan::call(...)` + `Artisan::output()`, the reliable pattern for asserting on `$this->table()` output in this Laravel version.
- The plan's draft test set `created_at` directly inside `CreditCard::create([...])`; `created_at` is not fillable on the model (mass-assignment protected), so it was silently ignored and both fixture cards got real timestamps. Switched to `forceFill(['created_at' => ...])->save()` after creation.

All three were mechanical fixes discovered during implementation, not scope or design changes.

### Code review follow-up, round 1 (2026-09-17)

**2. [P1, confirmed & fixed] The CHECK criterion missed cards created during the bug's own active window.**
- As shipped, `credit-cards:balance-audit` flagged a card only when `created_at < bugIntroducedAt` (2026-08-06). A card created 2026-09-01 — after the bug was introduced but before this fix deployed — was exposed to the buggy nightly recompute for its entire life, yet was never flagged. Reproduced directly against the original test fixture (a September-dated "post-fix" card that should have been flagged).
- **Fix (round 1):** `routes/console.php` gated on `fixDeployedAt` (`2026-09-17 12:00:00`, the migration's own timestamp) instead of `bugIntroducedAt`.
- **Test:** `CreditCardBalanceAuditCommandTest` restructured with three cards spanning all three windows (pre-bug, post-bug-pre-fix, post-fix) instead of two — the middle one is exactly the case the old criterion missed, now asserted as flagged.

### Code review follow-up, round 2 (2026-09-17)

**2 (continued). [P2, confirmed & fixed] fixDeployedAt conflated the migration's authored timestamp with its actual deploy time.**
- A migration's filename timestamp records when the file was written, not when it ran in any given environment. If a real deploy happens after `2026-09-17 12:00:00`, cards created in the gap between that hardcoded constant and the real deploy would be silently excluded from CHECK while genuinely having been exposed to the pre-fix nightly recompute.
- **Fix:** dropped date-based flagging entirely. `credit-cards:balance-audit` now lists **every** active card, ordered oldest-`created_at`-first (a manual-review-prioritization aid, not a pass/fail signal), with an added `Created` column and no `CHECK`/`Pre-fix?` column at all. The printed message tells the user to cross-check every listed card — conservative by construction, since a "list everything" report cannot silently exclude a genuinely at-risk card the way any date threshold could.
- **Test:** `CreditCardBalanceAuditCommandTest` rewritten (`it_lists_every_active_card_oldest_first_with_no_date_based_filtering`) — asserts both cards appear, the closed one doesn't, the count message has no CHECK wording, and row order matches `created_at` ascending.

Full suite re-verified green after both rounds: 333 tests, 0 failures, stable across two consecutive runs.

## Issues Encountered

None beyond the deviations above, all resolved during implementation and the post-review follow-up.

## User Setup Required

None — this is a CLI-only, non-scheduled reporting tool. The user should run `php artisan credit-cards:balance-audit` after deploying this phase and manually cross-check any `CHECK`-flagged real cards against real statements, correcting `Opening balance` in Filament where needed.

## Next Phase Readiness

Phase 21 is complete: both plans delivered, full test suite green (327 tests, 0 failures). No further committed work in this phase.

---
*Phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update*
*Completed: 2026-09-17*
