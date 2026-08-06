# Phase 18: Hardening & Security Proof - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-06
**Phase:** 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
**Areas discussed:** Proof breadth, Proof-then-fix policy, Non-HTTP auth context, withoutGlobalScopes() proof strategy

---

## Proof breadth

| Option | Description | Selected |
|--------|-------------|----------|
| Tutte e 3 insieme | Scoping/superadmin, cross-user permissions, and credit-card race conditions in one coherent phase | ✓ |
| Solo scoping/superadmin per ora | Highest-risk area only; the other two deferred to a future phase | |
| Decidi tu | Claude decides depth during research/planning, as long as scoping is covered | |

**User's choice:** Tutte e 3 insieme (Recommended option)
**Notes:** Unlike Phase 16, which deliberately started with a smaller high-leverage slice, the user chose to cover all three areas in this single phase since they're distinct and bounded rather than open-ended.

---

## Proof-then-fix policy

| Option | Description | Selected |
|--------|-------------|----------|
| Prova + fix quando il rischio è alto | Fix high-severity bugs (cross-user disclosure, corrupted balances) in-phase with a regression test; document lower-severity findings only | ✓ |
| Solo prova, mai fix | Pure verification phase; all bugs become material for a future fix phase | |
| Fix sempre, qualunque gravità | Fix every bug found regardless of severity | |

**User's choice:** Prova + fix quando il rischio è alto (Recommended option)
**Notes:** Establishes a severity threshold rather than an absolute proof-only or fix-everything stance — keeps scope bounded while still closing real high-severity gaps if found.

---

## Non-HTTP auth context

| Option | Description | Selected |
|--------|-------------|----------|
| Sì, verificarlo esplicitamente | Test HasUserScoping's auth()->check() behavior inside the existing scheduled jobs (card cycle issuing, subscription renewal, loan reminders) | ✓ |
| No, fuori scope | Limit proof to HTTP/controller paths only | |

**User's choice:** Sì, verificarlo esplicitamente (Recommended option)
**Notes:** Directly targets a concern already flagged in `.planning/codebase/CONCERNS.md` — untested scoping behavior in background/job contexts.

---

## withoutGlobalScopes() proof strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Entrambi | Generic pattern-based regression test as a safety net, plus targeted tests on the 14 already-identified call sites | ✓ |
| Solo test mirati | Targeted tests on the 14 known sites only, no generic framework | |
| Solo test generico | A single scanning mechanism over authenticated routes, no per-site tests | |

**User's choice:** Entrambi (Recommended option)
**Notes:** During this discussion, a live `grep` confirmed exactly 14 current `withoutGlobalScopes()` call sites in `app/` — more than the older `CONCERNS.md` doc listed, which is itself now noted in CONTEXT.md as something to re-verify (not trust blindly) during planning.

---

## Claude's Discretion

- Exact test file organization/naming for the new scoping and race-condition tests
- Whether the credit-card race-condition proof uses true parallel-request concurrency or a sequenced reproduction of the documented interleaving
- Re-verifying whether `CreditCardExpenseObserver`'s previously-flagged static-state leak is still present in current code (a live grep during this discussion found no `static $` declarations in `app/Observers/*.php`, suggesting it may already be fixed — planner/researcher must confirm)
- Exact scope of "cross-user permission test expansion" beyond the listed withoutGlobalScopes() sites

## Deferred Ideas

None — discussion stayed within phase scope. The narrower "scoping-only first slice" option was explicitly considered and rejected (see Proof breadth above).
