# Phase 21: Credit card balance recompute correctness - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update
**Areas discussed:** Balance model / fix approach, handleDeletedPayment() alignment, Test coverage, Data backfill

---

## Area selection

| Option | Description | Selected |
|--------|-------------|----------|
| Modello del saldo / approccio al fix | Opening_balance column vs. delta+locking vs. manual recompute action | ✓ |
| Riparazione dati esistenti | Audit/repair strategy for cards affected in production | |
| handleDeletedPayment() — allineare o rimandare | Align the delete path to the same authoritative recompute, or defer again | ✓ |
| Copertura di test / regressione | Whether to add an explicit race-condition regression test | ✓ |

**Note:** "Riparazione dati esistenti" was not explicitly selected as a standalone area, but resurfaced naturally once the backfill question became unavoidable during the Test coverage discussion — captured as its own set of decisions (D-09/D-10) below.

---

## Balance model / fix approach

| Option | Description | Selected |
|--------|-------------|----------|
| opening_balance + recompute | Add opening_balance column on CreditCard, fix syncCardBalance() formula to opening_balance + expenses − paid_principal | ✓ |
| Torna al modello a delta con locking | Revert payments to delta-based mutation with explicit lockForUpdate() | |
| Ricalcolo manuale via azione Filament | Keep current_balance freely editable, make recompute an explicit Filament action instead of automatic | |

**User's choice:** opening_balance + recompute (recommended option)
**Notes:** Preserves Phase 18's idempotent recompute-from-source approach; only the formula was wrong.

| Option | Description | Selected |
|--------|-------------|----------|
| Sì, come oggi current_balance | opening_balance stays freely editable after card creation | ✓ |
| No, bloccato dopo il primo salvataggio | Lock the field after first save | |
| Tu decidi | Defer to agent discretion | |

**User's choice:** Sì, come oggi current_balance

| Option | Description | Selected |
|--------|-------------|----------|
| Ricalcolo idempotente, nessun locking extra | Rely on the corrected formula's natural idempotency | ✓ |
| Locking esplicito (lockForUpdate) sulla riga carta | Add pessimistic locking as extra protection | |
| Tu decidi | Defer to agent discretion | |

**User's choice:** Ricalcolo idempotente, nessun locking extra

**Follow-up:** "More questions or move to next area?" → Move to next area.

---

## handleDeletedPayment() — allineare o rimandare

| Option | Description | Selected |
|--------|-------------|----------|
| Sì, allinealo in questa fase | Migrate handleDeletedPayment() to the same opening_balance-based recompute | ✓ |
| No, lascialo deferred come in Phase 18 | Keep scope narrow, re-log the gap in deferred-items.md | |

**User's choice:** Sì, allinealo in questa fase (recommended option)

| Option | Description | Selected |
|--------|-------------|----------|
| Sì, un unico punto di ricalcolo | Route create/update/delete all through syncCardBalance(), retire reversePrincipalPayment() for payments | ✓ |
| Tu decidi | Defer implementation detail to agent | |

**User's choice:** Sì, un unico punto di ricalcolo (recommended option)

**Follow-up:** "More questions or move to next area?" → Move to next area.

---

## Copertura di test / regressione

| Option | Description | Selected |
|--------|-------------|----------|
| Sì, riusa/estendi il test di Phase 18 | Re-verify CreditCardCycleServiceTest::duplicate_payment_unmark_sync_does_not_double_restore_balance still passes/extend it | ✓ |
| Basta la regressione sui due test esistenti | No dedicated race-condition test needed | |

**User's choice:** Sì, riusa/estendi il test di Phase 18 (recommended option)

| Option | Description | Selected |
|--------|-------------|----------|
| Sì | Add a migration/backfill test proving existing cards keep a stable balance | ✓ |
| No, basta la migrazione stessa | Rely on manual review only | |

**User's choice:** Sì (recommended option)

---

## Data backfill for existing cards (surfaced during Test coverage discussion)

| Option | Description | Selected |
|--------|-------------|----------|
| Migrazione neutra + revisione manuale post-deploy | opening_balance = 0 for all existing cards, no auto-reconstruction | |
| Fammi prima un report di quali carte sono a rischio | Artisan command/report listing cards with a payment created/updated since the bug's introduction, for manual cross-check against real statements | ✓ |
| Tu decidi | Defer strategy to agent | |

**User's choice:** Fammi prima un report di quali carte sono a rischio
**Notes:** This touches the user's real personal financial data (Fluxa is used with real credit card statements). The true pre-bug opening balance is not reconstructible from current data for affected cards, so the user wants a targeted report to manually verify against real statements rather than a blind neutral migration alone.

**Follow-up:** "Any remaining gray areas?" → Ready for context.

---

## Claude's Discretion

- Exact bug-introduction commit boundary for the at-risk-card report (approximate date 2026-08-06 given, to be re-verified via `git log` during planning/research)
- Exact naming/implementation of the at-risk-card report (Artisan command vs. Filament widget vs. other)
- Migration file naming and column type/decimal precision details (to mirror `Account.opening_balance` conventions)

## Deferred Ideas

None — discussion stayed within phase scope.
