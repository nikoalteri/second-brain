---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 04
subsystem: credit-cards
tags: [race-condition, idempotency, credit-card-balance, testing]
requires: []
provides:
  - "Idempotent CreditCardCycleService::syncCycleAndCardFromPayment balance recompute"
  - "Sequenced race-condition regression tests (unit + feature)"
affects:
  - app/Services/CreditCardCycleService.php
  - tests/Unit/CreditCardCycleServiceTest.php
  - tests/Feature/CreditCardLifecycleIntegrationTest.php
tech-stack:
  added: []
  patterns:
    - "Authoritative recompute (sum-from-source) instead of delta application/reversal for money state"
key-files:
  created: []
  modified:
    - tests/Unit/CreditCardCycleServiceTest.php
    - app/Services/CreditCardCycleService.php
    - tests/Feature/CreditCardLifecycleIntegrationTest.php
decisions:
  - "Replaced applyPrincipalPayment/reversePrincipalPayment deltas in syncCycleAndCardFromPayment with $this->syncCardBalance($card), matching the plan's prescribed fix and the invariant the nightly credit-cards:generate-cycles job already assumes"
  - "Fixed the revolving-card lifecycle test fixture (seeded current_balance -> opening-balance expense row) rather than weakening its assertions, since the recompute invariant is already what the nightly job applies to every active card"
  - "handleDeletedPayment() delta pattern (T-18-04-04) left unchanged per plan scope; documented here as a follow-up candidate for plan 18-06"
metrics:
  duration: "~35m"
  completed: 2026-08-06
---

# Phase 18 Plan 04: Close the credit-card cycle/payment balance race condition Summary

Replaced a non-idempotent delta-based balance adjustment in `CreditCardCycleService::syncCycleAndCardFromPayment()` with an authoritative recompute (`syncCardBalance()`), closing a race where two near-simultaneous payment-status syncs carrying the same stale `previousStatus` could each apply a full principal delta and drift `credit_cards.current_balance`.

## What Was Built

**Task 1 — RED (reproduction):** Added three sequenced tests to `tests/Unit/CreditCardCycleServiceTest.php`:
- `duplicate_payment_sync_with_stale_previous_status_does_not_double_reduce_balance`
- `duplicate_payment_unmark_sync_does_not_double_restore_balance`
- `interleaved_payment_syncs_produce_deterministic_cycle_status`

**Blocking bug fixed first (Rule 3):** `tests/Unit/CreditCardCycleServiceTest.php` was missing `use PHPUnit\Framework\Attributes\Test;`. Under PHPUnit 11 this silently drops every `#[Test]`-attributed method in the class from the suite — all 6 pre-existing tests in this file were not being executed at all (confirmed via `php artisan test --debug`, which listed `Tests\Unit\CreditCardCycleServiceTest` under "No tests found in class"). Fixed by adding the import; also added `RefreshDatabase` since the new tests need a DB-backed fixture.

**RED evidence (verbatim, pre-fix):**
```
✓ duplicate payment sync with stale previous status does not double reduce balance   (passed — see note below)
⨯ duplicate payment unmark sync does not double restore balance
   Failed asserting that 600.0 is identical to 300.0.
✓ interleaved payment syncs produce deterministic cycle status                        (passed — see note below)
Tests: 1 failed, 8 passed (41 assertions)
```
`duplicate_payment_unmark_sync_does_not_double_restore_balance` failed as expected (600.0 vs 300.0), proving the race is real. The reduce-direction and interleaved-status tests unexpectedly *passed* pre-fix — not because the bug is absent, but because in this fixture the balance is already floored at `max(0.0, ...)` after the first reduction (0.00), so a second full-principal reduction is masked by the clamp (`max(0, 0 - 300) = 0`, same as the correct result). The restore direction has no such floor near 300 in the same way, so it cleanly exposed the non-idempotency. This asymmetric result is still valid reproduction evidence for the underlying bug (a caller-supplied-state delta applied twice), and Task 2's fix addresses both directions uniformly.

