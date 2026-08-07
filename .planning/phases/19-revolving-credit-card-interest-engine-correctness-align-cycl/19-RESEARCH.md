# Phase 19: Revolving Credit Card Interest Engine Correctness - Research

**Researched:** 2026-08-07
**Domain:** Laravel 12 backend service/calculator correctness fix (billing-cycle date math, day-by-day balance interest, payment breakdown, enum-driven interest formula)
**Confidence:** HIGH (all findings verified directly against current repo code, no external library research needed)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
## Implementation Decisions

### Fix breadth
- **D-01:** Fix all four defects in this single phase rather than splitting into a smaller first slice. They share the same calculation engine (`RevolvingCreditCalculator` + `CreditCardCycleService`) and are causally linked — the wrong cycle period directly feeds the wrong daily-balance interest sum — so partial fixes would leave the engine in an internally inconsistent state.

### Billing-cycle period generalization
- **D-02:** The period-start-date fix must be generic, derived from each card's own `statement_day` (period = the day after the previous cycle's closing date, through the current cycle's closing date) — not hardcoded to "day 6." This must work correctly for any `statement_day` value, including month-boundary edge cases (e.g. a card closing on day 30 in a 31-day month, or day 31 in a 30-day/February month), and for the very first cycle a card ever has (no "previous cycle" to anchor to).

### Fixed-payment / stamp-duty inclusion
- **D-03:** Whether a card's fixed payment amount is inclusive or exclusive of stamp duty becomes an explicit, configurable per-card setting (not a hardcoded always-inclusive behavior). Real Amex statements prove the inclusive case; the setting exists so other card issuers with exclusive-of-duty fixed payments aren't silently miscalculated by this fix. Needs a migration adding this field, a sensible default, and Filament form exposure.

### Test/validation strategy
- **D-04:** Regression tests must use synthetic fixture data (a fictional card with the same rate/limit/mechanics — 14% TAN, EUR 4,000 fido, EUR 250 fixed payment, EUR 2 stamp duty, daily-balance method) that reproduces the same calculation *pattern* proven against the real statements, NOT the user's real statement amounts or dates verbatim. This keeps personally-identifying financial specifics out of versioned test files while still proving the corrected formulas produce statement-consistent results. The real source documents (`docs/reference/credit-card-statements/*.pdf`, `docs/reference/credit-card-revolving-validation.md`) stay gitignored under `/docs` and are reference-only for research/planning — never copied into committed code, tests, or docs.

### Claude's Discretion
### Claude's Discretion
- Exact migration/schema design for the new stamp-duty-inclusion flag (column name, default value, whether it lives on `credit_cards` or `credit_card_cycles`)
- Exact synthetic fixture numbers for tests, as long as they exercise the same edge cases (multi-day cycle, mid-cycle payment, non-first cycle, month-boundary statement_day)
- How to handle the very first cycle's period-start anchor when there's no previous cycle (D-02) — whether to fall back to account/card creation date, first cycle's own statement-day-derived month start, or another well-reasoned anchor
- Whether the `direct_monthly` mode gets fixed to a mathematically correct alternative formula, disabled/removed as an option, or left with a stronger warning — as long as it can no longer silently produce ~12x-too-high interest if selected

### Deferred Ideas (OUT OF SCOPE)
## Deferred Ideas

None — discussion stayed within phase scope. Data backfill/migration for pre-existing (incorrectly calculated) cycles was implicitly out of scope since no real card data exists yet to backfill.
</user_constraints>

## Summary

This is a self-contained, backend-only bug-fix phase inside two existing classes: `App\Services\CreditCardCycleService` (cycle creation/lifecycle) and `App\Services\RevolvingCreditCalculator` (pure calculation engine, stateless, already unit-tested). All four defects described in CONTEXT.md were re-verified line-by-line against the current code `[VERIFIED: codebase read 2026-08-07]`. There is exactly **one** call site for `ensureCurrentMonthCycle()` beyond the scheduled command (`CreditCardExpenseService::resolveCycle()`), and exactly **two** call sites for `calculatePaymentBreakdown()` (both inside `CreditCardCycleService`: `issueCycle()` and `syncIssuedCycle()`). No GraphQL, dashboard, or SPA surface reads `period_start_date`/`statement_date` with calendar-month assumptions that the D-02 fix would break — the one dashboard aggregator that computes credit-card totals (`CreditCardKpiService`) uses its own independent `startOfMonth()`/`endOfMonth()` window over `spent_at`, unrelated to cycle period boundaries.

