# Codebase Concerns

**Analysis Date:** 2024-12-19
**Partially re-verified:** 2026-08-06 (Phase 18 — Security Considerations and Known Bugs sections only; Tech Debt entries were NOT re-verified and may still be stale)

## Tech Debt

**Deprecated Method in CreditCardCycleService:**
- Issue: `calculateRevolvingPaymentBreakdown()` marked deprecated but kept for backward compatibility with tests
- Files: `app/Services/CreditCardCycleService.php` (lines 31-90)
- Impact: Maintenance burden; two parallel implementations of payment calculation logic that must stay in sync
- Fix approach: Migrate all tests to use `RevolvingCreditCalculator` directly, then remove the deprecated method. This is blocking clean refactoring of payment calculation.

**Service Locator Anti-Pattern:**
- Issue: Services using `app()` to resolve dependencies instead of constructor injection
- Files: 
  - `app/Services/CreditCardCycleService.php` (lines 26-27)
  - `app/Services/LoanScheduleService.php` (line 57)
  - `app/Filament/Pages/FinanceReport.php` (lines 62, 85, 97)
  - `app/Observers/TransactionObserver.php` (lines 15, 21, 27)
- Impact: Makes dependencies implicit, harder to test in isolation, difficult to trace dependency chains
- Fix approach: Convert all `app(ServiceClass::class)` to proper constructor injection. This requires updating observers and page classes to use proper DI.

**Magic Interest Rate Calculation:**
- Issue: Hard-coded assumption that 14% annual = 14% monthly in `CreditCardCycleService.php` (line 74)
- Files: `app/Services/CreditCardCycleService.php` (line 74)
- Impact: Comment indicates this is "per user's requirement" but should be configurable and documented in schema
- Fix approach: Extract to `InterestCalculationMethod` enum or UserSetting; validate against actual Amex/card terms during testing

## Known Bugs

**Credit Card Cycle Status Race Condition:**
- Symptoms: Cycle status may oscillate between ISSUED/OVERDUE during concurrent payment updates within same transaction window
- Files: `app/Services/CreditCardCycleService.php` (lines 164-166, 256-267, 313-322)
- Trigger: Multiple payment status changes on same cycle within rapid succession, especially near due date boundary
- Workaround: Payment status updates are wrapped in DB::transaction() so the window is small; due_date comparisons use string comparison only
- **Status 2026-08-06 (Phase 18):** CORRECTED line mapping — `app/Services/CreditCardCycleService.php` is now 380 lines. The status-decision windows are: `syncIssuedCycle()` lines 139-191 (status decision ~159-165), `syncCycleAndCardFromPayment()` lines 236-286 (status decision ~253-266), `handleDeletedPayment()` lines 288-330 (status decision ~305-317), `refreshCycleStatuses()` lines 335-362 (a non-transactional batch loop over the same decision logic — see `deferred-items.md`). CORRECTED (partial) — 18-04 replaced the non-idempotent delta-based balance adjustment in `syncCycleAndCardFromPayment()` with an authoritative `syncCardBalance()` recompute, closing the *balance-drift* half of this race; covered by `tests/Unit/CreditCardCycleServiceTest.php::duplicate_payment_unmark_sync_does_not_double_restore_balance` and `tests/Feature/CreditCardLifecycleIntegrationTest.php::repeated_mark_paid_requests_do_not_drift_card_balance`. `handleDeletedPayment()` still uses the old delta pattern (`reversePrincipalPayment()`) and was left unchanged — OPEN, documented in `deferred-items.md` as a lower-severity carry-forward.

