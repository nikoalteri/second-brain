# Phase 19: Revolving Credit Card Interest Engine Correctness - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-06
**Phase:** 19-revolving-credit-card-interest-engine-correctness-align-cycl
**Areas discussed:** Fix breadth, Billing-cycle period generalization, Fixed-payment/stamp-duty inclusion, Test/validation strategy

---

## Fix breadth

| Option | Description | Selected |
|--------|-------------|----------|
| Tutti e 4 insieme | All four defects in one phase since they share the same calculation engine | ✓ |
| Solo periodo ciclo + interessi giornalieri | Only the two most severe bugs; stamp-duty and direct_monthly deferred | |
| Decidi tu | Claude decides single-phase vs. sequential plans during research/planning | |

**User's choice:** Tutti e 4 insieme (Recommended option)
**Notes:** The billing-cycle period bug directly feeds the daily-balance interest sum — fixing them separately risks an internally inconsistent engine mid-phase.

---

## Billing-cycle period generalization

| Option | Description | Selected |
|--------|-------------|----------|
| Generico, per qualsiasi giorno di chiusura | Period derived from each card's own statement_day; works for any closing day, not just day 6 | ✓ |
| Specifico per chiusura giorno 6 | Faster, narrower fix targeted at this specific card | |

**User's choice:** Generico (Recommended option)
**Notes:** The app already has a per-card `statement_day` field; a narrow fix would leave the same bug for any future card with a different closing day.

---

## Fixed-payment / stamp-duty inclusion

| Option | Description | Selected |
|--------|-------------|----------|
| Flag configurabile per carta | New explicit per-card setting for whether the fixed payment includes stamp duty | ✓ |
| Sempre inclusivo | Hardcode inclusive-of-duty behavior for all cards, no new config | |

**User's choice:** Flag configurabile (Recommended option)
**Notes:** The validation doc itself proposes this as explicit configuration, anticipating other card issuers might exclude stamp duty from the fixed payment.

---

## Test/validation strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Dati sintetici equivalenti | Same rate/mechanics as the real card, but fictional fixture numbers — no real statement data in versioned test files | ✓ |
| I valori esatti degli estratti reali | Maximum fidelity to the real proof, but embeds the user's real financial figures in committed test files | |

**User's choice:** Dati sintetici equivalenti (Recommended option)
**Notes:** Protects the user's real financial data from ending up in versioned/committed test files, even though `/docs` (where the real statements live) is already gitignored — defense in depth.

---

## Claude's Discretion

- Exact migration/schema design for the new stamp-duty-inclusion flag
- Exact synthetic fixture numbers, as long as they exercise the same edge cases
- First-cycle period-start anchor when there's no previous cycle to derive from
- Whether direct_monthly gets a correct formula, gets removed, or gets a stronger warning — as long as it can't silently produce ~12x-too-high interest

## Deferred Ideas

None — discussion stayed within phase scope. Data backfill for existing cycles was implicitly out of scope (no real card data exists in the local database yet).
