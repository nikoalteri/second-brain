---
phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update
plan: 01
subsystem: payments
tags: [laravel, eloquent, filament, credit-cards, data-integrity]

requires:
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    provides: the idempotent syncCardBalance() recompute this plan corrects (commit c2fdff7, plan 18-04)
provides:
  - "CreditCard.opening_balance column, model wiring, and a creating-event backward-compat default from current_balance"
  - "Corrected syncCardBalance() formula: opening_balance + expenses - paid principal"
  - "handleDeletedPayment() routed through the same authoritative recompute instead of a separate delta path"
  - "opening_balance exposed additively in the Filament form and both REST create/update endpoints"
  - "CreditCardObserver recomputes current_balance whenever opening_balance is updated on an existing card (added post-review)"
affects: [21-02-credit-cards-balance-audit-command]

tech-stack:
  added: []
  patterns: ["creating-event backward-compat default (Eloquent booted() hook) to bridge a schema change without touching existing call sites"]

key-files:
  created:
    - database/migrations/2026_09_17_120000_add_opening_balance_to_credit_cards_table.php
    - tests/Feature/CreditCardOpeningBalanceBackfillTest.php
    - app/Observers/CreditCardObserver.php
    - tests/Feature/CreditCardOpeningBalanceRegressionTest.php
  modified:
    - app/Models/CreditCard.php
    - app/Services/CreditCardCycleService.php
    - app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php
    - app/Http/Requests/Api/StoreCreditCardRequest.php
    - app/Http/Requests/Api/UpdateCreditCardRequest.php
    - app/Http/Resources/Api/CreditCardResource.php
    - database/factories/CreditCardFactory.php
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "opening_balance is additive to current_balance (both stay editable), not a rename/replacement — avoids a breaking change to the existing Filament/API surface"
  - "No explicit locking (lockForUpdate) added — the corrected formula's idempotency alone is sufficient, matching Phase 18's original design intent"
  - "handleDeletedPayment() no longer branches on payment status before restoring debt — routes unconditionally through syncCardBalance(), which already excludes non-PAID principal from its sum"

patterns-established:
  - "Backward-compat schema bridge via Eloquent creating hook: when adding a column that supersedes part of another field's meaning, default it from the old field at creation time instead of migrating every existing test/factory/seeder call site"

requirements-completed: [D-01, D-02, D-03, D-04, D-05, D-06, D-07, D-08, D-09]

duration: ~40min
completed: 2026-09-17
---

# Phase 21 Plan 01: Credit card balance recompute correctness Summary

**Fixed a live data-integrity bug where `CreditCardCycleService::syncCardBalance()` silently discarded any card balance not backed by a tracked `CreditCardExpense`, by adding a persisted `opening_balance` column and correcting the recompute formula — with zero edits to either of the two tests that had been failing since Phase 18.**

## Performance

- **Duration:** ~40 min (research + implementation + verification)
- **Completed:** 2026-09-17
- **Tasks:** 4 of 4 completed
- **Files modified:** 7 modified, 2 created

## Accomplishments
- Root-caused and fixed the exact bug behind two pre-existing failing tests (`CreditCardCreditLineSyncTest`, `CreditCardKpiServiceTest`) — both now pass with zero test-file edits, via a `creating`-event default that seeds `opening_balance` from `current_balance` when only the latter is given
- Removed the now-dead `CreditCardBalanceService` constructor dependency from `CreditCardCycleService` after aligning `handleDeletedPayment()` to the same authoritative recompute used by create/update
- Full test suite: 327 tests, 0 failures (up from the 325-test/323-pass baseline; +2 new tests from this phase)