**Transaction Observer Recursion on Transfer Deletion:**
- Symptoms: Soft-delete of transfer OUT can create inconsistent state if inner transaction fails but outer commit succeeds
- Files: `app/Observers/TransactionObserver.php` (lines 49-58)
- Trigger: Delete a transfer transaction (OUT direction)
- Workaround: Recursion is prevented by checking `transfer_direction !== 'in'` but if paired transaction delete fails silently, they become orphaned
- Risk: Report queries will show transfer pairs as separate transactions if pair deletion fails
- **Status 2026-08-06 (Phase 18):** CORRECTED location — the paired-delete block is now at `app/Observers/TransactionObserver.php:47-58` (not 49-58). OPEN — not fixed in Phase 18; no test in this phase reproduced an actual orphaned pair. Cross-referenced in `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md`.

**Missing Validation on CreditCardExpense Move:**
- Symptoms: Can move expense between cards without verifying target card exists or target cycle exists
- Files: `app/Services/CreditCardExpenseService.php` (lines 97-104)
- Trigger: Update expense's `credit_card_id` to non-existent card ID or different cycle
- Workaround: `lockForUpdate()` on current card prevents concurrent updates, but doesn't validate target card
- Risk: Data inconsistency if target card is deleted between validation and update
- **Status 2026-08-06 (Phase 18):** CLOSED — confirmed via a different mechanism than originally described: the actual bug was a fail-open early return (`if (! $currentCard) { return; }`) at `app/Services/CreditCardExpenseService.php:32-34`, which silently accepted the mutation instead of merely "not validating" it. Fixed in 18-05 by throwing `ValidationException::withMessages(['credit_card_id' => ...])`; covered by `tests/Feature/CreditCardExpenseIntegrationTest.php::moving_expense_to_nonexistent_card_is_rejected` and `::creating_expense_on_nonexistent_card_is_rejected`.

**Console command ambient-authentication narrowing (new finding, Phase 18):**
- Symptoms: `credit-cards:generate-cycles` (and, by the same unguarded pattern, `loans:sync-installments` / `subscriptions:sync-renewals`) silently processes only the currently-authenticated user's records instead of every user's records, when run in a process that has an authenticated user in scope (e.g. `Artisan::call()` from an authenticated HTTP request or a queued job carrying auth context) rather than genuine unauthenticated cron.
- Files: `routes/console.php` (`CreditCard::query()`, `Loan::query()`), `app/Services/SubscriptionService.php::syncDueRenewals()` (`Subscription::query()`)
- Trigger: Invoking one of these three commands from a process with `auth()->check() === true`
- **Status 2026-08-06 (Phase 18):** CLOSED — discovered by 18-03's `tests/Feature/ConsoleScopingTest.php::test_generate_cycles_command_is_unaffected_by_ambient_authentication`, then fixed by the orchestrator immediately after Wave 1 merged (commit `3247037`) by adding `->withoutUserScope()` to all three query sites. Verified none of the three call sites are reachable from an authenticated HTTP path. Covered by the same test, now passing.

## Security Considerations

**Broad `withoutGlobalScopes()` Usage:**
- Risk: Bypassing user scope intentionally in multiple places; if auth guard fails, can leak cross-user data
- Files: 
  - `app/Http/Controllers/Api/V1/DashboardController.php` (lines 174-176)
  - `app/GraphQL/Queries/TotalByCategory.php` (line 19)
  - `app/GraphQL/Queries/TransactionCategories.php` (line 13)
  - `app/Filament/Resources/*/` (multiple)
- Current mitigation: Always wrapped by `request()->user()->hasRole('superadmin')` checks or auth middleware
- Recommendations: 
  1. Add explicit comments explaining why scope is bypassed in each location
  2. Create a helper method `allowSuperadminGlobalBypass()` that documents the intent
  3. Add automated security tests that verify no unauth'd access to bypassed scopes
