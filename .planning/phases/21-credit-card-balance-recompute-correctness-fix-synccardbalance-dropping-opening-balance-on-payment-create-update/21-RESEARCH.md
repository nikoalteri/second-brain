# Phase 21: Credit card balance recompute correctness - Research

**Researched:** 2026-09-17
**Status:** Research complete

## What I need to know to plan this phase well

### 1. Exact bug-introduction commit (confirms/refines CONTEXT.md's "~2026-08-06")

`c2fdff7` — `fix(18-04): make syncCycleAndCardFromPayment balance update idempotent`, authored 2026-08-06 02:57:16 +0200, merged to `main`/`uat` via `2be0d71` at 2026-08-06 03:02:12 +0200.

The commit message is explicit about the design intent it introduced:

> Replace the applyPrincipalPayment/reversePrincipalPayment delta with an authoritative `$this->syncCardBalance($card)` recompute, matching the pattern the nightly `credit-cards:generate-cycles` job already uses [...] Fixture fix (Rule 1): the revolving-card lifecycle test seeded `current_balance` directly instead of via an expense row, which is incompatible with the recompute invariant (`current_balance = sum(expenses) - sum(paid principal)`) that this fix now applies on every payment-status sync, and that the nightly job already assumed for all cards.

**Important nuance this surfaces:** the "recompute invariant" was a *deliberate* Phase 18 design decision, not an oversight — the author knew it required balances to be expense-backed and fixed one test (`tests/Feature/CreditCardLifecycleIntegrationTest.php`) accordingly. `tests/Unit/CreditCardCreditLineSyncTest.php` and `tests/Unit/CreditCardKpiServiceTest.php` were simply missed in that same pass and have been failing ever since (confirmed independently by Phase 19's `deferred-items.md`). This phase's job is to make the *invariant itself* correct (add the missing `opening_balance` term) — it is not correcting a design that nobody intended.

### 2. The bug is broader than "on payment create/update" — it also fires nightly for every active card

`routes/console.php` — `credit-cards:generate-cycles --issue-ready` (scheduled `dailyAt('02:00')`, see bottom of file):

```php
foreach ($cards as $card) {
    // ...
    $service->refreshCycleStatuses($card->fresh(['cycles.payments', 'payments']));
    $service->syncCardBalance($card->fresh(['cycles.payments', 'payments']));
}
```

This calls `syncCardBalance()` unconditionally for **every** `ACTIVE` credit card, every night, regardless of whether any payment was created/updated. `CONCERNS.md` and this phase's CONTEXT.md (D-10) framed "at-risk cards" as those with a `CreditCardPayment` created/updated after 2026-08-06 — that's too narrow. **Any active card is potentially affected**, because the nightly job has been silently recomputing every active card's balance since 2026-08-06, independent of payment activity.

**Practical consequence:** the recompute is idempotent (re-running it doesn't drift the value further), so a card's *current* `current_balance` in the DB already equals `expenses − paid_principal` for any active card that has run through at least one nightly cycle since 2026-08-06 — there's no "payment-triggered subset" to isolate. The at-risk-card report (D-10) should therefore list **all active cards** (their current `current_balance`, the computed `expenses − paid_principal`, and whether they match), not filter by payment history. This refines D-10's implementation without changing the underlying decision (report before backfill, no auto-reconstruction).

### 3. An "opening balance as synthetic expense" convention already exists in the codebase

`tests/Feature/CreditCardLifecycleIntegrationTest.php:246-252` (added by the same `c2fdff7` commit):

```php
CreditCardExpense::create([
    'credit_card_id' => $card->id,
    'spent_at' => Carbon::parse('2026-03-01'),
    'amount' => 1000,
    'description' => 'Opening balance carried over from previous system',
]);
```

This is the *only* place in the current codebase that represents a card's pre-existing debt correctly under the recompute invariant — by encoding it as a normal `CreditCardExpense` row instead of a raw `current_balance` seed. CONTEXT.md's D-01 (add a real `opening_balance` column) is a more explicit, first-class alternative to this convention, not a conflicting one — it doesn't require touching this test (a real `opening_balance` column defaults to 0, and the test's synthetic-expense approach keeps working unchanged: `0 + 1000 (expense) − paid_principal`). No action needed on this test.

### 4. Schema precedent to mirror, with one precision correction

`Account.opening_balance` (`database/migrations/2026_03_15_183138_add_opening_balance_to_accounts_table.php` + `..._fix_opening_balance_signed_in_accounts_table.php`): `decimal('opening_balance', 10, 2)->default(0)`, explicitly **not** unsigned (a follow-up migration removed an earlier `->unsigned()` because it broke negative balances).

`credit_cards.current_balance` (`database/migrations/2026_03_18_221000_create_credit_cards_table.php:28`) is `decimal('current_balance', 12, 2)->default(0)` — precision **12**, not 10, because credit limits/balances run higher than typical account balances in this app. `CreditCard.opening_balance` should use `decimal('opening_balance', 12, 2)->default(0)` to match its own table's existing column, not blindly copy Account's `10,2`. Do not mark it `->unsigned()` (same reasoning as the Account fix — a user might legitimately record a credit/negative opening position).

### 5. `handleDeletedPayment()` alignment (D-04/D-05) — safe, and enables a cleanup

`CreditCardBalanceService::reversePrincipalPayment()` is called from exactly one place in the whole codebase: `CreditCardCycleService::handleDeletedPayment()` (line 379). Once that call is replaced with `$this->syncCardBalance($card)` per D-05, the `$balanceService` property and constructor parameter on `CreditCardCycleService` become entirely unused (grepped: no other reference in the class). Remove them as part of this same change — no test constructs `CreditCardCycleService` with an explicit `$balanceService` mock (all six call sites in `tests/Unit/CreditCardCycleServiceTest.php` use the no-arg `new CreditCardCycleService()`), so removing the param is safe.

`CreditCardBalanceService::reversePrincipalPayment()` itself must stay — nothing else in this phase's scope should touch it, and no other caller was found, but it's still public API surface for the service class and out of this phase's boundary to remove.

### 6. Artisan command convention for the at-risk-card report (D-10)

No `app/Console/Commands/*.php` class-based commands exist in this app. All Artisan commands (`credit-cards:generate-cycles`, `loans:sync-installments`, `subscriptions:sync-renewals`) are closures registered directly in `routes/console.php`, using `->withoutUserScope()` to query across all users (superadmin/system context) and `$this->info(...)`/`$this->table(...)` for output. The new at-risk-card report command should follow this exact convention (closure in `routes/console.php`, not a new Command class), and should NOT be scheduled (it's a manual, one-off pre-migration audit tool, not a recurring job).

### 7. How to satisfy D-06 (existing tests pass unmodified) while adding `opening_balance`

Both failing tests create a `CreditCard` with `current_balance` set directly (500 and 860) and **never mention `opening_balance`** — because that field doesn't exist yet. Simply adding the column and fixing the formula is NOT enough on its own: `syncCardBalance()` would compute `opening_balance(0, never populated) + expenses(0) - paid_principal(...)`, still dropping the 500/860, because nothing ever tells the new column what the "starting" value was.

**Resolution (verified by hand-tracing both tests against the corrected formula):** give `CreditCard` a `creating` model-event default — if a card is created with `current_balance` set but `opening_balance` is not explicitly given, default `opening_balance` to the given `current_balance`. This is a one-line backward-compatibility bridge for every existing call site (both failing tests, `CreditCardFactory`, any other seeder/test) that still only knows about `current_balance`.

Hand-traced result with this bridge + corrected formula, using the exact values from both tests:
- `CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes` — card created with `current_balance: 500` → bridge sets `opening_balance: 500`. Payment created PENDING → `syncCardBalance` = `500 + 0 expenses − 0 paid` = **500** (assert at line 93 ✓). Payment → PAID (`principal_amount: 240`) → `500 + 0 − 240` = **260** (assert line 101 ✓). Payment → back to PENDING → `500 + 0 − 0` = **500** (assert line 109 ✓).
- `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user` — revolving card created with `current_balance: 860` → bridge sets `opening_balance: 860`. Two expenses (+80, +40) via the untouched delta-based `CreditCardBalanceService::addExpense` bring `current_balance` to 940 before any payment exists. Payment created PENDING for this card → `syncCardBalance` = `860 + 120 expenses − 0 paid` = **980** (assert `revolving_residual` line 124 ✓).
- The first test's other case (`expense_create_update_delete_syncs_used_credit_with_deltas`, already passing today) is untouched by this change: it never creates a `CreditCardPayment`, so `syncCardBalance()` never runs for that card and the existing delta-based expense flow (`addExpense`/`removeExpense`) behaves exactly as before.

**Filament/API surface (satisfies D-02 — opening_balance stays freely editable, same as current_balance today):** add `opening_balance` as a **new, additive** field next to the existing `current_balance` field in `CreditCardForm.php` and in both `StoreCreditCardRequest`/`UpdateCreditCardRequest` — do NOT rename or repurpose the existing `current_balance` field/column. `current_balance` keeps behaving exactly as it does today (directly editable, but overwritten by the next `syncCardBalance()` run — that part of its behavior predates this bug and is unchanged). Going forward, correcting a card's real-world balance should be done via the new `opening_balance` field (which the formula actually respects), not via `current_balance` — the Filament field's helper text should say so explicitly, so the user doesn't re-hit the same surprise when correcting real cards after reading the at-risk report (D-10).

## Files this phase will touch

- `database/migrations/{new}_add_opening_balance_to_credit_cards_table.php` — new migration, mirrors `Account.opening_balance` pattern with `decimal(12,2)` to match `credit_cards.current_balance`
- `app/Models/CreditCard.php` — add `opening_balance` to `$fillable` and `$casts` (`'opening_balance' => 'decimal:2'`)
- `app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php` — add `opening_balance` field next to `current_balance` (~line 119)
- `app/Http/Requests/Api/StoreCreditCardRequest.php`, `app/Http/Requests/Api/UpdateCreditCardRequest.php` — add `opening_balance` validation (mirror `current_balance`'s `nullable|numeric|min:0` / `sometimes|nullable|numeric|min:0`)
- `app/Services/CreditCardCycleService.php` — fix `syncCardBalance()` formula (line ~420-430), redirect `handleDeletedPayment()` to it (line ~377-380), remove now-unused `$balanceService` property/constructor param (lines 20, 24, 27)
- `routes/console.php` — add new `credit-cards:balance-audit` (or similar) closure command for the at-risk report (D-10)
- `database/factories/CreditCardFactory.php` — add `opening_balance` default (0.00, matching `current_balance`'s existing default)
- Tests: `tests/Unit/CreditCardCreditLineSyncTest.php`, `tests/Unit/CreditCardKpiServiceTest.php` — must pass with ZERO changes per D-06; verified achievable via the `creating`-event bridge described in section 7 above, `tests/Unit/CreditCardCycleServiceTest.php::duplicate_payment_unmark_sync_does_not_double_restore_balance` (re-verify, D-07), `tests/Feature/CreditCardLifecycleIntegrationTest.php::repeated_mark_paid_requests_do_not_drift_card_balance` (re-verify, D-07)

## RESEARCH COMPLETE