**Task 2 — GREEN (fix):** In `app/Services/CreditCardCycleService.php::syncCycleAndCardFromPayment()`, replaced the `applyPrincipalPayment`/`reversePrincipalPayment` delta block with `$this->syncCardBalance($card)`, the same authoritative `expenses - paid principal` recompute the nightly `credit-cards:generate-cycles` job already uses. `$fromPaid`/`$toPaid`/`$principal` locals removed; `$previousStatus`/`$currentStatus` parameters and the cycle-status decision logic were left unchanged (still used for the `OVERDUE` branch).

Post-fix, the previously-unexpectedly-passing tests continue to pass and `duplicate_payment_unmark_sync_does_not_double_restore_balance` now passes (300.0, not 600.0).

**Regression found and fixed (Rule 1, fixture):** `revolving_issue_and_payment_reduce_residual_balance_by_principal` in `tests/Feature/CreditCardLifecycleIntegrationTest.php` seeded `current_balance => 1000` directly on card creation instead of via an expense row. The new unconditional recompute (`current_balance = sum(expenses) - sum(paid principal)`) discarded that unsourced seed the first time any payment-status sync ran on the card (including on payment *creation*, previousStatus=null), because the nightly job already treats this formula as authoritative for every active card — the fixture's assumption (delta-preserved externally-seeded balance) was already inconsistent with production behavior, just never exercised by a test that also ran the nightly-equivalent recompute. Fixed by representing the pre-existing revolving debt as an "Opening balance" `CreditCardExpense` row (dated within the same cycle period) instead of a raw `current_balance` seed. This preserves every original assertion (1100 pre-payment, 132 interest, 118 principal, 982 post-payment, 748 account balance) without weakening any of them — the interest/principal calculation reads `card->current_balance` directly at issue time (unaffected by expense-vs-seed representation), and the recompute now correctly includes the opening-balance expense.

**Task 3 — end-to-end regression:** Added two `#[Test]` methods to `tests/Feature/CreditCardLifecycleIntegrationTest.php`:
- `repeated_mark_paid_requests_do_not_drift_card_balance` — two `POST .../mark-paid` calls leave `card.current_balance` at 0.00 and `cycle.status` at `PAID`, identical to one call.
- `mark_paid_unmark_and_remark_converges_on_single_payment_balance` — mark-paid → unmark (direct `$payment->update()`, no unmark endpoint exists) → mark-paid again converges on the same balance as a single mark-paid.

