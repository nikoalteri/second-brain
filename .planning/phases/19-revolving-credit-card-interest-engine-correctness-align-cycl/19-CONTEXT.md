# Phase 19: Revolving Credit Card Interest Engine Correctness - Context

**Gathered:** 2026-08-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 19 corrects the revolving-credit-card interest engine so its math matches real-world card-issuer statement math (verified against a real Amex card's statements), across four linked defects: the billing-cycle period, day-by-day payment application in the daily-balance interest calculation, the fixed-payment/stamp-duty split, and the `direct_monthly` interest mode's formula. It is a correctness/bug-fix phase for an existing structural-only feature, not a new feature or a UI phase — no frontend behavior changes are in scope beyond whatever numbers naturally change as a result of the corrected backend math.

</domain>

<decisions>
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
- Exact migration/schema design for the new stamp-duty-inclusion flag (column name, default value, whether it lives on `credit_cards` or `credit_card_cycles`)
- Exact synthetic fixture numbers for tests, as long as they exercise the same edge cases (multi-day cycle, mid-cycle payment, non-first cycle, month-boundary statement_day)
- How to handle the very first cycle's period-start anchor when there's no previous cycle (D-02) — whether to fall back to account/card creation date, first cycle's own statement-day-derived month start, or another well-reasoned anchor
- Whether the `direct_monthly` mode gets fixed to a mathematically correct alternative formula, disabled/removed as an option, or left with a stronger warning — as long as it can no longer silently produce ~12x-too-high interest if selected

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Source of truth for correct math (gitignored, local-only, reference-only — never copy real figures into committed code/tests)
- `docs/reference/credit-card-revolving-validation.md` — the user-authored validation doc: observed Amex rules, the correct interest formula, the correct principal/interest/stamp-duty split formula, and a 4-row table of real verified interest amounts (dates redacted from any committed artifact per D-04)
- `docs/reference/credit-card-statements/*.pdf` — 5 real Amex statements (2026-03-06 through 2026-07-06) backing the validation doc; read-only reference for confirming edge-case behavior (e.g. how the very first cycle with zero interest is represented) if needed during research

### Current (incorrect) implementation — the exact fix targets
- `app/Services/CreditCardCycleService.php:197-229` (`ensureCurrentMonthCycle`) — hardcoded `startOfMonth()` period start; this is D-02's fix target
- `app/Services/RevolvingCreditCalculator.php:31-74` (`calculateDailyBalances`) — never applies payments within the day-by-day loop, only expenses; this is the payment-application fix target
- `app/Services/RevolvingCreditCalculator.php:142-202` (`calculatePaymentBreakdown`) — principal calculation doesn't subtract stamp duty from the fixed payment (233.93 expected vs 235.93 computed, a confirmed 2 EUR/cycle drift), and `total_due` wrongly adds stamp duty on top instead of treating it as already included; this is D-03's fix target
- `app/Services/RevolvingCreditCalculator.php:109-128` (`calculateInterestDirectMonthly`) — applies the annual rate directly as a monthly rate; not the default (`daily_balance` is), but a live landmine if selected

### Prior phase precedent this phase continues
- `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/18-CONTEXT.md` — the D-02 "fix high-severity, document lower-severity" proof-then-fix pattern this phase's D-01 continues (though Phase 19 fixes all four since they're causally linked, not severity-triaged)
- `.planning/codebase/CONCERNS.md` (re-grounded 2026-08-06 by Phase 18) — should be re-checked for any credit-card-cycle notes still relevant to this fix

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Services/CreditCardCycleService.php` — owns cycle creation (`ensureCurrentMonthCycle`) and payment posting (`recordPayment`/similar); already wraps mutations in `DB::transaction()` per Phase 18's race-condition fix — the period-start fix must respect this transaction boundary
- `app/Services/RevolvingCreditCalculator.php` — the isolated, already-unit-tested calculation engine (`tests/Unit/RevolvingCreditCalculatorTest.php` exists) — the natural home for all four fixes, keeping calculation logic out of the service/observer layer
- `app/Enums/InterestCalculationMethod.php` — the `daily_balance` / `direct_monthly` enum; `direct_monthly`'s fix or removal happens here and in its one call site
- Existing credit-card migrations under `database/migrations/2026_03_18_*` through `2026_03_31_*` — the pattern to follow for D-03's new stamp-duty-inclusion column

### Established Patterns
- Card-level configuration (rate, limit, fixed payment, stamp duty, statement/due day) lives on the `credit_cards` table as plain columns with Filament form fields — D-03's new flag should follow this exact pattern
- `RevolvingCreditCalculator` is a stateless calculation class taking model instances and returning arrays — no observer/event side effects inside it; fixes should preserve this separation

### Integration Points
- `CreditCardCycleService::issueCycle()` / cycle-closing flow calls into `RevolvingCreditCalculator::calculatePaymentBreakdown()` — verify this call site after D-02/D-03 fixes since the breakdown's `principal_amount`/`total_due` shape may need contract review by callers (e.g. `CreditCardPaymentPostingService`)
- No SPA/GraphQL screens currently surface per-day interest math directly (confirmed no frontend scope needed) — dashboard/report totals derive from `current_balance`/cycle aggregates, which will simply reflect corrected numbers once the engine is fixed

</code_context>

<specifics>
## Specific Ideas

- Live-verified during discussion (2026-08-06): two real statements (2026-04-06, 2026-05-06) were read and their "Informazioni relative agli interessi del periodo" tables cross-checked against `credit-card-revolving-validation.md`'s summary table — both matched exactly (EUR 14.07 / EUR 1,183.30 average principal / 31 days; EUR 21.98 / EUR 1,909.98 / 30 days), and the "Quota Capitale" line item (233.93 = 250.00 − 14.07 − 2.00) confirmed the exact stamp-duty-inclusion formula in the validation doc.
- No real `credit_cards` row exists yet in the local database — this is a pure code-correctness fix, not a live-data remediation.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. Data backfill/migration for pre-existing (incorrectly calculated) cycles was implicitly out of scope since no real card data exists yet to backfill.

</deferred>

---

*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Context gathered: 2026-08-06*