- **Status 2026-08-06 (Phase 18):** CORRECTED — the site inventory is **13** call sites in `app/` (`grep -rn "withoutGlobalScopes" app/` matches 14 lines, but one is a comment at `app/GraphQL/Queries/TotalByCategory.php:17`; CONTEXT.md's count of 14 counted that comment line). Of the 7 Filament resources, all 7 pass `withoutGlobalScopes([SoftDeletingScope::class])` inside `getRecordRouteBindingEloquentQuery()` — this only bypasses the soft-delete scope, not the `user` global scope, so cross-user record binding is still blocked for 6 of them by default; only `AccountsResource` additionally overrides `getEloquentQuery()` to intentionally show all users' accounts to superadmins. Cross-user record binding across all 7 resources is now asserted by `tests/Feature/Filament/AdminPanelScopingTest.php`.
- **Status 2026-08-06 (Phase 18):** CLOSED — `app/GraphQL/Queries/TransactionCategories.php` (line 13) previously had zero user/role gating on this bypass (a confirmed cross-user data disclosure, not merely a documented-mitigation risk as this entry implied). Fixed in 18-01 by adding an owner-scoped gate to both the root query and the eager-loaded `parent` relation; covered by `tests/Feature/Api/GraphQLApiTest.php::test_graphql_transaction_categories_query_returns_only_own_categories`.
- **Status 2026-08-06 (Phase 18):** CLOSED — the generic tenant boundary across all `HasUserScoping` models, plus the `DashboardController.php`/`FinanceReportService.php` `withoutGlobalScopes()` bypass sites, is now covered by `tests/Feature/ScopingSecurityTest.php` (11-model sweep) and targeted regression tests in `tests/Feature/Api/DashboardApiTest.php` and `tests/Feature/Api/FinanceReportApiTest.php`. No leaks were found in any of these sites during Phase 18's proof sweep.

**User Scoping Dependency on Auth Context:**
- Risk: `HasUserScoping` trait in `bootHasUserScoping()` calls `auth()->check()` which can fail in non-HTTP contexts (jobs, commands, API tokens)
- Files: `app/Traits/HasUserScoping.php` (lines 9-14)
- Current mitigation: Works in controllers but untested in background jobs or queued tasks
- Recommendations:
  1. Add explicit user context parameter to service methods that need it
  2. Test scoping in Job/Command contexts
  3. Document that models should not be queried without explicit user context in background processes
- **Status 2026-08-06 (Phase 18):** CLOSED — the console (non-HTTP) no-op is intentional and is now contractually asserted, not merely untested. `tests/Feature/ConsoleScopingTest.php` proves all three shipped scheduled commands (`credit-cards:generate-cycles`, `loans:sync-installments`, `subscriptions:sync-renewals`) process every user's records when run unauthenticated (the real cron condition). A genuine ambient-authentication gap was found and fixed in-phase (see the Known Bugs "Console command ambient-authentication narrowing" entry below); with that fix, `test_generate_cycles_command_is_unaffected_by_ambient_authentication` also passes. See `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md` for findings that remain open.

**Relying on `auth()->user()->hasRole()` for Data Access:**
- Risk: If role assignment system is compromised, entire data isolation breaks
- Files: 20+ locations use `auth()->user()?->hasRole('superadmin')`
- Current mitigation: Spatie permissions package handles role storage
- Recommendations:
  1. Add role-based access audit logging
  2. Cache role checks to reduce DB queries
  3. Document that role changes require immediate cache clear
- **Status 2026-08-06 (Phase 18):** OPEN — not re-verified in Phase 18; the phase's scope was proof of the tenant/console boundaries above, not an audit-logging or role-check-caching implementation. No change from the 2024-12-19 assessment.

## Performance Bottlenecks

**N+1 Query Risk in RevolvingCreditCalculator:**
- Problem: `calculateDailyBalances()` calls `$cycle->expenses()->get()` but doesn't eager load expenses at parent query
- Files: `app/Services/RevolvingCreditCalculator.php` (line 51)
- Cause: Called from `CreditCardCycleService.issueCycle()` which fetches cycle without relation
- Improvement path: Ensure cycle is loaded with `->with('expenses')` before calling calculator
- Risk: Each issued cycle triggers 1 query for expenses; monthly cycles × multiple cards = hundreds of extra queries in batch jobs

**Refresh/Fresh Calls Without Selective Loading:**
- Problem: Full model refresh reloads all relations including large ones
- Files:
  - `app/Services/CreditCardBalanceService.php` (line 126): `$card->refresh()`
  - `app/Services/CreditCardCycleService.php` (lines 275, 289, 336): `->fresh(['cycles.payments', 'payments'])` or `->refresh()`
  - `app/Services/LoanScheduleService.php` (line 54): `->fresh(['payments.loan'])`
- Cause: After mutations, code needs fresh state but reloads everything
- Improvement path: Use `refresh()` for simple scopes; use `fresh(['specific.relations'])` for complex refreshes. Pre-load selectively in transaction.
- Impact: 2-5 extra queries per mutation in critical paths

**Dashboard Controller Data Fetching:**
- Problem: `getNetWorthTrendChartData()` (lines 245-280) builds 12-month trend in a loop, each iteration queries accounts + transactions
- Files: `app/Http/Controllers/Api/V1/DashboardController.php` (lines 241-281)
- Cause: Loop over 12 months × 2-3 queries per month = 24-36 queries for one chart
- Improvement path: Batch fetch all transaction data upfront, group/aggregate in memory
- Impact: 24+ queries for single API call; significantly impacts mobile app responsiveness

**Missing Indexes on Frequently Filtered Columns:**
- Problem: Queries filter by `user_id`, `credit_card_id`, `credit_card_cycle_id`, `status` but indexes not verified
- Files: Migrations use these in WHERE but no explicit indexes visible
- Cause: Database migrations may not include all necessary indexes
- Improvement path: Add explicit `->index()` in migrations for all foreign keys + status/state fields
- Impact: Full table scans on large datasets (millions of transactions); hundreds of ms latency

## Fragile Areas

**Credit Card Balance Synchronization:**
- Files: 
  - `app/Services/CreditCardBalanceService.php`
  - `app/Services/CreditCardExpenseService.php`
  - `app/Services/CreditCardCycleService.php`
- Why fragile: Balance is calculated as `SUM(expenses) - SUM(paid_principal)` across multiple services. If any service fails mid-operation, balance can drift. Multiple methods touch the same data (`addExpense`, `removeExpense`, `reversePrincipalPayment`, `applyPrincipalPayment`, `syncCardBalance`).
- Safe modification: All balance operations MUST be wrapped in `DB::transaction()`. Never update balance outside transactions. Add integrity check `syncCardBalance()` method as recovery point after any failed operation.
- Test coverage: No integration tests verify balance consistency after concurrent mutations

**Interest Calculation Implementation Split:**
- Files:
  - `app/Services/RevolvingCreditCalculator.php` (224 lines)
  - `app/Services/CreditCardCycleService.php` (deprecated method + `issueCycle()`)
  - `app/Enums/InterestCalculationMethod.php`
- Why fragile: Interest calculation logic exists in 3 places; daily balance method, deprecated fixed method, and enum definition. If one is updated without others, calculations diverge.
- Safe modification: All calculation changes must be made in `RevolvingCreditCalculator` only. Deprecated method must NOT be used in new code.
- Test coverage: No parametrized tests verify all calculation methods against known card statements

**Subscription Auto-Posting Logic:**
- Files: `app/Services/SubscriptionService.php` (302 lines)
- Why fragile: Auto-create-transaction logic depends on subscription status, renewal date, posting frequency, and whether transaction already exists. Complex interdependencies with minimal tests.
- Safe modification: Never skip the `hasPostingForRenewal()` check. All mutations must call `syncSubscriptionStatus()` after changes.
- Test coverage: No tests verify posting behavior doesn't create duplicates on edge dates

**Observer Chain Dependency:**
- Files:
  - `app/Observers/TransactionObserver.php`
  - `app/Observers/CreditCardExpenseObserver.php`
  - `app/Observers/CreditCardPaymentObserver.php`
  - `app/Observers/CreditCardCycleObserver.php`
- Why fragile: Observers trigger service methods which may trigger other observers. If one fails, entire chain breaks. Static `$originalPointers` array in `CreditCardExpenseObserver` can leak state between requests in high concurrency.
- Safe modification: Keep observer logic minimal—only call ONE service method. Don't call services from inside services if those services also have observers. Add clear logging of observer entry/exit.
- Test coverage: No transaction-level tests verify observer chains complete successfully
- **Status 2026-08-06 (Phase 18):** CORRECTED — the static-array pattern is still present at `app/Observers/CreditCardExpenseObserver.php:21` (`$originalPointers`, previously cited around line 15), and a previously undocumented second instance of the same pattern exists at `app/Observers/CreditCardPaymentObserver.php:25` (`$previousStatuses`; the plan's pre-verification note referenced it as `CreditCardPaymentObserver.php:16` before 18-05 added an explanatory docblock above the property, shifting its line number). "Leak state between requests" is CORRECTED — no Laravel Octane/Swoole is in use (verified via `composer.json`), so the risk is intra-request only (same static array reused if the same model ID is touched twice within one request), not cross-request. Both properties now carry in-code docblocks pointing at `tests/Unit/Observers/ObserverStaticStateTest.php`, which proves clearance after successful updates and measures the residue left by a failed mid-update write (`{"<id>":"pending"}`) — that residue does not corrupt the subsequent legitimate update's cycle status or card balance (see `deferred-items.md` for the full D-02 disposition; OPEN/documented-not-fixed, Branch B).

## Scaling Limits

**Database Locks in Credit Card Operations:**
- Current capacity: ~1000 concurrent credit card updates per minute (rough estimate)
- Limit: Explicit `lockForUpdate()` on CreditCard in `CreditCardExpenseService` will block concurrent UPDATEs
- Scaling path: 
  1. Use optimistic locking (version field) instead of row locks
  2. Batch-process expenses in off-peak hours
  3. Denormalize balance into separate summary table to avoid frequent updates

**Dashboard Chart Query Volume:**
- Current capacity: ~100 concurrent requests on dashboard charts
- Limit: 24-36 queries per chart; at 100 RPS × 30 queries = 3000 DB queries/sec
- Scaling path:
  1. Materialize monthly summaries into `finance_snapshots` table
  2. Cache dashboard data for 5 minutes
  3. Use read-only database replicas for analytics queries

**Transaction Query N+1 in Reports:**
- Current capacity: Reports work for ~10k transactions
- Limit: Above 100k transactions, lazy-loading relationships in `FinanceReportService` causes hundreds of queries
- Scaling path:
  1. Replace `DB::table()` raw queries with aggressive eager loading
  2. Use database views for complex aggregations
  3. Pre-compute monthly/yearly summaries

## Dependencies at Risk

**Laravel 11.x Compatibility:**
- Risk: Project uses Laravel 11 but many packages may not be tested against it yet (e.g., Filament, Lighthouse)
- Files: `composer.json`, `composer.lock`
- Impact: Security updates may not be available; future versions may break compatibility
- Migration plan: Monitor Filament and Lighthouse releases; test major versions before production upgrades

**Spatie Permissions Package Dependency:**
- Risk: All authorization via `auth()->user()?->hasRole()` depends on Spatie's permission system; if package is abandoned, fixing security issues becomes difficult
- Files: `app/Traits/HasRoles` integration throughout
- Impact: Critical path dependency; no fallback if package breaks
- Migration plan: Document role assignment logic clearly; maintain local copy of role checking logic as backup

## Missing Critical Features

**No Data Backup/Recovery Mechanism:**
- Problem: Users can delete transactions, cycles, and payments but no recovery option exists
- Blocks: Cannot restore user data after accidental deletion
- Implementation gap: No soft-delete recovery UI; no export/import for user data

**No API Rate Limiting:**
- Problem: GraphQL and REST endpoints have no rate limiting
- Blocks: Vulnerable to abuse; no protection for multi-user deployments
- Implementation gap: Missing Laravel rate limiter configuration

**No Webhook Support for External Integrations:**
- Problem: System is completely isolated; cannot push data to external services (Slack, Zapier, webhooks)
- Blocks: Advanced automation workflows; integration with other fintech platforms
- Implementation gap: No webhook table, event system, or delivery mechanism

**No Audit Trail for Financial Transactions:**
- Problem: `AuditLog` table exists but is not populated for critical operations
- Blocks: Cannot trace who changed what financial data and when
- Implementation gap: Missing observer hooks or middleware to log mutations

## Test Coverage Gaps

**Unit Tests for Interest Calculation:**
- What's not tested: `RevolvingCreditCalculator::calculateInterestFromDailyBalances()` with edge cases (leap years, multi-cycle interest, rates > 50%)
- Files: `app/Services/RevolvingCreditCalculator.php`
- Risk: Interest calculations can silently diverge from expected amounts without test catches it
- Priority: HIGH — financial calculations must be comprehensive

**Integration Tests for Transaction Observer Chain:**
- What's not tested: Full flow of creating expense → updating cycle → updating card balance → updating subscription posting all in one transaction
- Files: `app/Observers/`
- Risk: Observer chain fails silently in production while working in isolation tests
- Priority: HIGH — observer chains are fragile

**Concurrent Update Tests:**
- What's not tested: Two simultaneous updates to same credit card expense or cycle status
- Files: All service mutation methods
- Risk: Race conditions that only manifest under load
- Priority: MEDIUM — scaling risks unknown

**GraphQL Schema Validation Tests:**
- What's not tested: Lighthouse schema compilation; all GraphQL queries and mutations against generated schema
- Files: `graphql/schema.graphql`
- Risk: Invalid schema or breaking changes only discovered at runtime
- Priority: MEDIUM — API contracts must be verified

**API Permission Tests:**
- What's not tested: Each API endpoint with unauthorized user; cross-user data access attempts
- Files: `app/Http/Controllers/Api/V1/`
- Risk: Security bypass where user A can access user B's data
- Priority: CRITICAL — security vulnerability

## Cleanup Opportunities

**Remove Deprecated Method:**
- Location: `app/Services/CreditCardCycleService.php::calculateRevolvingPaymentBreakdown()` (lines 31-90)
- Effort: 2-3 hours (update tests + remove)
- Benefit: Reduces maintenance burden; clarifies single source of truth for payment calculations
- Blocker: Tests must be migrated first

**Extract Service Locator to Helper:**
- Location: Multiple files using `app(ServiceClass::class)`
- Effort: 4 hours
- Benefit: Centralizes dependency resolution; makes DI chain visible
- Blocker: None

**Consolidate Balance Update Logic:**
- Location: `CreditCardBalanceService` has 4 similar methods (`addExpense`, `removeExpense`, `applyPrincipalPayment`, `reversePrincipalPayment`)
- Effort: 3-4 hours
- Benefit: Single, testable balance mutation function
- Blocker: None

**Extract Observer Logic to Actions:**
- Location: `app/Observers/` (6 files)
- Effort: 5-6 hours
- Benefit: Observers become thin dispatchers; business logic moves to explicit Action classes
- Blocker: None but high risk of introducing bugs

### Phase 18 verification note

Line numbers in the Security Considerations and Known Bugs sections above were re-verified against the working tree on 2026-08-06. Line numbers elsewhere in this document are from 2024-12-19 and must be re-grepped before use. Unfixed Phase 18 findings and their severity rationale live in `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md`.

---

*Concerns audit: 2024-12-19*
