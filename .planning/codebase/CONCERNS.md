# Codebase Concerns

**Analysis Date:** 2026-03-23

## Complexity & Maintainability

**Large Service Classes:**
- Issue: Some service classes approach 300+ lines (CreditCardCycleService 334 lines)
- Files: `app/Services/CreditCardCycleService.php`, `app/Services/LoanScheduleService.php` (160 lines)
- Impact: Difficult to test in isolation; high cognitive load when maintaining
- Fix approach: Extract distinct responsibilities into separate services; use private helper methods or break into sub-services

**Large Filament Forms:**
- Issue: Complex form schemas with 100+ lines (CreditCardForm 126 lines, LoanForm 208 lines)
- Files: `app/Filament/Resources/*/Schemas/*Form.php`
- Impact: Hard to extend; form validation logic intertwined with field definitions
- Fix approach: Extract form sections into separate methods; use custom form components for complex inputs

**Large Filament Pages:**
- Issue: FinanceReport custom page is 263 lines (mixing data aggregation + UI rendering)
- Files: `app/Filament/Pages/FinanceReport.php`
- Impact: Difficult to test report logic; UI and business logic coupled
- Fix approach: Extract report generation to dedicated Service; page acts only as view layer

## Test Coverage Gaps

**API/GraphQL Not Tested:**
- What's not tested: GraphQL schema, query execution, authorization via GraphQL
- Files: `graphql/schema.graphql` - no corresponding test files
- Risk: Breaking changes to API not caught until production; resolvers untested
- Priority: High - API is primary integration point for frontend

**Authorization Policies Lightly Tested:**
- What's not tested: Some policies (CreditCardPolicy, TransactionPolicy, etc.) not directly tested
- Files: `app/Policies/` - only loan and transaction authorization tested
- Risk: Unauthorized access to sensitive data not caught until production
- Priority: High - authorization is critical for financial data security

**Observer Logic Not Tested:**
- What's not tested: Observer event dispatch and side effects
- Files: `app/Observers/` - no dedicated observer tests
- Risk: Changes to observers cause silent failures in dependent systems
- Priority: Medium - only discovered through integration tests

**Factory Defaults Not Validated:**
- What's not tested: Whether factories generate realistic/valid data
- Files: `database/factories/` - factories not tested for constraints
- Risk: Factories produce invalid test data; tests pass but production fails
- Priority: Medium - can be caught in feature tests but not at factory level

## Technical Debt

**Workday Calculation Hardcoded:**
- Issue: Italian holiday dates hardcoded in `HasWorkdayCalculation` trait
- Files: `app/Support/Concerns/HasWorkdayCalculation.php` (fixed holiday array)
- Impact: Not extensible; assumes all users are in Italy; Easter calculation annually computed
- Fix approach: Move to configurable holiday calendar (database or config); support multiple locales

**User Scoping Applied Selectively:**
- Issue: `HasUserScoping` trait only used where explicitly applied; no global scope enforcement
- Files: `app/Traits/HasUserScoping.php` (trait), but not applied to all models
- Impact: Risk of accidentally exposing one user's data to another; requires manual diligence
- Fix approach: Apply via model boot() globally; or enforce at policy level with middleware

**No Audit Trail:**
- Issue: No record of who created/modified financial records or when
- Files: All models missing `created_by`, `updated_by` fields
- Impact: Cannot trace financial changes; compliance/accountability gaps
- Fix approach: Add user audit columns; create audit log table for sensitive operations

**Enum Status Values Not Fully Consistent:**
- Issue: Status fields use both Enum backing (LoanStatus) and string columns
- Files: Mixed usage in `app/Enums/` and models; some status fields are plain strings
- Impact: Type safety not enforced everywhere; risk of invalid status values
- Fix approach: Migrate all status fields to Enum backing; create migration to cast existing data

## Performance Concerns

**Credit Card Cycle Calculations:**
- Problem: CreditCardCycleService recalculates everything on each call; no caching
- Files: `app/Services/CreditCardCycleService.php` - loops through transactions, cycles, payments
- Cause: Denormalized calculations from raw transaction data; happens per request
- Improvement path: Denormalize summary data to table columns; update only on transaction/payment change; implement query-level caching

**Account Balance Recalculation:**
- Problem: Account balance computed from transaction sum each time (no denormalization)
- Files: `app/Models/Account.php` (balance derived via relationship)
- Cause: No balance column on accounts table; relies on SUM(transactions.amount)
- Improvement path: Add balance column; update via trigger or observer on transaction/payment changes

**Paginated Queries Unbounded:**
- Problem: GraphQL @paginate directive default 10, but no maximum enforced
- Files: `graphql/schema.graphql` - queries use @paginate(defaultCount: 10) without max
- Cause: Client can request arbitrarily large result sets
- Improvement path: Add maxCount directive parameter; validate pageSize in Lighthouse middleware

## Security Considerations

**Laravel Configuration Exposure:**
- Risk: Debug mode may expose sensitive routes/config in error pages
- Files: `config/app.php` - APP_DEBUG setting controlled by .env
- Current mitigation: .env file not committed; APP_DEBUG=false in production
- Recommendations: Audit environment configuration; use config:cache in production; disable debug routes

