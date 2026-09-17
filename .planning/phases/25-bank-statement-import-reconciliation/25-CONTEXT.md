# Phase 25: Bank statement import & reconciliation - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Import transactions from user-exported CSV/Excel files (not PDF, not live Open Banking) into draft rows the user reviews and confirms before they become real `Transaction`/`CreditCardExpense` records, with duplicate detection against existing records. This is the largest and most novel of the four brainstormed phases — no comparable feature exists yet in the codebase. `maatwebsite/excel` (^3.1.68) is already a composer dependency (used for the existing finance CSV/XLSX exports), directly reusable for reading import files too.

</domain>

<decisions>
## Implementation Decisions

### File formats
- **D-01:** CSV and Excel (XLSX) only in this phase. PDF statement parsing is explicitly deferred — the user can export PDF, CSV, and Excel today, but PDF parsing is far more fragile (bank-specific layout, breaks on format changes) and is noted as a possible future phase, not committed here.

### Column mapping
- **D-02:** Configurable mapping, not a hardcoded format for one specific bank. On first import for a given account/card, the user is shown a preview of the file's rows and assigns which column is date / amount / description (/ category, if present). The mapping is saved and reused for that account/card's future imports. This makes the importer bank-agnostic instead of coupling it to one institution's export format — important since the exact bank/format was not specified during this discussion.

### Scope: accounts and credit cards
- **D-03:** Import targets both `Transaction` (for `Account`-based conti) and `CreditCardExpense` (for credit cards) — not just one. The mapping/preview/draft/confirm flow must work for both target entities, even though their underlying fields differ (see `app/Services/SubscriptionService.php::upsertTransaction()` vs `::upsertCreditCardExpense()` for the shape difference already established in the codebase).

### Duplicate detection
- **D-04:** Detect likely duplicates against existing records (match on date + amount, plus description similarity where useful) and surface them for confirmation instead of silently creating a duplicate or silently skipping. The user decides case-by-case during review (D-05), not the importer.

### Review before commit
- **D-05:** Imported rows land in a **draft/staging state** — reviewable and editable (category, amount correction, discard) — before being promoted to real `Transaction`/`CreditCardExpense` rows. Nothing from an import silently affects balances, budgets, or reports until the user explicitly confirms each row (or a batch). This is a stronger requirement than a simple "undo" — the record must not exist as a real financial fact until confirmed.

### The agent's Discretion
- Exact staging data model — most likely a new lightweight model (e.g., `ImportedTransaction` or similar) rather than adding a "draft" status to the core `Transaction`/`CreditCardExpense` models, to avoid polluting core finance models with import-specific state; confirm/promote logic converts a staging row into a real row using the same field mapping logic those models already use elsewhere (`SubscriptionService`'s upsert methods are the closest existing precedent for "build a Transaction/CreditCardExpense payload programmatically")
- Exact duplicate-matching algorithm/threshold (date window, amount exact-match, description fuzzy-match library or simple normalization)
- UI flow details for the mapping/preview/review screens (Filament vs SPA vs both — not decided, research should check which surface fits better given `maatwebsite/excel` is typically wired through Filament import actions in this ecosystem)
- Whether a mapping can be edited/re-applied later if it turns out wrong

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Reusable dependency
- `composer.json` — `maatwebsite/excel` (^3.1.68) already present; check existing usage (finance export feature, per `PROJECT.md`'s validated "finance exports download as CSV, XLSX, and PDF") for established import/export conventions in this codebase before introducing new patterns

### Target entities and their existing "build a record programmatically" precedent
- `app/Models/Transaction.php` — fillable fields, including `account_id`, `transaction_category_id`, `amount`, `date`, `description`
- `app/Models/CreditCardExpense.php` — the credit-card-side equivalent
- `app/Services/SubscriptionService.php::upsertTransaction()` / `::upsertCreditCardExpense()` — closest existing precedent for "construct a Transaction/CreditCardExpense payload from external data and persist it," including the existing dedup-by-lookup pattern (`Transaction::withTrashed()->where(...)->first()`) this phase's duplicate detection should draw on

### Scoping boundary (do not violate)
- `.planning/ROADMAP.md` "Deferred Longer-Term Product Ideas" — "Bank-feed or Open Banking expansion" remains explicitly out of scope. This phase is file-based import only; it must not evolve into a live bank API integration without a separate, explicit scoping decision.

No other external specs — the exact bank/statement format was deliberately left unspecified (see Deferred below); requirements captured above are format-agnostic by design (D-02).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `maatwebsite/excel` composer dependency — read CSV/XLSX without adding a new library
- `SubscriptionService`'s upsert methods — pattern to follow for "turn external data into a Transaction/CreditCardExpense," including the `withTrashed()`-based lookup-before-insert dedup shape

### Established Patterns
- Both target models use `HasUserScoping` — imports must be scoped to the authenticated user's own accounts/cards, never cross-user

### Integration Points
- Likely a new Filament resource/action (file upload → column-mapping step → preview/review → confirm) given Filament already hosts the admin-side finance workflows in this app, though research should confirm vs. an SPA-based flow

</code_context>

<specifics>
## Specific Ideas

None captured — the user has not yet specified which bank(s) or exact file layout(s) will be imported; the phase is scoped to work generically via configurable mapping (D-02) rather than around one known format.

</specifics>

<deferred>
## Deferred Ideas

- PDF statement parsing — user confirmed CSV/Excel are sufficient to start; PDF noted as a possible future phase for banks that only offer PDF exports
- Live Open Banking / bank-feed API integration — already explicitly out of scope per ROADMAP.md's Deferred Longer-Term Product Ideas bucket; this phase does not reopen that boundary

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

---

*Phase: 25-bank-statement-import-reconciliation*
*Context gathered: 2026-09-17*
