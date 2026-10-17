# 25-04 Summary — SPA import flow and verification

## What exists

- Route `/transactions/import` and an "Import statement" button on the Transactions page (`StatementImportView.vue`), four steps with a step indicator: **Upload** (target account or card, CSV/XLSX, optional ending balance and period) → **Map columns** (preview of the first rows, every option explicit, pre-filled from the target's saved mapping or a guess from the header and first line) → **Review** (counts, filters Ready / Need decision / Errors / Discarded / Confirmed / All, inline edit of description and amount, discard/restore, notes explaining each duplicate or look-alike, "Confirm all ready" and "Confirm selected", per-row failure reasons, "Load more") → **Done**. "Cancel import" deletes the unfinished draft on the server.
- `stores/statementImport.js`: one request helper with the bearer token, API errors turned into messages and field errors, and the same session guard as the notifications and forecast stores (reset on logout; a response from an earlier session is discarded). `utils/statementImport.js`: mapping guess (English/Italian headers, split debit/credit, ISO / day-first / month-first dates, decimal separator), strict amount parsing for edits, row notes and the "needs a decision" rule that mirrors the server's.
- Amounts are shown with the batch currency; nothing is combined across currencies.

## Evidence

- Vitest: `statement-import.spec.js` (11: mapping guesses, helpers, store upload with FormData and bearer token, mapping → review → done, validation errors, stale response after logout, discarding a draft) and `StatementImportView.spec.js` (7: upload step and disabled submit, error alert, mapping preview, ready vs needs-decision vs errors with select-and-confirm, confirm-all and failed-row report, edit/discard through the store, finished state). With the Phase 24 tests: 25 frontend tests pass; `npm run build` passes; the existing `.mjs` tests still pass. Backend: 735 tests green.
- Browser check (local app, synthetic Italian CSV with split debit/credit, European numbers, an identical repeated line, an unreadable date, and one line already in the ledger), desktop 1440px and mobile 500px (window minimum), dark theme: the mapping was guessed correctly, the review showed 4 ready, 1 needing a decision ("Likely duplicate of an existing entry"), 1 unreadable and the repeated line flagged; "Confirm all ready" posted 4 transactions once, the account balance moved by exactly their sum, the duplicate stayed an unposted draft; no horizontal scroll at mobile width.

## Not verified

- A real bank file (only synthetic ones), XLSX through the UI, a credit card import through the UI (covered by API tests), the light theme, widths below 500px, large files, and two real database connections confirming at once (SQLite ignores row locks).
- Category selection per row in the review (a category column in the file is matched server-side; the API supports changing it but the screen only edits description and amount).

## After the review (see 25-05-SUMMARY.md)

The upload form offers a CSV delimiter; the form resets after each upload; "Finish import (discard the rest)" ends a partially confirmed import; the review handles discarding the last waiting row; theme-aware colours for the alert and finished blocks. Frontend: 30 tests pass.
