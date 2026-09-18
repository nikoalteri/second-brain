# Phase 28: Automatic transaction categorization - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Suggest/assign a `TransactionCategory` for a transaction automatically, based on (in priority order) explicit user-defined rules, then historical pattern-matching against the user's own past categorizations. No categorization infrastructure exists yet — `grep -rln "CategorizationRule" app/` returns nothing. Applies going forward only; existing uncategorized transactions are not touched automatically.

</domain>

<decisions>
## Implementation Decisions

### Categorization sources and precedence
- **D-01:** Two sources, in this priority order: (1) explicit user-defined rules (pattern → category, e.g. "description contains 'SPOTIFY' → Entertainment"), checked first; (2) historical match — if no rule matches, look at the user's own past `Transaction` records with a similar description and reuse the category they were assigned, if a confident match exists. Rules always win when both would apply.
- **D-02:** New `CategorizationRule` model/table (or equivalent) is needed for (1) — user-facing management surface (create/edit/delete rules) is implementation discretion (likely a new Filament resource, following existing resource conventions).
- **D-03:** For (2), the exact matching algorithm (substring/normalization vs fuzzy match, minimum confidence to auto-apply) is implementation discretion — should be grounded in what's cheap and reliable given this is a single-user dataset, not a general-purpose ML matching problem.

### Where it applies and how
- **D-04:** For transactions created via manual entry (SPA/REST API), the suggested category is **assigned silently** — no separate confirmation step, consistent with how a category is normally entered anyway.
- **D-05:** For draft rows created during Phase 25's bank-statement import review, the suggested category is shown as a **pre-filled suggestion the user reviews and can override** before confirming — consistent with Phase 25's existing "nothing becomes real until confirmed" review flow (25-CONTEXT.md D-05). Do not silently assign categories to import drafts the same way as D-04 — the import review step already exists specifically to catch exactly this kind of thing before it becomes a real record.
- **D-06:** Applies going forward only. Existing transactions without a category are **not** automatically recategorized as part of this phase — no retroactive backfill, no migration touching historical data.

### The agent's Discretion
- Exact `CategorizationRule` schema (pattern type: plain substring, case-insensitive contains, or a small set of match types)
- Matching-confidence threshold for the historical-match source (D-03)
- Whether the historical match looks only within the same account/card or across all the user's transactions
- Filament UI for managing rules

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Integration points
- `app/Http/Requests/Api/StoreTransactionRequest.php`, `app/Http/Requests/StoreTransactionRequest.php` — where a manually-created transaction is validated/persisted; the silent-assign (D-04) hooks in around here or in the underlying creation service, whichever the researcher finds is the single source of truth for transaction creation
- `app/Models/Transaction.php` — `transaction_category_id` fillable field
- `app/Models/TransactionCategory.php` — categories are user-scoped (`HasUserScoping`), hierarchical (`parent_id`/`children()`) — rules and history-matching must respect per-user category sets, never cross-user

### Sibling phase this depends on for one of its two integration points
- `.planning/phases/25-bank-statement-import-reconciliation/25-CONTEXT.md` — the import draft/review flow (D-05 there) is where this phase's suggest-not-assign behavior (D-05 here) plugs in; the staging data model Phase 25 settles on must have a field for a suggested-but-unconfirmed category

No external specs — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- None yet — this is genuinely new infrastructure, unlike Phases 22/24/26 which mostly composed existing services

### Established Patterns
- `HasUserScoping` trait — any new `CategorizationRule` model must use it, consistent with every other user-owned model in this codebase

### Integration Points
- Transaction creation path (manual entry) — D-04
- Phase 25's import draft review — D-05

</code_context>

<specifics>
## Specific Ideas

The user's own example: a transaction described "SPOTIFY" should be categorized the same way (e.g., "Entertainment") as previous similarly-described transactions, without manual re-entry each time.

</specifics>

<deferred>
## Deferred Ideas

- Retroactive recategorization of existing uncategorized transactions — user explicitly chose "going forward only" for this phase; a future manual/opt-in command could add this later without changing this phase's scope

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

## Audit-derived requirements (added 2026-09-18)

Source: the 2026-09-17 project audit (kept locally under `.planning/audits/2026-09-17/`, not tracked). These are **proposed requirements to settle during `/gsd-plan-phase`**, not decisions already taken; the discuss-phase decisions above stay authoritative unless the maintainer changes them. Foundations already delivered by the audit hardening are listed in ROADMAP.md ("Audit hardening").

- An explicit user category always wins over a suggestion.
- Learn only from the same owner's history and only from confirmed rows, never from duplicate or unconfirmed imported rows.
- Record provenance (rule / history / confidence) and keep every assignment correctable.
- Keep category similarity separate from payment identity: two identical purchases are not automatically duplicates.
- Limit rule patterns to simple ones; no arbitrary user regex without protection against pathological cost.

---

*Phase: 28-automatic-transaction-categorization*
*Context gathered: 2026-09-17*