## Files Created/Modified
- `database/migrations/2026_09_17_120000_add_opening_balance_to_credit_cards_table.php` - additive `decimal(12,2)` column, default 0, not unsigned
- `app/Models/CreditCard.php` - fillable/cast for `opening_balance`; `creating` hook backward-compat default
- `app/Services/CreditCardCycleService.php` - corrected `syncCardBalance()` formula; `handleDeletedPayment()` now calls `syncCardBalance()`; removed unused `$balanceService` property/constructor param
- `app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php` - additive `opening_balance` TextInput with explanatory helper text
- `app/Http/Requests/Api/StoreCreditCardRequest.php`, `UpdateCreditCardRequest.php` - `opening_balance` validation mirroring `current_balance`
- `app/Http/Resources/Api/CreditCardResource.php` - `opening_balance` in the API response
- `database/factories/CreditCardFactory.php` - explicit `opening_balance` default
- `tests/Feature/CreditCardOpeningBalanceBackfillTest.php` - new regression test proving pre-existing (schema-default) cards are stable under repeated recomputes and correctly apply payments
- `app/Observers/CreditCardObserver.php` - **added post-review** (see below): `updated()` hook that re-runs `syncCardBalance()` whenever `opening_balance` changes on an existing card, with a re-entrancy guard
- `app/Providers/AppServiceProvider.php` - **added post-review**: registers `CreditCard::observe(CreditCardObserver::class)`
- `tests/Feature/CreditCardOpeningBalanceRegressionTest.php` - **added post-review**: 5 tests covering the four review findings below

## Decisions Made
See `key-decisions` in frontmatter. All three (additive field, no extra locking, unconditional recompute in delete path) were made during implementation per the CONTEXT.md/RESEARCH.md guidance captured during discuss-phase and research.

## Deviations from Plan

None during initial execution — the `creating`-event bridge (RESEARCH.md section 7) was verified by hand-trace against both failing tests' exact assertions before any code was written.

### Code review follow-up (2026-09-17)

A review of the diff against `main` found four reproducible issues, all confirmed by direct reproduction (via `php artisan tinker` against the real dev DB, then cleaned up) before fixing:

**1. [P1, confirmed & fixed] Filament's form default bypassed the opening_balance backward-compat bridge.**
- `CreditCardForm.php`'s `opening_balance` field is `->required()->default(0)`, so Filament's create submission always sends `opening_balance: 0` explicitly — never absent. The original `creating` hook only fired when the key was entirely missing (`! array_key_exists(...)`), so a user entering `current_balance: 500` via Filament and leaving the new field at its untouched default lost the 500 on the first recompute, reproducing the exact original bug for every new Filament-created card.
- **Fix:** `app/Models/CreditCard.php`'s `creating` hook now treats `opening_balance` as "not meaningfully provided" when it is either absent OR exactly `0.0` while `current_balance` carries a non-zero value, not just when the key is absent.
- **Test:** `CreditCardOpeningBalanceRegressionTest::creating_with_both_fields_explicit_like_filament_still_seeds_opening_balance_from_current_balance`