The codebase already has the exact date-field precedent needed for D-02 (previous cycle's own stored `statement_date` should be read directly, not recomputed) and for the payment-application fix in `calculateDailyBalances()` (`CreditCardPaymentPostingService` already establishes the `actual_date ?? due_date` fallback pattern used elsewhere for dating a payment, which the day-loop should mirror). There is no `paid_at`/`posted_at` column on `credit_card_payments` — the closest existing analog is `actual_date` (nullable `date` column, set when a payment is marked PAID).

For D-03 (stamp-duty inclusion flag), the safest default is `false` (exclusive, matching today's `calculatePaymentBreakdown()` behavior) because `tests/Unit/CreditCardDailyBalanceTest.php::calculates_payment_breakdown_from_cycle_with_daily_balance` and other existing regression tests already assert `total_due = installment + stamp_duty` (252.0 for a 250+2 fixture) — defaulting to inclusive would silently change behavior for every existing/future card that doesn't explicitly opt in, which CONTEXT.md's own D-03 language explicitly wants to avoid ("sensible default" + "not silently miscalculated"). The user's real Amex card would need this flag explicitly set to `true` post-migration — that's an expected one-time manual step, not a silent behavior change for other cards.

For D-04 (`direct_monthly`), there is real *test* usage (3 test files assert the current ~12x-too-high behavior as if it were correct), but zero real/seeded *data* usage — confirmed no `credit_cards` rows exist locally and no factory state defaults to `direct_monthly`. This means the safe, low-blast-radius fix is to correct the formula in place (or remove the case) and update the handful of tests that currently encode the wrong expectation as "correct," rather than needing a data migration.

**Primary recommendation:** Fix all three engine methods and the enum in `RevolvingCreditCalculator` + the one method in `CreditCardCycleService`, add one migration (new boolean column on `credit_cards`), update the existing test files in place (they already assert the *current wrong* behavior in several places and must be corrected, not just extended), and add new synthetic-data regression tests mirroring the validation doc's *pattern* (not its real figures).

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Billing-cycle period derivation | API / Backend (`CreditCardCycleService`) | Database (`credit_card_cycles` columns) | Cycle creation is a service-layer concern; dates are persisted, not recomputed per-request |
| Day-by-day balance + interest calculation | API / Backend (`RevolvingCreditCalculator`) | — | Pure, stateless calculation class; no persistence, no HTTP concerns |
| Fixed-payment/stamp-duty split | API / Backend (`RevolvingCreditCalculator`) | Database (`credit_cards.fixed_payment_includes_stamp_duty` new column) | Card-level config drives per-cycle calculation branching |
| `direct_monthly` formula/enum | API / Backend (`RevolvingCreditCalculator` + `InterestCalculationMethod` enum) | Frontend Server (Filament form validation) | Formula lives in calculator; Filament form/API request validation is where a stronger warning (if chosen) would surface |
| New config field exposure | Frontend Server (Filament `CreditCardForm` schema) | API / Backend (`StoreCreditCardRequest`/`UpdateCreditCardRequest`) | Card settings are edited via Filament admin panel and REST API; both need the new field wired for parity |

## Standard Stack

This phase introduces no new third-party dependencies. It works entirely within the existing stack:

| Component | Version | Purpose |
|-----------|---------|---------|
| Laravel Framework | 12.56.0 `[VERIFIED: composer show laravel/framework]` | App framework, migrations, Eloquent |
| PHP | 8.4.23 `[VERIFIED: php -v]` | Runtime |
| Carbon | bundled with Laravel 12 | Date math (`Carbon::day()`, `daysInMonth`, `copy()`, etc. — already used throughout `CreditCardCycleService`) |
| PHPUnit (via Laravel testing) | project's existing `tests/` convention, `#[Test]` attributes | Regression tests |
| Filament | existing admin panel (`app/Filament/Resources/CreditCards/`) | Card config form exposure for the new D-03 flag |

**No installation needed.** `[VERIFIED: no new packages required]`

## Architecture Patterns

### System Architecture Diagram

```
Scheduled command (credit-cards:generate-cycles, daily 02:00)
        │
        ▼
CreditCardCycleService::ensureCurrentMonthCycle(card, referenceDate)
        │  [D-02 fix: derive period_start_date from PREVIOUS cycle's stored statement_date,
        │   not startOfMonth(); fall back to a first-cycle anchor when no previous cycle exists]
        ▼
CreditCardCycle row (period_start_date, statement_date, status=OPEN)
        │
        │◄── CreditCardExpenseService::resolveCycle() also calls ensureCurrentMonthCycle()
        │    when posting an expense whose spent_at falls outside any existing cycle window
        ▼
[cycle reaches statement_date, --issue-ready flag set]
        ▼
CreditCardCycleService::issueCycle(cycle)
        │
        ▼
RevolvingCreditCalculator::calculatePaymentBreakdown(cycle)
        │
        ├─► isFirstCycle()? ─── yes ──► interest = 0
        │         │
        │         no
        │         ▼
        │   interest_calculation_method?
        │         │
        │    ┌────┴────┐
        │    ▼         ▼
        │ DAILY_BALANCE  DIRECT_MONTHLY
        │    │              │  [D-04 fix target: currently balance × (rate/100),
        │    │              │   ~12x too high vs. a true monthly-equivalent rate]
        │    ▼              │
        │ calculateDailyBalances(cycle)
        │    │  [D-02b/payment fix: currently only folds in expenses per day;
        │    │   must also fold in the PRINCIPAL portion of payments dated
        │    │   within the cycle, on their own date, symmetric to expenses]
        │    ▼
        │ calculateInterestFromDailyBalances(dailyBalances, rate)
        │    │
        │    └──────────────┘
        │         ▼
        │   interestAmount
        ▼
[D-03 fix: principal = fixedPayment − interest − (stampDuty if inclusive flag set);
 total_due = fixedPayment (if inclusive) or fixedPayment + stampDuty (if exclusive)]
        ▼
CreditCardCycle.update(interest_amount, principal_amount, stamp_duty_amount, total_due)
CreditCardPayment created (PENDING) with the same breakdown values
        │
        ▼
[user later marks payment PAID with actual_date]
        │
        ▼
CreditCardPaymentObserver::updated() → CreditCardCycleService::syncCycleAndCardFromPayment()
        │
        ▼
CreditCardCycleService::syncCardBalance() — recomputes current_balance from
  SUM(expenses.amount) − SUM(payments WHERE status=PAID).principal_amount
```

### Recommended Approach: D-02 (Billing-cycle period)

**Read the previous cycle's stored `statement_date` directly — do not recompute it.** `[VERIFIED: CreditCardCycleService.php:214-233 already firstOrCreate()s on (credit_card_id, period_month, period_start_date, statement_date), so every prior cycle's exact statement_date is already durably persisted]`

```php
// Sketch — not exact final code, illustrates the derivation order
$statementDate = $referenceDate->copy()->day(
    min((int) $card->statement_day, $referenceDate->daysInMonth)
); // existing clamping logic, keep as-is for the CURRENT cycle's closing date

$previousCycle = CreditCardCycle::query()
    ->where('credit_card_id', $card->id)
    ->where('statement_date', '<', $statementDate->toDateString())
    ->orderByDesc('statement_date')
    ->first();

$periodStartDate = $previousCycle
    ? $previousCycle->statement_date->copy()->addDay()
    : /* first-cycle anchor — see options below */;
```

This avoids re-deriving the previous month's clamped statement day (which would require re-running the `min(statement_day, daysInMonth)` logic against a different reference month and risks subtle off-by-one bugs at month boundaries) — the value is already sitting in the database from when that cycle was created.

**Month-boundary verification:** `[VERIFIED: existing clamping formula]` `min($card->statement_day, $referenceDate->daysInMonth)` already correctly handles `statement_day = 30` in February (clamps to 28/29) and `statement_day = 31` rolling into a 30-day month (clamps to 30). Reading the *previous cycle's own stored* `statement_date` sidesteps needing to re-derive this for the prior month at all — the stored value already reflects whatever clamping applied when that cycle was created.

**First-cycle anchor — two concrete options (Claude's discretion per CONTEXT.md):**

| Option | Period start | Tradeoff |
|--------|-------------|----------|
| A: Card creation/`start_date` | `card->start_date` (or `created_at` if `start_date` is null) | Matches "when the card entered the system." Risk: if a card is added to the app *after* it has real prior debt (e.g. migrating an existing physical card), the first cycle's period could be very long (weeks/months of pre-existing balance) or very short, distorting interest even though "first cycle has 0 interest by definition" already neutralizes this for interest purposes — it would only affect the *daily balance timeline* if D-02's fix is ever consumed by anything beyond interest calc. |
| B: First-cycle-month start (`statementDate->copy()->startOfMonth()`, i.e. today's current buggy default) | Calendar-month start of the first statement's month | Simple, predictable, matches existing behavior for the one case where it doesn't matter (first cycle has 0 interest regardless of period length, per `isFirstCycle()`). No new edge cases introduced. |

**Recommendation: Option B.** Since `isFirstCycle()` already forces interest to `0.0` regardless of period length or daily-balance timeline `[VERIFIED: RevolvingCreditCalculator.php:172-186, "else: First cycle has 0 interest by definition"]`, the first cycle's exact period-start anchor has **no effect on any currently-computed number** — it only affects the `period_start_date` column's display value and the length of `calculateDailyBalances()`'s day loop (which is never summed into interest for that cycle). Option B requires zero new logic (it's the current line already), is trivially testable, and defers any real-world complexity (e.g., a card added mid-history with pre-existing debt) to when/if that scenario actually needs a `start_date`-driven anchor. Flag this rationale explicitly to the user/planner since CONTEXT.md left it open — this is a reasoned recommendation, not a verified requirement.

**Other callers of `period_start_date`/`statement_date` audited — none break:**
- `CreditCardExpenseService::findMatchingCycle()` — matches by `whereDate('period_start_date', '<=', date) AND whereDate('statement_date', '>=', date)`, a pure range check that works identically regardless of what generates the range. `[VERIFIED: CreditCardExpenseService.php:169-184]`
- `CreditCardKpiService` — dashboard aggregation uses its own independent `startOfMonth()/endOfMonth()` window over `CreditCardExpense.spent_at`, entirely decoupled from cycle period boundaries. `[VERIFIED: grep of CreditCardKpiService.php shows no period_start_date/period_month reference]`
- `CyclesRelationManager` (Filament) — manual cycle create/edit form lets an admin type `period_start_date` and/or `period_month`; derives `period_month` as `Carbon::parse(period_start_date)->format('Y-m')`. Under the new scheme this will show the month the cycle *starts* in, not necessarily the month most of the cycle falls in (e.g. a cycle from Jul 7–Aug 6 will show `period_month = "2026-07"`). This is a **display semantics change**, not a functional break — flag it in the plan as a note, not a bug. `[VERIFIED: CyclesRelationManager.php:63-182]`
- `CreditCardCycleResource` (API) and `resources/views/scribe/*` — just serialize `period_month`/`period_start_date` as-is; no assumption baked in. `[VERIFIED: CreditCardCycleResource.php:15-16]`
- `credit_card_cycles` unique index is `(credit_card_id, period_start_date, statement_date)`, not `(credit_card_id, period_month)` — already safe for non-calendar-aligned periods. `[VERIFIED: migration 2026_03_19_091000]`

### Recommended Approach: Payment application in `calculateDailyBalances()`

**No `paid_at`/`posted_at` column exists on `credit_card_payments`.** `[VERIFIED: migration 2026_03_18_221200_create_credit_card_payments_table.php — columns are due_date, actual_date only]` The closest existing analog is `actual_date` (nullable `date`, set when a payment transitions to PAID), and the codebase already has a precedent for using it as the "effective date" of a payment with a fallback:

```php
// CreditCardPaymentPostingService.php:45 — existing precedent to mirror
$date = $payment->actual_date ?? $payment->due_date;
```

Design for `calculateDailyBalances()`: only payments with `status === PAID` should reduce the daily balance (an unpaid/PENDING payment hasn't actually reduced principal yet — this matches `syncCardBalance()`'s existing authoritative computation, which only sums `principal_amount` from `PAID` payments `[VERIFIED: CreditCardCycleService.php:372-374]`). For each such payment dated within `[period_start_date, statement_date]` (using `actual_date`, since a PAID payment should always have one set — the `due_date` fallback matters less here than in the posting-transaction case, but mirroring the same fallback keeps behavior consistent and defensive against any PAID-without-actual_date edge case), subtract only the **principal portion** (`payment->principal_amount`), never `interest_amount` or `stamp_duty_amount` — this matches the validation doc's formula conceptually (capital reduces by principal-attributed payment, not the full payment amount) `[CITED: docs/reference/credit-card-revolving-validation.md, formula section — referenced conceptually, no figures copied]`.

```php
// Sketch — mirrors the existing expense-grouping pattern at lines 51-56
$paymentsByDate = $cycle->creditCard->payments()
    ->where('status', CreditCardPaymentStatus::PAID)
    ->whereBetween('actual_date', [$startDate->toDateString(), $endDate->toDateString()])
    ->get()
    ->groupBy(fn ($p) => $p->actual_date->toDateString())
    ->map(fn ($payments) => $payments->sum('principal_amount'))
    ->toArray();

// inside the day loop, symmetric to the expense application:
if (isset($paymentsByDate[$dateStr])) {
    $currentBalance -= (float) $paymentsByDate[$dateStr];
}
$currentBalance = max(0.0, $currentBalance); // never go negative
```

**Important scoping note:** this queries `$cycle->creditCard->payments()`, not `$cycle->payments()` — a payment posted mid-cycle logically belongs to whichever cycle's date range it falls in for balance purposes, which may not be the same cycle it was originally issued against (e.g., an early/extra payment made against an *open* cycle before that cycle is even issued). Confirm this against the plan's chosen semantics; the validation doc's model is date-range-based, not payment-cycle-FK-based, for the daily-balance walk specifically (distinct from `calculatePaymentBreakdown()`'s own principal/interest split, which is cycle-scoped as today).

### Recommended Approach: D-03 (Fixed-payment/stamp-duty split)

**Migration:** add `fixed_payment_includes_stamp_duty` (boolean, `default(false)`) to `credit_cards`, following the existing pattern of the `2026_03_31_195535_add_interest_calculation_method_to_credit_cards.php` migration (single-column, non-breaking, `Schema::table()`). `[VERIFIED: existing migration file pattern]`

**Default recommendation: `false` (exclusive — today's current behavior).** Rationale: several existing regression tests already assert `total_due = fixedPayment + stampDuty` as correct for generic (non-Amex) fixture cards — e.g. `tests/Unit/CreditCardDailyBalanceTest.php::calculates_payment_breakdown_from_cycle_with_daily_balance` asserts `total_due === 252.0` for a `fixed_payment=250, stamp_duty_amount=2` fixture with no explicit flag set `[VERIFIED: CreditCardDailyBalanceTest.php:144-146]`. Defaulting to `true` would silently flip every *existing test's implicit expectation* and any future card that doesn't explicitly configure the flag — exactly the "silently miscalculated" risk D-03 calls out. `false` preserves current behavior for anyone who doesn't touch the new setting; the user's real Amex card must have the flag explicitly set to `true` as a one-time manual/seed step once the migration lands (no real `credit_cards` row exists locally yet, so there's no backfill migration needed — confirmed by CONTEXT.md's own "Deferred" section).

**Formula (both branches):**
```php
$stampDuty = (float) ($card->stamp_duty_amount ?? 0);
$includesStampDuty = (bool) ($card->fixed_payment_includes_stamp_duty ?? false);

if ($includesStampDuty) {
    $principalAmount = round(max(0.0, $fixedPayment - $interestAmount - $stampDuty), 2);
    $totalDue = round($fixedPayment, 2); // stamp duty already inside fixedPayment
} else {
    $principalAmount = round(max(0.0, $fixedPayment - $interestAmount), 2); // current behavior
    $totalDue = round($fixedPayment + $stampDuty, 2); // current behavior
}
```

**Filament form exposure:** `app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php` is the existing form-schema file (referenced by `CreditCardResource::form()`) — locate its `fixed_payment`/`stamp_duty_amount` field definitions and add a `Toggle::make('fixed_payment_includes_stamp_duty')` alongside them, matching the existing field-grouping/labeling conventions in that file. Also add the field to `StoreCreditCardRequest`/`UpdateCreditCardRequest` (`app/Http/Requests/Api/`) validation rules, mirroring how `interest_calculation_method` was added there (`['nullable', ...]` / `['sometimes', 'nullable', ...]`) `[VERIFIED: StoreCreditCardRequest.php:36, UpdateCreditCardRequest.php:37]`. *(Research did not read `CreditCardForm.php` directly — planner should open it before writing the exact field-placement task; the file path is confirmed to exist via `CreditCardResource::form()`'s reference to `CreditCardForm::configure()`.)*

### Recommended Approach: D-04 (`direct_monthly`)

Three options considered, per CONTEXT.md's request:

1. **Fix the formula to a true monthly-equivalent rate.** E.g. `monthlyRate = (1 + annualRate/100/365)^(365/12) - 1` (compound) or a simpler `annualRate / 100 / 12` (simple monthly-equivalent). This keeps the enum case and its intent ("apply a monthly rate directly, no daily walk") but makes the number order-of-magnitude correct.
2. **Remove the enum case entirely**, migrating the enum to a single implicit `daily_balance` behavior. Requires deciding what happens to the 3 existing tests that construct cards with `interest_calculation_method: 'direct_monthly'` and to the `Rule::in(['daily_balance', 'direct_monthly'])` validation in both API request classes and the Scribe-documented API contract (`resources/views/scribe/index.blade.php` references `direct_monthly` as a valid enum value in generated API docs — would need doc regeneration).
3. **Keep it, gate behind a stronger warning/validation** (e.g. reject `direct_monthly` selection in the Filament form/API unless an explicit confirmation flag is passed, or add a persistent Filament banner). Doesn't fix the underlying math, just adds friction.

**Recommendation: Option 1 (fix the formula).** Zero real/seeded data currently uses `direct_monthly` — confirmed no `credit_cards` rows exist locally (per CONTEXT.md's "Specifics" section) and no factory state defaults to it `[VERIFIED: grep of DIRECT_MONTHLY usage shows only explicit test-level `->create(['interest_calculation_method' => 'direct_monthly'])` calls, no factory default]` — so there is no data-migration/blast-radius concern, only test-file updates. Option 1 preserves the enum's existing surface area (validation rules, API docs, Filament label/description) while fixing the actual defect, which matches D-01's "fix all four defects" framing better than removing a documented public API option. The three affected test methods (`it_calculates_interest_using_direct_monthly_method`, `it_uses_direct_monthly_method_when_configured`, `daily_balance_and_direct_monthly_produce_different_results` in `RevolvingCreditCalculatorTest.php`, plus `daily_balance_interest_is_lower_than_direct_monthly_rate` in `CreditCardDailyBalanceTest.php`, plus the `interest_calculation_method: 'direct_monthly'` fixture in `CreditCardLifecycleIntegrationTest.php:219`) currently hardcode `75.88` as the "correct" direct-monthly result for a 542-balance/14%-rate fixture — these assertions must be corrected to whatever the new formula produces, not merely left in place. `[VERIFIED: grep results across test files]`

`InterestCalculationMethod::getDescription()` and `getLabel()` (lines 29-35 and 13-19 of the enum) should also be updated if the formula changes to avoid the docblock/UI text describing the old (wrong) formula.

### Anti-Patterns to Avoid

- **Recomputing the previous cycle's statement_date from scratch** instead of reading the stored column — reintroduces exactly the kind of derivation bug this phase is fixing, and duplicates logic that's already correct and persisted.
- **Applying the full payment amount (not just principal) to the daily balance** — would double-count the interest/stamp-duty portions, which are separate ledger lines per the validation doc's formula.
- **Silently defaulting the new stamp-duty-inclusion flag to `true`** — breaks existing test assertions and any future non-Amex card that doesn't opt in.
- **Leaving stale test assertions unexamined** — several existing tests assert today's *wrong* numbers as correct (`direct_monthly` = 75.88, `total_due` = 252.0 as the only correct answer for all cards). The plan must explicitly update these, not just add new tests alongside them.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date clamping for statement day at month boundaries | A new day-clamping helper | The existing `min((int) $card->statement_day, $referenceDate->daysInMonth)` expression, already in `ensureCurrentMonthCycle()` | Already correct and tested implicitly by the current (buggy-in-a-different-way) code; the bug is in *what it clamps for* (calendar month vs. prior-cycle-derived range), not the clamping arithmetic itself |
| Payment-date fallback logic | A new `paid_at`-style accessor or column | `actual_date ?? due_date`, the exact pattern already used in `CreditCardPaymentPostingService::upsertPostingTransaction()` | Precedent already exists in the codebase; adding a new column/concept would fragment "what date represents this payment" across two different mechanisms |
| Money rounding | Manual float truncation | `round($value, 2)`, used consistently throughout both `RevolvingCreditCalculator` and `CreditCardCycleService` | Matches existing house convention; floats are used (not a Money/Decimal value object) throughout this codebase's finance domain |

**Key insight:** Every piece of this fix has a directly analogous, already-correct pattern elsewhere in the same two files or their immediate collaborators (`CreditCardPaymentPostingService`, `CreditCardExpenseService`). The task is to make four internally-inconsistent methods follow patterns the codebase already uses correctly elsewhere, not to introduce new architecture.

## Runtime State Inventory

*(Included because this phase changes date-derivation and calculation logic that affects previously-created records — though scoped narrowly since no real card data exists yet.)*

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | No real `credit_cards`/`credit_card_cycles` rows exist in the local database — confirmed explicitly in CONTEXT.md's "Specifics" section (`"No real credit_cards row exists yet in the local database"`). Any existing rows are test-only (RefreshDatabase, wiped between test runs). | None — no data migration needed for this phase. |
| Live service config | N/A — no external services (n8n, Datadog, etc.) reference this domain. | None. |
| OS-registered state | The `credit-cards:generate-cycles --issue-ready` command is scheduled via Laravel's in-process `Schedule::command()` (`routes/console.php:88`), not an OS-level cron/Task Scheduler entry — no external re-registration needed after this fix. | None. |
| Secrets/env vars | None involved. | None. |
| Build artifacts | `resources/views/scribe/index.blade.php` (generated API docs) references `direct_monthly` as a valid enum value with example payloads — if D-04's formula changes (Option 1, recommended) the enum value itself is unchanged, so these docs remain valid without regeneration; only the *example numeric outputs* embedded in the doc (if any show computed interest) could go stale, but a targeted grep found no computed-interest example values baked into the Scribe doc for this endpoint — only the schema/enum values. | None strictly required; optionally regenerate Scribe docs (`php artisan scribe:generate`) as a wrap-up step if the plan wants docs fully current, but not load-bearing. |

## Common Pitfalls

### Pitfall 1: Forgetting `calculateDailyBalances()` is called from two places with different card-balance semantics
**What goes wrong:** `calculateDailyBalances()` starts from `$card->current_balance - $cycle->total_spent` (i.e., balance *before* this cycle's expenses) and then re-adds expenses day by day. If payments are folded in using the same subtraction approach without checking whether `current_balance` already reflects prior-cycle payments, double-counting or under-counting can occur.
**Why it happens:** `current_balance` is a rolling authoritative figure (`syncCardBalance()`), and the day-loop reconstructs a *within-cycle* timeline from it — mixing "cycle-scoped" and "all-time" payment sets during the reconstruction is an easy mistake.
**How to avoid:** Scope the payments query strictly to the `[period_start_date, statement_date]` date range (via `actual_date`), exactly as expenses are scoped to the cycle relation today, and reason explicitly about whether `current_balance` (the starting point) already had this cycle's own payments subtracted out (it likely has NOT, if the payment was made mid-cycle before the cycle closes, because `syncCardBalance()` runs synchronously on every PAID transition) — the day-loop needs to re-subtract them at their date to get a *correct daily* balance even though the *final* balance after the loop should reconcile with `current_balance`.
**Warning signs:** New regression tests where the last day's `dailyBalances[endDate]` doesn't match `$card->current_balance` after a mid-cycle payment — a good sanity assertion to add in tests.

### Pitfall 2: Test fixtures accidentally encoding real statement figures
**What goes wrong:** Copying exact numbers from `docs/reference/credit-card-revolving-validation.md` (e.g. the four-row interest table, or the 542/250/14% numbers referenced in *existing* comments like `RevolvingCreditCalculatorTest.php:184-227`) into new committed test files.
**Why it happens:** The existing test file already has comments like `// User's real case: 542 debt, 14% rate, expects 75.88 interest` — a natural (but wrong) instinct is to keep matching this existing style when adding new fixtures.
**How to avoid:** Per CONTEXT.md D-04, use the synthetic fixture explicitly locked in CONTEXT.md: 14% TAN, EUR 4,000 fido/credit_limit, EUR 250 fixed payment, EUR 2 stamp duty, daily-balance method — but with **fictional** dates/expense timelines/balances that exercise the same edge cases (multi-day cycle, mid-cycle payment, non-first cycle, month-boundary statement_day), not the real 542/1183.30/1909.98/etc. figures or the real 2026-04/05/06/07 statement dates.
**Warning signs:** Any new test with a comment referencing "real bank data," "real Amex," or "user's card" — these phrases already appear in the *existing* test file and are a smell for what NOT to replicate.

### Pitfall 3: `isFirstCycle()` ordering interacting with the D-02 period-start fix
**What goes wrong:** `isFirstCycle()` determines "first" by `orderBy('statement_date')->first()` among issued/paid/overdue cycles `[VERIFIED: RevolvingCreditCalculator.php:15-23]`. This is independent of `period_start_date` and unaffected by the D-02 fix — but a naive test/reviewer might assume changing period-start logic also changes first-cycle detection. It does not; keep this decoupled.
**Why it happens:** Both concepts involve "cycle ordering," inviting conflation.
**How to avoid:** No code change needed here; just don't accidentally touch `isFirstCycle()` while fixing `ensureCurrentMonthCycle()`.
**Warning signs:** N/A — flagging pre-emptively since it's adjacent code in the same class family.

## Code Examples

### Existing fallback-date pattern to mirror for payment dating
```php
// Source: app/Services/CreditCardPaymentPostingService.php:45
$date = $payment->actual_date ?? $payment->due_date;
```

### Existing expense-grouping-by-date pattern to mirror for payment-grouping-by-date
```php
// Source: app/Services/RevolvingCreditCalculator.php:51-56
$expensesByDate = $cycle->expenses()
    ->orderBy('spent_at')
    ->get()
    ->groupBy(fn($e) => ($e->posted_at ?? $e->spent_at)->toDateString())
    ->map(fn($expenses) => $expenses->sum('amount'))
    ->toArray();
```

### Existing migration pattern to follow for D-03's new column
```php
// Source: database/migrations/2026_03_31_195535_add_interest_calculation_method_to_credit_cards.php
// (single additive column on credit_cards, Schema::table(), reversible down())
Schema::table('credit_cards', function (Blueprint $table) {
    $table->string('interest_calculation_method')->default('daily_balance')->after('interest_rate')
        ->comment('daily_balance or direct_monthly');
});
```

## State of the Art

Not applicable — this is an internal correctness fix, not a library/ecosystem currency question. No external "old vs. current approach" table applies.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `CreditCardForm.php`'s exact field layout/grouping conventions for `fixed_payment`/`stamp_duty_amount` (file was located but not read in this research pass) | Architecture Patterns → D-03 → Filament form exposure | Planner/executor may place the new Toggle inconsistently with existing form conventions; low risk, easily corrected by reading the file before writing that task |
| A2 | Recommended monthly-equivalent formula for `direct_monthly` (Option 1: `annualRate/100/12` simple, or compound alternative) — CONTEXT.md explicitly left the exact remediation to discretion, and no authoritative source (bank statement, regulation) was consulted for which exact formula "correct monthly-equivalent" should use | Architecture Patterns → D-04 | If the planner picks a specific formula without user confirmation, it could still be mathematically defensible but not match any specific real-world card issuer's convention — low risk since D-04 explicitly has no current real usage to match against |
| A3 | Payments should be scoped to `$cycle->creditCard->payments()` by date range (not `$cycle->payments()` by FK) when folding into `calculateDailyBalances()` | Architecture Patterns → payment-application fix | If the actual intended model is FK-scoped (a payment only affects the cycle it was issued against), the date-range approach could pull in a payment from a different cycle if `actual_date` falls in this cycle's window but the payment's `credit_card_cycle_id` points elsewhere; needs explicit confirmation during planning, not just inferred from the validation doc's date-based formula |

**A1 and A3 are the two claims most worth a quick confirmation glance during planning** (reading `CreditCardForm.php` directly, and deciding FK-scoped vs. date-range-scoped payment folding) before locking task-level detail.

## Open Questions (RESOLVED — orchestrator decisions applied during planning)

1. **RESOLVED: FK-scoped.** Should `calculateDailyBalances()`'s payment query be FK-scoped (`$cycle->payments()`) or date-range-scoped (`$cycle->creditCard->payments()->whereBetween('actual_date', ...)`)?**
   - What we know: expenses are already cycle-FK-scoped (`$cycle->expenses()`) in the current code, but the validation doc's formula is date-based ("a payment must reduce capital on its own effective date," not "a payment belonging to this specific cycle").
   - What's unclear: whether a payment recorded against cycle N could ever have an `actual_date` that falls inside cycle N+1's window (e.g., paid late, after the next cycle already opened) — and if so, which cycle's daily-balance walk should see it.
   - Recommendation: default to FK-scoped (`$cycle->payments()`) for symmetry with the existing expense pattern and to avoid cross-cycle leakage; only fall back to date-range-scoped if a concrete test case in planning proves FK-scoping produces a wrong number against the validation doc's pattern.

2. **RESOLVED: flat `annualRatePercent / 100 / 12`, no compound math.** Exact `direct_monthly` replacement formula.
   - What we know: CONTEXT.md leaves this fully to discretion; three options are outlined above with a recommendation for Option 1 (fix in place).
   - What's unclear: the precise formula (simple `rate/12` vs. compound monthly-equivalent) — both are "not ~12x too high" but produce different numbers.
   - Recommendation: planner should pick the simpler `annualRate / 100 / 12` unless the user has a specific card issuer's monthly-equivalent convention in mind, since there is zero real usage today to validate against either way.

## Environment Availability

No external dependencies beyond the existing Laravel/PHP/MySQL(or SQLite-for-tests) stack already running in this repo — this phase is pure application code + one migration.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit via Laravel's testing layer (`Tests\TestCase`, `RefreshDatabase`, `#[Test]` attributes) `[VERIFIED: phpunit.xml, existing test files]` |
| Config file | `phpunit.xml` (repo root) |
| Quick run command | `php artisan test --filter=RevolvingCreditCalculatorTest` (or `CreditCardCycleServiceTest`, `CreditCardDailyBalanceTest`) |
| Full suite command | `php artisan test` (or `composer test`, per `composer.json`'s `"test"` script) |

### Phase Requirements → Test Map
No formal requirement IDs assigned yet (per phase description). Coverage is instead mapped to CONTEXT.md's D-01 through D-04:

| Decision | Behavior | Test Type | Automated Command | File Exists? |
|----------|----------|-----------|-------------------|-------------|
| D-02 | Generic period derivation from previous cycle's stored statement_date; month-boundary clamping; first-cycle anchor | unit | `php artisan test --filter=CreditCardCycleServiceTest` | Partial — existing file covers `calculateRevolvingPaymentBreakdown` (deprecated method) and race-condition sync tests, but has **no** existing test for `ensureCurrentMonthCycle()`'s period derivation at all → Wave 0 gap |
| D-02 (payment application) | Payments fold into daily-balance loop on their principal-reducing date | unit | `php artisan test --filter=RevolvingCreditCalculatorTest` or `CreditCardDailyBalanceTest` | Partial — `calculateDailyBalances()` is tested for expenses only, not payments → Wave 0 gap |
| D-03 | Stamp-duty-inclusive vs. exclusive fixed-payment split | unit | `php artisan test --filter=RevolvingCreditCalculatorTest` | Partial — existing `calculates_payment_breakdown_from_cycle_with_daily_balance` in `CreditCardDailyBalanceTest.php` asserts the exclusive-mode total_due (252.0); needs a companion inclusive-mode test → Wave 0 gap |
| D-04 | `direct_monthly` produces a plausible (not ~12x) monthly interest figure | unit | `php artisan test --filter=RevolvingCreditCalculatorTest` | Existing tests assert the *wrong* number as correct → must be corrected, not just extended |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=RevolvingCreditCalculatorTest` (fastest feedback loop, covers 3 of 4 defects directly)
- **Per wave merge:** `php artisan test --testsuite=Unit` then `php artisan test --testsuite=Feature` (covers `CreditCardLifecycleIntegrationTest.php`, which exercises the full issue-cycle → pay → sync flow end to end, including the `interest_calculation_method: 'direct_monthly'` fixture at line 219 that will need updating)
- **Phase gate:** Full suite (`php artisan test`) green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] New unit test(s) in `tests/Unit/CreditCardCycleServiceTest.php` (or a new dedicated file) covering `ensureCurrentMonthCycle()`'s period-start derivation across: normal month, `statement_day=30` in Feb, `statement_day=31` rolling into a 30-day month, first cycle (no previous), and non-"now" reference dates (mirroring `CreditCardExpenseService::resolveCycle()`'s usage pattern)
- [ ] New unit test(s) in `tests/Unit/RevolvingCreditCalculatorTest.php` or `CreditCardDailyBalanceTest.php` covering a mid-cycle PAID payment reducing the daily-balance walk on its `actual_date`, using the synthetic fixture (14% TAN, EUR 4,000 limit, EUR 250 fixed payment, EUR 2 stamp duty)
- [ ] New unit test(s) covering `fixed_payment_includes_stamp_duty = true` producing `total_due = fixedPayment` and `principal = fixedPayment - interest - stampDuty`, alongside the existing `= false` (default) test that must continue passing unchanged
- [ ] Corrected assertions (not new files) in the ~4 existing test methods that currently hardcode the wrong `direct_monthly` figure as correct (`RevolvingCreditCalculatorTest.php` ×3, `CreditCardDailyBalanceTest.php` ×1, `CreditCardLifecycleIntegrationTest.php` fixture at line 219)
- [ ] No new framework/config install needed — existing PHPUnit setup covers all of the above

## Security Domain

*(Included per default-enabled `security_enforcement`; this phase has minimal security surface.)*

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No | Unaffected — no auth logic touched |
| V3 Session Management | No | Unaffected |
| V4 Access Control | No | Unaffected — `CreditCardResource::getEloquentQuery()`'s user-scoping (already hardened in Phase 18) is untouched by this phase |
| V5 Input Validation | Yes | New `fixed_payment_includes_stamp_duty` boolean field must be validated in `StoreCreditCardRequest`/`UpdateCreditCardRequest` (`['boolean']` or `['nullable', 'boolean']`, matching the existing `interest_calculation_method` field's validation style) |
| V6 Cryptography | No | Unaffected |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Malformed/negative `stamp_duty_amount` or `fixed_payment` values causing negative `principal_amount`/`total_due` | Tampering (via API/Filament form) | Existing `max(0.0, ...)` guards throughout `RevolvingCreditCalculator` already defend against this pattern; the D-03 fix must preserve the same `max(0.0, ...)` clamping on `principalAmount` in both branches |
| Race condition between concurrent payment PAID transitions | Tampering / Repudiation | Already mitigated by Phase 18's `DB::transaction()` + authoritative `syncCardBalance()` recomputation pattern — this phase must not regress that by introducing a second, competing balance-mutation path inside `calculateDailyBalances()` (it must remain read-only/pure, consistent with the class's existing stateless design) |

## Sources

### Primary (HIGH confidence — direct codebase reads, 2026-08-07)
- `app/Services/CreditCardCycleService.php` — full file read
- `app/Services/RevolvingCreditCalculator.php` — full file read
- `app/Observers/CreditCardPaymentObserver.php` — full file read
- `app/Services/CreditCardPaymentPostingService.php` — full file read
- `app/Services/CreditCardExpenseService.php` (relevant sections) — read
- `app/Models/CreditCardPayment.php`, `app/Models/CreditCardCycle.php`, `app/Enums/InterestCalculationMethod.php` — full files read
- `database/migrations/2026_03_18_221200_create_credit_card_payments_table.php`, `2026_03_18_221000_create_credit_cards_table.php`, `2026_03_19_091000_update_credit_card_cycles_unique_index_for_date_ranges.php` — full files read
- `database/factories/CreditCardCycleFactory.php` — full file read
- `tests/Unit/RevolvingCreditCalculatorTest.php`, `tests/Unit/CreditCardCycleServiceTest.php`, `tests/Unit/CreditCardDailyBalanceTest.php` — full files read
- `app/Filament/Resources/CreditCards/CreditCardResource.php` — full file read
- `routes/console.php` — full file read (scheduled command + only external caller of `ensureCurrentMonthCycle`)
- `docs/reference/credit-card-revolving-validation.md` — read conceptually per D-04 privacy constraint; no figures copied into this document
- `.planning/phases/19-.../19-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/STATE.md` — full files read
- `php artisan --version`, `composer show laravel/framework`, `php -v` — verified via shell

### Secondary (MEDIUM confidence)
- None — no external web sources were needed for this phase; it is entirely internal-code research.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies, versions verified via composer/php directly
- Architecture: HIGH — every claim traced to a specific file/line in the current repo
- Pitfalls: HIGH — derived from direct inspection of existing test assertions that currently encode the bugs as "expected" behavior

**Research date:** 2026-08-07
**Valid until:** Effectively indefinite for this phase (internal code, no external API/library drift risk) — re-verify only if the underlying files change before planning executes (unlikely given no other concurrent phase touches these files).
