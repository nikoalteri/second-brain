# Phase 27: Automated statement reconciliation - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Evolve the manual `php artisan credit-cards:balance-audit` command (Phase 21) into an automatic check: once a bank-statement import batch (Phase 25) has been confirmed and its optional declared "statement ending balance" (Phase 25's D-06 addendum) is present, compare that number against Fluxa's own computed balance for the same account/card as of the same date, and notify (via Phase 22's `Notification` delivery) when they don't match. This is a true external-truth reconciliation, not merely an internal math self-check — it can only run for import batches where the user provided the statement's real ending balance.

</domain>

<decisions>
## Implementation Decisions

### Comparison mechanism
- **D-01:** Reconciliation is driven by the statement ending balance captured per import batch (Phase 25, D-06), not by re-deriving a "truth" from the imported transactions themselves — imported rows become ordinary `Transaction`/`CreditCardExpense` records once confirmed, so they can't also serve as an independent check on the balance they contributed to.
- **D-02:** For an import batch with a declared ending balance, compute Fluxa's own balance for that account/card as of the batch's statement end date (same style of computation the existing `credit-cards:balance-audit` command already does for credit cards — `opening_balance + expenses − paid principal` — and the equivalent account-side balance derivation for `Account`) and compare. A mismatch (any non-zero difference — no tolerance/rounding band decided here, agent's discretion below) triggers a notification.
- **D-03:** This phase does **not** replace the manual `credit-cards:balance-audit` command — that remains available for ad-hoc manual review of any card, reconciled or not. This phase adds an automatic check that only fires when real external data (an imported statement's ending balance) is available to compare against.

### Trigger and delivery
- **D-04:** Runs as part of confirming an import batch (Phase 25) — not a separate schedule — since the comparison is only meaningful once the batch's transactions are confirmed and the declared ending balance is known. Reuses Phase 22's `Notification` delivery and its "one notification per active condition" dedup principle: a mismatch for a given import batch should notify once, not repeatedly.
- **D-05:** Scope is both accounts and credit cards, mirroring Phase 25's own scope (D-03 there).

### The agent's Discretion
- Exact tolerance for "mismatch" — an exact-cents comparison vs. a small rounding tolerance (e.g., ±0.01€) to avoid false positives from floating-point/rounding artifacts elsewhere in the app
- Whether a reconciled/unreconciled state is persisted on the import batch (e.g., `reconciled_at`, `reconciliation_diff`) for later reference, or the check is purely "notify once and forget" — persisting is likely useful given the user will want to look back at which imports were clean
- Exact notification payload wording and `related_model`/`related_id` linkage

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Hard dependencies — must exist before this phase can be planned in detail
- `.planning/phases/25-bank-statement-import-reconciliation/25-CONTEXT.md` — the import batch concept and its D-06 "statement ending balance" field this phase reads; **this phase cannot be planned until Phase 25's actual data model (batch entity, ending-balance field) is settled during Phase 25's own planning**
- `.planning/phases/22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers/22-CONTEXT.md` — notification delivery and dedup model this phase reuses

### Existing balance computation to reuse (not reimplement)
- `app/Services/CreditCardCycleService.php::syncCardBalance()` — the authoritative `opening_balance + expenses − paid principal` formula (Phase 21) — reuse its computation logic, or the method itself, for the credit-card side of the comparison
- `routes/console.php` — `credit-cards:balance-audit` command (Phase 21) — closest existing precedent for "compute and report a card's balance breakdown for manual review"; this phase automates a narrower version of the same idea
- `app/Models/Account.php` — `balance`/`opening_balance` fields for the account-side equivalent comparison

No external specs — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CreditCardCycleService::syncCardBalance()` — exact formula to reuse for the credit-card half of the comparison
- Phase 22's `Notification` delivery — reuse for surfacing mismatches

### Established Patterns
- `credit-cards:balance-audit` already demonstrates listing/reporting balance breakdowns per card for manual review — this phase's automatic check is a narrower, triggered variant of the same computation

### Integration Points
- Wherever Phase 25's import-batch "confirm" action lives — this phase's check runs at that point
- `Notification::create()` call site (Phase 22 establishes the pattern)

</code_context>

<specifics>
## Specific Ideas

The user's own example during discussion: importing September's statement, entering "saldo al 30/09: 2.450,00€" from the real bank statement, Fluxa computing 2.410,00€ internally, and a 40€ discrepancy being notified — this is the concrete worked example the implementation should match.

</specifics>

<deferred>
## Deferred Ideas

- A pure internal-consistency check (balance before + sum of imported amounts = balance after, with no external statement figure) was considered and explicitly rejected in favor of the real-statement-balance comparison — noted here so a future phase doesn't reintroduce it as if it were the original intent
- Tolerance/rounding-band configuration — agent's discretion for now, could become user-configurable later if it proves too strict/loose in practice

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

## Audit-derived requirements (added 2026-09-18)

Source: the 2026-09-17 project audit (kept locally under `.planning/audits/2026-09-17/`, not tracked). These are **proposed requirements to settle during `/gsd-plan-phase`**, not decisions already taken; the discuss-phase decisions above stay authoritative unless the maintainer changes them. Foundations already delivered by the audit hardening are listed in ROADMAP.md ("Audit hardening").

- The formula `opening_balance + expenses - paid principal` is a current/principal balance. The issuer's ending balance may include interest and fees and is not necessarily the same quantity: identify which balance type the external figure is and which components it includes.
- Compute as of a date, using a dated opening balance, posted vs spent, and actual payments. `RevolvingCreditCalculator::calculateDailyBalances()` now derives the cycle opening balance from the ledger (PR #16); `calculatePaymentBreakdown()` still uses the current balance, which is a decision to take here.
- Use the statement's dates, not the calendar month or today's balance. Store the comparison, the difference, a status and a version, and recompute after relevant corrections.
- A tolerance must not mask systematic bugs.

---

*Phase: 27-automated-statement-reconciliation*
*Context gathered: 2026-09-17*
