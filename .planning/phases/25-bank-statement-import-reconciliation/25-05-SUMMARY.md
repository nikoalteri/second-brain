# 25-05 Summary — import/ledger concurrency and SPA completion

## The protocol as implemented

`App\Support\LedgerLock` is the one place that takes the target row lock: `accounts(ids)` is one `SELECT … ORDER BY id FOR UPDATE` (ascending, soft-deleted included), `card(id)` locks the card. A request outside a database transaction is logged, because a row lock protects nothing there. Order everywhere: domain rows (import batch, subscription, payment/loan) → accounts → cards.

- `TransactionObserver` takes the account lock on `creating`, on `updating` when account/amount/date change (old, new and the transfer pair's account in one statement) and on `restoring`. This covers the API, GraphQL (transactional), Filament (`databaseTransactions()`), the loan, card-payment and subscription postings, and the import.
- `AccountTransferService` locks both accounts ascending before either leg. **Fixes a pre-existing deadlock** between two opposite transfers (A→B and B→A).
- Card expenses already locked the card in `CreditCardExpenseService`; the import now takes it first. The order is documented next to the existing card note in `CreditCardCycleService`.
- `ImportRowPromotionService::promote()` order: batch row → target (account or card) → staging row, with no plain read before the target lock (InnoDB takes its read snapshot at the first plain SELECT). Under the lock it runs `ImportDuplicateDetector::matchOne()`: the same candidate rules as the batch classification, excluding movements this batch already posted and those another draft row is matched to (one-to-one). A row reviewed as clean that now matches is held back with "review it again"; a row the owner saw flagged proceeds (conscious duplicate). `confirm()` keeps its pre-pass for the `all_ready` selection.

## Evidence

- **Two real MySQL connections** (`phpunit.mysql.xml`, `tests/Mysql`, README with the recipe; run against a scratch MySQL 26.7, REPEATABLE-READ): 8 tests, 71 assertions. A worker freezes holding its lock, the test starts the other writer and asserts it is still waiting, releases, and checks the committed outcome: manual entry mid-insert vs import (import waits, then is held back); import between check and insert vs manual entry (manual waits, both post); two imports of one charge (second held back); the same staging row confirmed twice (balance moves once); an automatic loan posting vs the import in both orders; opposite transfers (second queues, no deadlock); two identical legitimate charges and a conscious duplicate still post.
- **The tests discriminate:** with the locks disabled 6 of the 8 fail (writers do not wait, a duplicate is posted, the transfers reach the deadlock position); with only the under-lock re-check disabled 3 fail.
- SQLite: `LedgerLockOrderTest` (8) pins the order of lock, ledger read and insert for manual create, transfer, update, restore and the import (account and card), plus the single-row re-check and one-to-one matching. Full default suite: 766 tests green.

## SPA completion (third review request) and what it found

Reviewed `StatementImportView`/store against the final API and checked desktop (1440px) and mobile (500px, the window minimum), dark theme, synthetic data (an ambiguous CSV, a card whose cycle was issued, a partial confirmation). Found and fixed:

- the delimiter choice (and the other upload fields) carried over to the next import → the form is reset after a successful upload and on start-over;
- column labels used the first data line when the file had no header → "Column A";
- a mapping that made every line unreadable was remembered as the target's default (and pre-filled the next import) → a mapping is saved only if it read at least one row;
- a partially confirmed import could not be finished and its leftover drafts lingered → `POST /import-batches/{id}/close` (discards the rest, keeps the history), automatic completion when the last waiting row is discarded, a "Finish import" button, and `imports:purge-abandoned` now closes stale partial imports;
- low contrast of the error and "finished" blocks in the dark theme (classes without a dark override) and "1 rows were added".

Seen working: the ambiguous-delimiter error and the delimiter select, mapping guess for header/headerless files, rows needing a decision with their reasons, the "some rows were not posted" report with the billing-cycle reason and a successful retry after the cycle was reopened, the cancel refusal for a partially posted import, the finished state.

## Not proven / remaining

- The MySQL tests run against one server version and the default isolation level; they are opt-in (not in CI).
- `AccountBalanceService` increments (and the transfer pair sync of an edit) were not changed beyond the up-front locks; a path that holds a card lock and then creates an account movement would invert account → card. None exists today; the log line for an unwrapped write and the order note are the guard.
- Writes done outside Eloquent bypass the hook; the unique promotion indexes remain the last defence for a retry.
- The SPA keeps the current import in memory only: leaving the page loses the way back to an unfinished import (the purge closes it eventually); there is no list of past imports.
- Real bank files, XLSX through the UI, the light theme and widths below 500px remain unchecked.

## Review fix: consent is bound to the request (PR #76, 14:04)

`promote()` used to skip the live re-check whenever the stored row was already flagged, so a flag written by *another* request (a concurrent reclassification) could be read as the owner's consent to post a duplicate in an `all_ready` run. Now `promote($batch, $rowId, $allowFlagged, $reviewedSignature)` judges the decision under the lock against what this request was allowed to do:

- **`all_ready`** (`allowFlagged: false`): a row that is, or has become, a duplicate or carries a review flag is always held back, whoever flagged it.
- **Explicit rows** (`allowFlagged: true`): consent is bound to the classification signature (class, review flags, matched ledger entry) stored at the START of the request, before the pre-pass refreshes it. A row whose signature differs under the lock is held back; a row reviewed as clean is re-checked against the live ledger; a row reviewed as flagged and unchanged proceeds (conscious duplicate). Direct `promote()` calls keep the previous behaviour.

Evidence: 3 SQLite tests (a hook on `ImportBatch::retrieved` reproduces "after selection, before the lock") and 2 MySQL tests through the public `confirm(..., null)` and `confirm(..., [ids])` with a worker frozen after row selection while the movement is committed and the draft reclassified. With the old inference all of them fail; the full default suite is 769 tests and the MySQL suite 10 tests, all green.
