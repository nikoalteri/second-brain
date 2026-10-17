# 25-03 Summary — review and idempotent confirmation

## What exists

- `GET /api/v1/import-batches/{id}/rows` (cursor-paginated; filters `status`, `duplicate_class`, `flag`, `errors`), `PATCH .../rows/{row}` (description, amount with at most two decimals, ISO date, own `generic` category, and `status` draft ⇄ discarded). Editing rewrites the encrypted normalised data and marks it `edited`; rows with no valid data can only be discarded; discarded rows must be restored before editing; a completed batch is closed.
- `POST /api/v1/import-batches/{id}/confirm` with `row_ids` (explicit choice, flagged rows allowed) or `all_ready` (only rows with duplicate class `none` and none of `already_imported`, `currency_mismatch`, `card_credit`, `possible_transfer`, `possible_card_payment`, `matches_generated_renewal`). Response: per-row outcomes (`confirmed` / `skipped` / `failed` with a safe reason), counts, batch status and summary. The batch must be `mapped`; it becomes `completed` once no draft row is left (error rows must be discarded).
- `ImportRowPromotionService`: one DB transaction per row with a row lock; only `draft` rows with valid data are promoted; the record goes through the same models and observers as the manual API. Account batch: `Transaction` (type Income/Expense from the sign, owner and account re-checked, inactive/deleted target refused). Card batch: charge → `CreditCardExpense` (observers assign the cycle and update the card balance); card credits/payments are never posted automatically. The created id is stored on the row; `transaction_id` and `credit_card_expense_id` are unique, so a record can belong to at most one row. A failure rolls the row back, stores `failure_reason` and leaves it a draft for retry; one failure never blocks the others.

## Evidence

`ImportConfirmationApiTest` (16): list/filter, edit/discard/restore and their validation and ownership, balance moves once, repeated confirmation and repeated promotion are no-ops, unique-index guard, `all_ready` skips flagged rows while an explicit choice can include them, same file imported twice is not posted by `all_ready`, requires a mapped batch, error/discarded/foreign rows skipped, inactive target, other users get 404, card charge vs credit, issued-cycle failure stays retryable, isolation between rows, completion and closing rules. Full backend suite green.

## Limits

- SQLite ignores `FOR UPDATE`, so concurrent confirmation is protected structurally (status check + unique indexes) but not exercised with two real connections; a MySQL two-connection test is a follow-up like Phase 22's.
- A row edited by the owner keeps its original fingerprint (it identifies the statement line, not the edit).
- Card refunds and payments must be discarded or entered manually; modelling them is out of scope here.