While writing the second test, found and fixed a test-authoring bug in my own new test (not a deviation from the plan's scope, just a self-correction before commit): `$payment` was fetched before the first HTTP `mark-paid` call, so calling `$payment->update(['status' => PENDING, ...])` without `->refresh()` first was a silent no-op (Eloquent compared against its own stale in-memory "original" attributes, which already said `pending`/`null`, so it saw no dirty fields). Added `$payment->refresh()` before the direct update.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Missing `PHPUnit\Framework\Attributes\Test` import silently dropped all tests in `tests/Unit/CreditCardCycleServiceTest.php`**
- **Found during:** Task 1, first `php artisan test --filter=CreditCardCycleServiceTest` run (`No tests found`)
- **Issue:** `#[Test]` attribute referenced without an import resolves to an unresolvable class; PHPUnit 11 silently excludes the method from the suite rather than erroring
- **Fix:** Added `use PHPUnit\Framework\Attributes\Test;`
- **Files modified:** `tests/Unit/CreditCardCycleServiceTest.php`
- **Commit:** `8b7812e`
- **Scope note:** The same missing-import pattern exists in several sibling files (`CreditCardBalanceServiceTest.php`, `CreditCardAvailableCreditTest.php`, `CreditCardCreditLineSyncTest.php`, and others reported by `php artisan test --debug`). Those files are outside this plan's `files_modified` and were left untouched — flagged here as a systemic pre-existing issue worth a dedicated cleanup pass (not credit-card-specific; affects `tests/Unit/LoanRepositoryTest.php`, `SubscriptionServiceTest.php`, etc. too).

**2. [Rule 1 - Bug/fixture] Revolving lifecycle test fixture incompatible with the new authoritative recompute**
- **Found during:** Task 2, full-suite verification
- **Issue:** `revolving_issue_and_payment_reduce_residual_balance_by_principal` seeded `current_balance` directly rather than deriving it from an expense row; the new unconditional `syncCardBalance()` call discarded the unsourced seed
- **Fix:** Represented the pre-existing revolving debt as an "Opening balance" `CreditCardExpense` row instead of a raw `current_balance` seed; no assertions weakened
- **Files modified:** `tests/Feature/CreditCardLifecycleIntegrationTest.php`
- **Commit:** `c2fdff7`

**3. [Rule 1 - Bug, self-caught before commit] Stale in-memory `$payment` model in new Task 3 test**
- **Found during:** Task 3 authoring, `mark_paid_unmark_and_remark_converges_on_single_payment_balance`
- **Issue:** Direct `$payment->update()` after an earlier HTTP call was a silent no-op because the local `$payment` object was never refreshed
- **Fix:** Added `$payment->refresh()` before the direct status update
- **Files modified:** `tests/Feature/CreditCardLifecycleIntegrationTest.php`
- **Commit:** `1b275c6`

### Environment issue (not a code deviation)

This worktree's `vendor/` is a symlink shared across all parallel wave-1 worktree agents (pointing at the main repo's `vendor/`). Composer's generated `autoload_classmap.php`/`autoload_files.php` embed an absolute `$baseDir` computed from `dirname(__DIR__)` inside the (symlink-resolved) `vendor/composer/` directory — so whichever worktree agent last ran `composer dump-autoload` "wins" the shared autoloader for all of them. Mid-session, another parallel agent's `composer dump-autoload` repointed the shared autoloader at its own worktree, causing this worktree's `App\*` edits to silently not take effect (my `syncCycleAndCardFromPayment` fix appeared to not run, until traced to this cause). Re-running `composer dump-autoload` from this worktree before each verification run fixed it. No production or test code changes were made for this; documenting per the existing guidance in this agent's instructions ("if a sibling worktree's parallel composer dump-autoload corrupts the shared vendor/ symlink... run composer dump-autoload before concluding it's a real bug").

## Out-of-Scope / Deferred (not fixed, per plan scope boundary)

**T-18-04-04 (accepted in this plan's threat model):** `CreditCardCycleService::handleDeletedPayment()` (lines ~293-338) still uses the same delta pattern (`reversePrincipalPayment`) that Task 2 replaced in `syncCycleAndCardFromPayment()`. Left unchanged per the plan's explicit scope boundary. Carrying forward to plan 18-06's deferred-items documentation with a D-02 severity note, as instructed by the plan.

**Pre-existing, unrelated test failure:** `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels` fails identically on the unmodified base commit (verified via `git stash` before making any change in this plan) — no credit-card code involved. Out of scope for this plan; not touched.

**Systemic missing-`Test`-attribute-import pattern:** see deviation #1's scope note above — several other `tests/Unit/*.php` files (unrelated to credit cards) have the same silently-dropped-tests issue. Not fixed here as it is outside this plan's file scope.

## Verification

```
php artisan test --filter=CreditCardCycleServiceTest    -> 9 passed (42 assertions)
php artisan test --filter=CreditCardLifecycleIntegrationTest -> 9 passed (54 assertions)
php artisan test (full suite) -> 162 passed, 1 failed (pre-existing, unrelated FinanceReportPageTest)
grep -n "pcntl_fork\|new PDO" tests/  -> no matches (no SQLite :memory: true-concurrency anti-pattern)
```

## Threat Flags

None — this plan's threat model already anticipated all surfaces touched (T-18-04-01 through T-18-04-05); no new surface introduced.

## Known Stubs

None.

## Self-Check: PASSED

- `tests/Unit/CreditCardCycleServiceTest.php` — FOUND
- `app/Services/CreditCardCycleService.php` — FOUND
- `tests/Feature/CreditCardLifecycleIntegrationTest.php` — FOUND
- Commit `8b7812e` — FOUND
- Commit `c2fdff7` — FOUND
- Commit `1b275c6` — FOUND