**GraphQL Introspection Enabled:**
- Risk: Introspection query reveals entire API schema to unauthenticated clients
- Files: `graphql/schema.graphql` - Lighthouse allows introspection by default
- Current mitigation: None observed - introspection publicly accessible
- Recommendations: Disable introspection in production (Lighthouse config); restrict to authenticated users or development

**No Rate Limiting on GraphQL:**
- Risk: GraphQL endpoint susceptible to DoS attacks; can run expensive queries
- Files: `routes/api.php` - API middleware includes `ApiRateLimitMiddleware` but scope unclear
- Current mitigation: Middleware present but implementation unverified
- Recommendations: Test rate limiting; implement query complexity analysis (Lighthouse field weights)

**Password Reset Token Expiration:**
- Risk: Laravel default password reset tokens expire after 60 minutes; no audit log
- Files: `config/auth.php` - password reset token lifetime
- Current mitigation: Laravel default security; Sanctum tokens checked
- Recommendations: Document token expiration; consider shorter windows for sensitive operations

**RBAC Not Enforced Globally:**
- Risk: Service classes don't verify authorization; only Filament resources enforce policies
- Files: `app/Services/` - no authorization checks; assumes caller is authorized
- Current mitigation: Policies on Filament resources; policies available for manual checking
- Recommendations: Add policy checks to service methods; middleware for GraphQL authorization

## Missing Critical Features

**Audit Logging:**
- Problem: No history of who accessed/modified financial data
- Blocks: Compliance auditing, fraud investigation, accountability

**Data Validation at Multiple Levels:**
- Problem: Form validation in Filament; GraphQL validation via @rules; service layer assumes valid input
- Blocks: Validation rules not DRY; inconsistency between API and admin panel validations

**Duplicate Transaction Prevention:**
- Problem: No idempotency key or duplicate detection on payment postings
- Blocks: Retry logic for failed jobs risks double-posting payments

**Soft Deletes Implementation:**
- Problem: Only Accounts table has soft deletes; other financial records hard-deleted
- Blocks: Cannot recover deleted loans/transactions; audit trail lost

**Real-Time Notifications:**
- Problem: No notification system for payment reminders, cycle closures, etc.
- Blocks: Users don't get timely alerts; relies on manual dashboard checks

## Scaling Limits

**Database Schema Not Indexed:**
- Current capacity: Works fine with hundreds of accounts/loans
- Limit: Query performance degrades with millions of transactions
- Scaling path: Add indexes on foreign keys, status fields, date ranges; partition transaction table by date

**In-Memory Job Processing:**
- Current capacity: One queue worker processes jobs synchronously
- Limit: Slow jobs block subsequent queue processing
- Scaling path: Implement Redis queue driver; scale to multiple workers; use Horizon for monitoring

**No Caching Layer:**
- Current capacity: Queries work for small datasets
- Limit: Repeated expensive calculations (balance, KPIs) slow down with data growth
- Scaling path: Add Redis caching; invalidate on transaction/payment changes; query-level caching in Filament

## Dependencies at Risk

**Laravel 12.x Adoption:**
- Risk: Framework recently released; edge cases may surface
- Impact: Bug fixes and security patches may change behavior
- Migration plan: Stay on current minor version; monitor changelog; test thoroughly on upgrades

**Filament 4.x Stability:**
- Risk: Filament is actively developed; API changes possible in 4.x
- Impact: Admin UI may require refactoring between versions
- Migration plan: Pin minor version; review CHANGELOG before upgrading; maintain custom components

**Lighthouse GraphQL:**
- Risk: GraphQL patterns may evolve; schema directives may change
- Impact: Schema may require rewriting if directives deprecated
- Migration plan: Use stable directives; monitor release notes; test schema against new versions

## Fragile Areas

**Credit Card Cycle Logic:**
- Files: `app/Services/CreditCardCycleService.php`, `app/Models/CreditCardCycle.php`
- Why fragile: Complex state machine (opening, active, closing, closed); multiple dependencies; 334 lines
- Safe modification: Add comprehensive integration tests before touching; test all state transitions
- Test coverage: Integration tests exist but could be more thorough

**Loan Schedule Generation:**
- Files: `app/Services/LoanScheduleService.php`, `app/Observers/LoanObserver.php`
- Why fragile: Date calculations with edge cases (month ends, weekends, holidays); observer triggers async job
- Safe modification: Add tests for edge dates (Feb 29, month-end adjustments); mock Carbon time
- Test coverage: Good unit tests; could add more edge case scenarios

**User Authorization Enforcement:**
- Files: `app/Traits/HasUserScoping.php`, `app/Policies/`, routes
- Why fragile: Scoping not consistently applied; easy to add query without user filter
- Safe modification: Make user scoping automatic via global scope; audit all queries for scoping
- Test coverage: Authorization tests exist but not comprehensive for all resources

---

*Concerns audit: 2026-03-23*
