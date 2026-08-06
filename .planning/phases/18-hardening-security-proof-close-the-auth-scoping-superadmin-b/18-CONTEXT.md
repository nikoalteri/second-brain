# Phase 18: Hardening & Security Proof - Context

**Gathered:** 2026-08-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 18 closes three specific, already-identified hardening gaps with real automated tests, and fixes any high-severity bug that testing confirms is real: (1) auth-context scoping and superadmin-bypass proof gaps around the 14 current `withoutGlobalScopes()` call sites, (2) `HasUserScoping`'s untested behavior in non-HTTP contexts (scheduled jobs/commands), and (3) credit-card cycle lifecycle race conditions and observer-chain fragility. It is a proof-and-targeted-fix phase, not a broad refactor or feature-enhancement phase.

</domain>

<decisions>
## Implementation Decisions

### Proof breadth
- **D-01:** Cover all three risk areas (scoping/superadmin bypass, cross-user permission tests, credit-card lifecycle race conditions) within this single phase rather than splitting into a smaller first slice — they are distinct, bounded areas that together form one coherent hardening pass, not an open-ended sweep.

### Proof-then-fix policy
- **D-02:** When a test confirms a real bug, fix severity determines action:
  - **High severity** (cross-user data disclosure, corrupted/drifted balances, broken money math) → fix in this same phase, with a regression test proving the fix.
  - **Lower severity** (cosmetic, narrow edge case, no data-integrity or disclosure impact) → document the gap (mirroring the `deferred-items.md` pattern already used in Phase 17) rather than fixing here, to keep scope from ballooning.
- **D-03:** This is NOT a proof-only phase — unlike Phase 16's discovery-first stance, Phase 18 explicitly allows and expects fixes for the high-severity class defined in D-02.

### Non-HTTP auth context
- **D-04:** Explicitly test `HasUserScoping`'s `auth()->check()` behavior in non-HTTP contexts — specifically the scheduled jobs already shipped (credit-card cycle issuing, subscription renewal posting, loan payment posting reminders). If scoping silently no-ops or misbehaves outside an HTTP request lifecycle, that is exactly the kind of proof gap this phase exists to close.

### `withoutGlobalScopes()` proof strategy
- **D-05:** Use both a generic/pattern-based regression test (asserting no authenticated route leaks cross-user data) AND targeted tests on the specific high-risk call sites already identified in this discussion:
  - `app/Http/Controllers/Api/V1/DashboardController.php:94`
  - `app/GraphQL/Queries/TotalByCategory.php:19`
  - `app/GraphQL/Queries/TransactionCategories.php:13,15`
  - `app/Services/FinanceReportService.php:236`
  - `app/Models/Transaction.php:72`
  - The 7 Filament Resource classes using `withoutGlobalScopes()` (Roles, Permissions, AuditLogs, UserSettings, Accounts, Backups, Notifications) — these are already gated by the admin panel's own access control, but should still get an explicit cross-user assertion, not just an assumption that panel-level auth is sufficient.

### Claude's Discretion
- Exact test file organization and naming (e.g., one `ScopingSecurityTest.php` vs. per-domain test additions to existing Feature test files)
- Whether the credit-card cycle race-condition proof uses a true concurrency test (parallel requests) or a sequenced test that reproduces the exact interleaving described in `.planning/codebase/CONCERNS.md`
- Whether `CreditCardExpenseObserver`'s previously-flagged static-state leak (from `.planning/codebase/CONCERNS.md`, dated 2024-12-19) is still present in current code — this must be re-verified against current code during research/planning, not assumed from the (possibly stale) codebase map
- Exact scope of "cross-user permission test expansion" beyond the specific `withoutGlobalScopes()` sites already listed above

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Prior confidence-boundary and proof-first precedent
- `.planning/phases/16-proof-first-validation-of-structural-finance-surfaces/16-CONTEXT.md` — establishes the proof-first discipline this phase continues, including the D-01/D-02 "smaller high-leverage slice" pattern this phase's D-01 deliberately diverges from (explicitly covering all three areas instead)
- `.planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md` — authoritative validated vs. structural-only ledger; this phase's fixes must not silently promote structural-only domains as a side effect

### Codebase concern sources (this phase's scope was derived from these)
- `.planning/codebase/CONCERNS.md` — Security Considerations section (`withoutGlobalScopes()` usage, `HasUserScoping` auth-context dependency, role-check reliance) and Known Bugs section (credit-card cycle status race condition, observer chain fragility) — **dated 2024-12-19, re-verify every cited line/file against current code before planning**
- `.planning/codebase/ARCHITECTURE.md` — service-layer/observer architecture the fixes must respect
- `.planning/codebase/STRUCTURE.md` — repository layout for locating scoping/observer/job code
- `.planning/codebase/TESTING.md` — existing test conventions and proof-gap notes

### Reference deferred-work bucket this phase promotes
- `.planning/ROADMAP.md` — "Deferred Hardening, Security, and Performance Concerns" section, which named the three areas this phase closes

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Traits/HasUserScoping.php` — the trait under test; `auth()->check()` + `auth()->user()?->hasRole('superadmin')` global scope plus `scopeWithoutUserScope()` escape hatch
- Existing scheduled jobs (credit-card cycle issuing, subscription renewal, loan payment posting) — the non-HTTP contexts D-04 targets for scoping tests
- `app/Services/CreditCardCycleService.php` — `DB::transaction()` already wraps cycle/payment mutations (lines ~100, 147, 247, 301); race-condition proof should target the interleaving windows around these transactions, not assume no protection exists at all
- Phase 17's `deferred-items.md` pattern (`.planning/phases/17-custom-read-only-finance-chatbot-engine/deferred-items.md`) — the established convention for documenting out-of-scope/lower-severity findings without fixing them

### Established Patterns
- `HasUserScoping` global scope + `withoutGlobalScope('user')` escape hatch is the established per-model scoping idiom; some models (Loan/CreditCard payment children) instead use manual `whereHas('parent', ...)` ownership checks — both idioms already surfaced during Phase 17 and must be respected, not unified into a third pattern
- `DB::transaction()` wrapping is the established mutation-safety pattern for credit-card cycle/payment state changes

### Integration Points
- New scoping tests likely live alongside existing `tests/Feature/AccountAuthorizationTest.php`, `tests/Feature/TransactionAuthorizationTest.php`, `tests/Feature/LoanAuthorizationTest.php` conventions (Feature-level, `Sanctum::actingAs`, cross-user assertions)
- New job-context scoping tests need to invoke the scheduled command/job directly (not through HTTP) and assert on resulting data ownership

</code_context>

<specifics>
## Specific Ideas

- Live-verified during this discussion (2026-08-06): exactly 14 `withoutGlobalScopes()` call sites currently exist in `app/` (7 in Filament Resources, 7 in Controllers/GraphQL/Services/Models) — more than `CONCERNS.md`'s narrower list, confirming the codebase map needs re-grounding, not blind trust, during planning.
- `HasUserScoping.php`'s current implementation (`auth()->check() && ! auth()->user()?->hasRole('superadmin')`) was read in full during this discussion — any test must exercise this exact conditional, including the superadmin bypass branch.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. (The narrower "start with scoping only" option was considered and explicitly rejected per D-01.)

</deferred>

---

*Phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b*
*Context gathered: 2026-08-06*
