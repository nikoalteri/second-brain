# 25-02 Summary — column mapping, normalisation and duplicate detection

## What exists

- `PUT /api/v1/import-batches/{id}/mapping` (`MapImportBatchRequest`): explicit, validated mapping — 0-based column indexes, `header_row`, a whitelisted `date_format`, single signed amount or split debit/credit columns, explicit decimal and thousands separators, `invert_sign`, optional category and currency columns. A completed batch answers 409; a stranger's batch 404.
- `ImportAmountParser` (integer minor units, never the process locale): `-12,50`, `(12,50)`, `12,50-`, `+1.234,56`, currency symbol/code; more than two decimals, mixed separators and exponents are rejected.
- `ImportRowNormalizer`: sign convention **negative = money out (spending)**, positive = money in. Row errors (bad date, bad amount, empty, both debit and credit filled) are stored per row and never block the rest. The categories column is matched by name to the owner's own `generic`-scope categories only.
- `ImportMappingService::apply()`: one transaction, batch row lock; header/preamble lines become `skipped`; re-applying touches only `draft`/`skipped` rows, so rows the owner discarded or confirmed are left as they are. The mapping is saved per target (`import_mappings`) and returned as `saved_mapping` on the next import of that account/card.
- `ImportDuplicateDetector`: HMAC fingerprint (owner + target + date + amount + description + occurrence); ledger match `exact` (same day, same amount, similar description) / `probable` (same amount within ±3 days) / `none`, one-to-one (two identical imported charges cannot both claim one ledger row, and both stay importable); look-alike flags: `within_file_duplicate`, `already_imported` (same fingerprint in another batch still draft or confirmed), `possible_transfer` (opposite amount on another own account), `possible_card_payment` (card cycle payment of the same total ±5 days), `matches_generated_renewal`, `currency_mismatch`, `card_credit`. Everything is a proposal; no row is auto-discarded.
- Normalised cells are encrypted at rest; the fingerprint is a keyed hash. The batch response now carries `mapping`, `mapping_version`, per-row `normalized`/`error`/`duplicate_class`/`flags` in the preview, plus `summary` and `saved_mapping`.

## Evidence

`ImportAmountParserTest` (18 cases), `ImportMappingApiTest` (22): normalisation and summary, European/split/inverted formats, error rows, category scoping, remembered mapping, re-mapping rules, 409/404, invalid mappings, encryption at rest, no ledger writes, exact/probable/none, window and amount boundaries, one-to-one matching, same file twice, discarded/deleted earlier batches, transfer/card-payment/renewal/currency flags, card batches, other users' ledger rows. Full suite: 719 tests green (inline synthetic `APP_KEY`).

## Notes

- "Same file twice does not stage twice" is implemented as: the second batch is staged but every row is flagged `already_imported` (with the earlier batch id); 25-03's "confirm all" must skip flagged rows by default.
- Not covered: very large files' normalisation time, descriptions in other scripts for the similarity metric, `date_format` `m/d/y` ambiguity (the user picks the format).

## Review fixes (PR #76 review of 2026-10-17)

- **XLSX numbers are canonical.** The reader reports which cells were real numbers (`numeric_columns` on each row); the normaliser parses those with `.` and no thousands separator whatever the mapping says, so a numeric `-12.5` under a European mapping stays -12.50 instead of becoming -125.00. Text cells still follow the configured separators.
- **Editing a draft reclassifies it.** Changing date, amount, description or restoring a row re-runs the ledger matching, keeping the row's original fingerprint (it identifies the statement line, not the edit). Ledger movements created by this same batch are excluded from later matching, so two identical legitimate charges stay importable one after the other.
