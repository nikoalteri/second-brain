---
phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl
plan: 01
subsystem: database
tags: [laravel, eloquent, filament, rest-api, credit-cards]

# Dependency graph
requires: []
provides:
  - "credit_cards.fixed_payment_includes_stamp_duty boolean column, default false"
  - "CreditCard model fillable/cast wiring for the new flag"
  - "Filament Toggle exposing the flag in the Regole section"
  - "REST create/update validation and CreditCardResource serialization for the flag"
affects: [19-02, 19-03, 19-04, 19-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-card boolean configuration flag added via single additive migration, mirrored from the existing interest_calculation_method migration pattern"

key-files:
  created:
    - database/migrations/2026_08_07_120000_add_fixed_payment_includes_stamp_duty_to_credit_cards.php
    - tests/Feature/CreditCardStampDutyFlagTest.php
  modified:
    - app/Models/CreditCard.php
    - database/factories/CreditCardFactory.php
    - app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php
    - app/Http/Requests/Api/StoreCreditCardRequest.php
    - app/Http/Requests/Api/UpdateCreditCardRequest.php
    - app/Http/Resources/Api/CreditCardResource.php

key-decisions:
  - "Default the new column to false (exclusive of stamp duty) so no existing card or existing test assertion changes behavior — verified against tests/Unit/CreditCardDailyBalanceTest.php:146, CreditCardKpiServiceTest.php:121, CreditCardPaymentPostingServiceTest.php:51"
  - "Did not add a factory state for true — tests needing inclusive mode set the attribute explicitly per the plan"
  - "Did not touch the CreditCard::booted() saving hook — CHARGE cards never reach calculatePaymentBreakdown() so the flag is harmless there"

patterns-established:
  - "Per-card revolving-math configuration flags: migration -> model fillable/cast -> factory default -> Filament Toggle -> Store/Update request rule -> API resource field, all mirroring the existing interest_calculation_method/skip_weekends conventions"

requirements-completed: [D-03]

# Metrics
duration: 35min
completed: 2026-08-07
---

# Phase 19 Plan 01: Stamp Duty Inclusion Flag Summary

**Added the per-card `fixed_payment_includes_stamp_duty` boolean configuration flag (D-03) across schema, model, factory, Filament form, REST validation, and REST serialization, defaulting to `false` so no existing card or test changes behavior.**

## Performance

- **Duration:** 35 min
- **Started:** 2026-08-07T00:05:00Z
- **Completed:** 2026-08-07T00:39:49Z
- **Tasks:** 2 completed
- **Files modified:** 8 (2 created, 6 modified)

## Accomplishments
- `credit_cards.fixed_payment_includes_stamp_duty` boolean column exists, defaulting to `false`, applied to the local dev database
- `CreditCard` model mass-assigns and boolean-casts the new attribute; `CreditCardFactory` defaults it to `false`
- Filament `Regole` section exposes a `Toggle` for the flag with an explanatory helper text
- `StoreCreditCardRequest` / `UpdateCreditCardRequest` accept `['sometimes', 'boolean']`; `CreditCardResource` returns the flag as a plain boolean
- `CreditCardStampDutyFlagTest` proves the default-false factory behavior and the create/update REST round-trip with synthetic-only fixture data

## Task Commits

Each task was committed atomically:

1. **Task 1: Add fixed_payment_includes_stamp_duty column, model wiring, and factory default** - `956a910` (feat)
2. **Task 2: Expose the flag in the Filament form, REST validation, and REST resource** - RED `6864ff8` (test) → GREEN `c07c0c4` (feat)

**Plan metadata:** committed separately by the orchestrator after wave completion (worktree mode — STATE.md/ROADMAP.md are not touched by this agent)

## Files Created/Modified
- `database/migrations/2026_08_07_120000_add_fixed_payment_includes_stamp_duty_to_credit_cards.php` - Additive migration adding the boolean column, default false, after `stamp_duty_amount`
- `app/Models/CreditCard.php` - Added to `$fillable` and `$casts`
- `database/factories/CreditCardFactory.php` - Added `'fixed_payment_includes_stamp_duty' => false` to `definition()`
- `app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php` - Added `Toggle::make('fixed_payment_includes_stamp_duty')` in the `Regole` section
- `app/Http/Requests/Api/StoreCreditCardRequest.php` - Added `['sometimes', 'boolean']` validation rule
- `app/Http/Requests/Api/UpdateCreditCardRequest.php` - Added `['sometimes', 'boolean']` validation rule
- `app/Http/Resources/Api/CreditCardResource.php` - Added `(bool) $this->fixed_payment_includes_stamp_duty` to `toArray()`
- `tests/Feature/CreditCardStampDutyFlagTest.php` - 4 tests covering factory default, API create-true round-trip, API update-false round-trip, and API create-omitted default

## Decisions Made
- Default `false` (exclusive) chosen deliberately per the plan's explicit warning: several existing tests already assert the exclusive-mode result (e.g. `total_due = fixed_payment + stamp_duty`), so defaulting to `true` would have silently flipped every existing card's math.
- No factory `true` state added; kept minimal per plan instruction.
- Left the `booted()` saving hook untouched, matching the plan's explicit guidance that CHARGE cards never reach the calculator.

## Deviations from Plan

### Auto-fixed Issues (environment, not code)

**1. [Rule 3 - Blocking] Worktree had no local `vendor/` — symlinked to a sibling directory with a stale/mismatched autoload map**
- **Found during:** Task 1 (running `php artisan migrate --pretend`)
- **Issue:** The worktree's `vendor` was a symlink to the main repo's `vendor`. `php artisan` CLI calls (which `require` `bootstrap/app.php` by explicit path) worked fine, but PHPUnit-based tests use `Application::inferBasePath()`, which derives the base path from the *physical* location of the registered Composer class loader — resolving to the main repo, not this worktree. As a result, Composer's baked-in PSR-4 absolute-path autoload maps pointed at the main repo's `app/`, `database/factories/`, etc., so classes loaded the main repo's (unmodified) copies of `CreditCard.php` and `CreditCardFactory.php` during tests, silently ignoring this worktree's edits, and migrations resolved against the wrong `database/migrations` directory (missing the new file) under `RefreshDatabase`.
- **Fix:** Removed the `vendor` symlink and ran a full local `composer install` in the worktree so the autoloader is self-contained and correctly path-mapped to this worktree.
- **Files modified:** None tracked by git (`vendor/` is gitignored); no application code changed.
- **Verification:** After reinstall, `Schema::hasColumn('credit_cards', 'fixed_payment_includes_stamp_duty')` returns `true` under `RefreshDatabase`, and the factory-created card correctly reflects the model's boolean cast and default.
- **Committed in:** N/A (environment-only fix, no commit)

---

**Total deviations:** 1 auto-fixed (1 blocking, environment-only). No application code deviations — plan executed exactly as written for all shipped files.
**Impact on plan:** None on scope; this was a pre-existing worktree provisioning gap discovered while verifying the plan's own acceptance criteria, not a consequence of any code change in this plan.

## Issues Encountered
- Diagnosed and resolved the vendor-symlink/autoload issue described above (see Deviations).
- Discovered two pre-existing `Unit` test failures (`CreditCardCreditLineSyncTest`, `CreditCardKpiServiceTest`) unrelated to this plan's files; reproduced identically on the unmodified `main` branch baseline, so left untouched per the scope boundary rule. Logged in `.planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md`.
- Discovered two worktree-only environment gaps while running the broader `Feature` suite for regression-checking (missing Vite build manifest for `ExampleTest`, and a `FinanceReportPageTest` assertion difference that passes identically on `main` with a byte-identical test file). Both unrelated to credit cards; logged in the same `deferred-items.md` file rather than fixed, per the scope boundary rule.

## User Setup Required

None - no external service configuration required. The migration was already applied to the local dev database (`php artisan migrate --force`) as required by the plan.

## Next Phase Readiness

- `credit_cards.fixed_payment_includes_stamp_duty` exists and is fully wired end-to-end (model, factory, Filament, REST validation, REST resource), unblocking plan 19-03's calculator fix which branches on this flag.
- No blockers for downstream 19-02..19-05 plans introduced by this plan.
- Pre-existing, unrelated test/environment gaps are tracked in `deferred-items.md` for future triage — not blocking this plan or its immediate dependents.

---
*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Completed: 2026-08-07*

## Self-Check: PASSED

- All 8 created/modified files confirmed present on disk
- All 3 task commit hashes (`956a910`, `6864ff8`, `c07c0c4`) confirmed present in `git log --oneline --all`
- No missing items