**3. [P2, confirmed & fixed] Updating opening_balance didn't recompute current_balance.**
- Editing `opening_balance` on an existing card (via Filament's edit form, or the REST update endpoint) left `current_balance` stale until an unrelated payment or the nightly job happened to run — directly undermining the Filament helper text's own instruction to "edit this field to correct a card's real-world debt."
- **Fix:** new `app/Observers/CreditCardObserver::updated()` calls `CreditCardCycleService::syncCardBalance()` whenever `opening_balance` was changed, guarded against recursion both by `wasChanged('opening_balance')` (syncCardBalance's own nested update never touches opening_balance) and an explicit static per-record flag.
- **Tests:** `updating_opening_balance_recomputes_current_balance_immediately` (model level), `updating_opening_balance_via_the_rest_api_recomputes_current_balance` (REST level)

**4. [P2, confirmed & fixed] Validation accepted explicit null for opening_balance, a NOT NULL column.**
- Both `StoreCreditCardRequest` and `UpdateCreditCardRequest` declared `opening_balance` as `nullable`, but the migration created it without `->nullable()`. An explicit `opening_balance: null` in a request passed validation and then hit `SQLSTATE[23000]` at the database — reproduced directly.
- **Fix:** removed `nullable` from both rules (`['numeric', 'min:0']` / `['sometimes', 'numeric', 'min:0']`) — `numeric` already rejects `null`, so this now fails cleanly at validation (422) instead of at the database (500). Note: `current_balance` has the identical pre-existing issue, out of this phase's scope, not touched.
- **Tests:** `store_rejects_explicit_null_opening_balance_with_a_validation_error_not_a_database_error`, `update_rejects_explicit_null_opening_balance_with_a_validation_error_not_a_database_error`

(Finding 2, the at-risk-report threshold, belongs to plan 21-02 — see `21-02-SUMMARY.md`.)

Full suite re-verified green after all four round-1 fixes: 332 tests, 0 failures (327 → 332 as the 5 new regression tests were added), including the two Phase 19 tests and all plan-21-01 tests unchanged.

### Code review follow-up, round 2 (2026-09-17)

A second review pass found one more P1 in this plan's territory, the mirror-image gap of round 1's finding 1:

**1b. [P1, confirmed & fixed] Creating with only opening_balance set left current_balance uninitialized.**
- Round 1's fix only handled "current_balance set, opening_balance defaulted to 0" (the Filament create-form case). The opposite case — `opening_balance: 900`, `current_balance` left at 0 — was never addressed: `current_balance` (and therefore `available_credit`) stayed 0 immediately after creation, until an unrelated sync. Reproduced exactly as reported: `opening_balance: 900`, `credit_limit: 1000` showed `available_credit: 1000` right after creation instead of `100`.
- **Fix:** `app/Models/CreditCard.php`'s `creating` hook now has a symmetric else-branch — whenever the backward-compat bridge condition doesn't apply (i.e. `opening_balance` is meaningfully non-zero, or both fields are genuinely 0), `current_balance` is force-set to the computed `$openingBalance` float. A brand-new record has no expenses/payments yet, so `current_balance` can only ever legitimately equal `opening_balance` at creation — regardless of whatever value the caller separately submitted for `current_balance`.
- **Bug caught during this fix's own verification:** the first attempt assigned `$card->current_balance = $card->opening_balance` (the raw, possibly-`null` model attribute) instead of the already-computed `$openingBalance` local float — this NULL'd `current_balance` on any ordinary card created with `current_balance: 0` and no `opening_balance` key at all (e.g. the audit-command test fixtures), violating the NOT NULL constraint. Caught immediately by the full suite (2 errors) before commit; fixed by using the computed float.
- **Test:** `creating_with_only_opening_balance_set_initializes_current_balance_and_available_credit_immediately`

Full suite re-verified green after round 2: 333 tests, 0 failures, stable across two consecutive runs.

## Issues Encountered

- A full-suite run intermittently produced 1 unrelated error in `CreditCardApiTest::test_nested_credit_card_resources_must_belong_to_the_addressed_card` ("This billing cycle has already been issued"). Confirmed via isolated re-run and a same-order re-run on the unmodified base commit that this is a pre-existing, real-wall-clock-time-sensitive flake unrelated to this plan's changes (reproduced identically with and without the fix present in one run, absent in the next run of either). Not investigated further — same class of flakiness already documented in Phase 19's `deferred-items.md` for a different test.
- BSD `grep` (macOS) does not treat a literal `$` mid-pattern as safe for plain `grep -q` the way GNU grep does in this shell's default config — acceptance-criteria greps referencing `$totalExpenses`/`$totalPrincipalPaid` needed `-F` (fixed-string) to match reliably during manual verification. Functional correctness was confirmed via `php artisan test`, not affected.

## User Setup Required

None — this is an internal schema/logic fix. Plan 21-02 delivers the promised at-risk-card report for manual post-deploy reconciliation of real cards against real statements.

## Next Phase Readiness

Plan 21-02 (at-risk-card audit command) depends on `opening_balance` existing and the corrected formula being live — both delivered here. Ready to proceed directly.

---
*Phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update*
*Completed: 2026-09-17*
