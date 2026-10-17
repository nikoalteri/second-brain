# 25-01 Summary — staging model and safe file intake

## What exists

- `import_batches` / `import_rows` migrations and `ImportBatch` / `ImportRow` models. `ImportBatch` deliberately has no user-scoping trait: every lookup states the owner, so a superadmin cannot read someone else's statement. Row cells use the `encrypted:array` cast (bulk inserts encrypt the same way).
- `ImportFileReader` (CSV and XLSX only): content sniffing, size/row/column/cell limits, UTF-8 / BOM / Windows-1252 handling, delimiter detection (`, ; tab |`), quoted multiline fields; XLSX zip-entry and uncompressed-size guard, first sheet only, read filter capped at the row/column limits, dates converted to ISO strings, formulas kept as text and never evaluated.
- `ImportBatchService::stage()`: target must be the uploader's own active account or credit card (card currency comes from its settlement account); target with no valid ISO currency is refused; everything is staged in one DB transaction as `draft` rows.
- API (existing throttles): `POST /api/v1/import-batches`, `GET /api/v1/import-batches`, `GET /api/v1/import-batches/{id}` (preview of the first 20 rows), `DELETE /api/v1/import-batches/{id}` (409 when completed). `statement_ending_balance` and the statement period are stored (D-06); the response never includes the owner id.
- `php artisan imports:purge-abandoned` (scheduled 02:10 daily): removes `uploaded`/`mapped` batches older than 30 days with per-item error isolation; completed batches are never touched.
- Config in `config/import.php` (5 MB, 5000 rows, 40 columns, 500-char cells, 50 MB / 200 entries for XLSX).

## Deviation from the plan text

The raw file is not stored encrypted with a TTL purge; it is parsed immediately and never persisted. Staged cells are encrypted instead, and abandoned batches are purged. `25-RESEARCH.md` and `25-01-PLAN.md` were updated.

## Evidence

`tests/Unit/Services/Import/ImportFileReaderTest.php` (15) and `tests/Feature/Api/ImportBatchApiTest.php` (12): both targets, one-target rule, foreign/inactive/currency-less targets, superadmin cannot import into or read another user's batch, bad/oversized/binary/renamed files stage nothing, encrypted at rest, no `transactions` / `credit_card_expenses` rows and no balance change, preview limit, delete rules, purge rules. Full suite: 683 tests green (synthetic inline `APP_KEY`).

## Open for later plans

- Mapping, normalisation, fingerprints and duplicate classification are 25-02; row edit/confirm is 25-03; the SPA is 25-04.
- The header line is staged like any other row; 25-02's mapping will declare the header row.
- Only `uploaded`/`mapped` statuses are produced so far; `completed` is set by 25-03.

## Review fixes (PR #76 review of 2026-10-17)

- **XLSX content past a limit is rejected, never truncated.** The reader loads a window (limit plus a blank-row/column slack of 50/10); if cells were skipped it loads the outside region separately and refuses the workbook when any of it has content (an empty sentinel row no longer hides data). `XlsxBoundsFilter` is the shared filter. Blank rows inside the data are not a false positive.
- **CSV delimiter from the record structure.** A candidate (`, ; tab |`) is accepted only when at least 90% of the first records split into the same number of columns (quotes respected); two fitting candidates are ambiguous and raise an error asking for an explicit `delimiter` (`comma|semicolon|tab|pipe`, optional on `POST /import-batches`, and a select in the SPA). Single-column files still read.
- **Partial batches are kept.** `DELETE` answers 409 and `imports:purge-abandoned` skips any batch that has a confirmed row; both lock the batch and re-check, and promotion locks batch-then-row, so a confirmation racing a delete or purge cannot lose its provenance.
