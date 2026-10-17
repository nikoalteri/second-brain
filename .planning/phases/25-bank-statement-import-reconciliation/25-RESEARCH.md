# Phase 25 — implementation research

**Prepared:** 2026-10-17 against `main`/`uat` (identical). Code takes precedence over `25-CONTEXT.md` where they differ.

## Confirmed from the code

- `maatwebsite/excel` 3.1.70 is installed (PhpSpreadsheet 1.30.7) but only used for **exports**; there is no import code. `composer audit` currently reports only `league/commonmark` (unrelated to this phase).
- `Transaction` creation moves `Account.balance` through `TransactionObserver` → `AccountBalanceService::handleCreated` (increment by signed amount); deletion reverses it. The saving hook forces the sign from `TransactionType.is_income`, so an imported row needs a type before promotion. Owner is taken from the account.
- `CreditCardExpense` creation is validated by `CreditCardExpenseObserver::creating` → `CreditCardExpenseService::validateExpenseChange` and needs a `credit_card_cycle_id`; promotion must reuse the same service path the API uses, not raw inserts.
- Existing HTTP validation (`StoreTransactionRequest`, `OwnedByAuthenticatedUser`) already enforces ownership of account and category; promotion must apply the same ownership checks.
- Unique indexes exist on `transactions.loan_payment_id`, `credit_card_payment_id` and the subscription renewal pair; there is no natural unique key for an arbitrary bank line.
- Private storage: the `local` disk is `storage/app/private`. The SPA (Vue + Pinia, `/api/v1`) is where users manage transactions; Filament is the admin hub. Tokens come from Sanctum, with per-route throttling in place on auth.
- Phase 24's forecast flags `bank_data_not_reconciled` unconditionally; Phase 27 will make it conditional, using this phase's `statement_ending_balance` (D-06).

## Planning decisions (proposed — items marked **ask** need the maintainer)

1. **Surface (ask):** REST API + SPA flow (upload → map → review → confirm), consistent with the transactions views and the owner-scoped API. Filament is not used for end-user finance workflows.
2. **Staging model:** `import_batches` (owner, target account *or* card, currency, status, mapping snapshot + version, optional `statement_ending_balance`, period dates, row counts, timestamps) and `import_rows` (batch, row number, raw cells JSON, normalised date/amount in minor units/description, status `draft|discarded|confirmed`, duplicate classification, fingerprint, user edits, `transaction_id` / `credit_card_expense_id` once promoted). No new status on core finance tables. Nothing touches balances until promotion.
3. **File handling:** CSV and XLSX only, chosen by content sniffing and extension together; size limit, row/column/cell limits and a decompressed-size and entry-count check on XLSX before reading; the first sheet only; formula cells are kept as text and never calculated, external links are not followed; parsing happens server-side on the framework's own temporary upload path. **Changed during execution (25-01):** the raw file is not stored at all. It is parsed in the request and each line is staged as an `import_rows` record whose cells are stored encrypted (`encrypted:array`), so mapping re-application and review work from the rows. An abandoned batch (never confirmed) is purged after 30 days by a scheduled command; a completed batch is never purged.
4. **Mapping (D-02):** per target account/card, a saved mapping: date / amount (single signed column *or* debit+credit columns) / description / optional category, plus explicit parsing options — date format, decimal and thousands separator, sign inversion (card statements list charges as positive), header row, delimiter. Parsing is deterministic and never locale-dependent. Mapping is versioned; re-applying an edited mapping re-normalises draft rows only.
5. **Currency:** every row is in the target's currency (account currency; card via its settlement account). A file currency column, if mapped, only flags mismatching rows for review; no conversion. A target with an invalid/unknown currency cannot be imported into.
6. **Duplicates (D-04):** a row fingerprint (target, date, amount, normalised description, occurrence index in file) prevents re-importing the same file; against the ledger, match same target, equal amount in minor units, date within ±3 days, description similarity as a tiebreak → `exact | probable | none`. Matches are proposals shown for review, never auto-skipped. Also flag: possible own-account transfer (equal opposite amount on another own account in the window), possible card payment (matches a `CreditCardPayment` total), possible subscription renewal already generated (matches a posted renewal). For a card batch, a charge that is also an imported account payment must not double the outflow: the review shows the link and the user decides.
7. **Confirmation (D-05):** per row or per selected batch. Each promotion runs in a DB transaction that locks the staging row, requires status `draft`, builds the record through the existing model/service path, stores the created id on the row and flips it to `confirmed`; a unique constraint on the promoted ids and the status check make a double confirmation a no-op. A batch confirmation reports per-row outcomes (confirmed / skipped / failed) and can be retried; partial progress is a defined state.
8. **Out of scope:** automatic categorisation (Phase 28: only an optional mapped category name matched to the user's own categories here), reconciliation against the ending balance (Phase 27: this phase only stores it), PDF, Open Banking.

## Risks to settle during execution

- Choosing the transaction *type* for an imported row (income vs expense from the sign) must match what the manual API does.
- Card cycle assignment for old statement dates (cycle may not exist) → row marked unconfirmable with a reason, not guessed.
- Memory/time for large files: chunked parsing with the row limit enforced before staging.
- Encrypted-at-rest file plus purge needs a scheduler entry with the existing error-isolation pattern.

## Edge cases to prove

Duplicate rows inside the same file; re-uploading the same file; two identical legitimate same-day charges (occurrence index); European vs English number formats; negative/positive sign inversion; debit+credit columns with an empty cell; quoted multiline CSV fields; formula-looking cells (`=…`) kept as text; XLSX with formulas, external links, a decompression bomb; wrong extension; another user's account/card/batch/row IDs; double confirmation (sequential and concurrent); confirming into a deleted/inactive account; card statement date before the card's first cycle.
