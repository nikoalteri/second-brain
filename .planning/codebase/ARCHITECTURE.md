# Architecture

**Analysis Date:** 2026-03-23

## Pattern Overview

**Overall:** Layered MVC with Filament Admin + GraphQL API

**Key Characteristics:**
- Clean separation between Eloquent models (data layer), services (business logic), and Filament resources (presentation)
- GraphQL as primary API layer via Lighthouse (schema-driven approach)
- Admin dashboard UI via Filament (declarative resource management)
- Background job processing for compute-heavy operations (loan/credit card calculations)
- Role-based access control (RBAC) using Spatie Permission

## Layers

**Presentation Layer (Filament):**
- Purpose: Admin dashboard UI for managing financial data
- Location: `app/Filament/Resources/`, `app/Filament/Pages/`, `app/Filament/Widgets/`
- Contains: Resource pages (List/Create/Edit), custom pages (FinanceReport), widgets (KPI displays)
- Depends on: Models, Policies, Services
- Used by: Authenticated admin users via `/admin` dashboard

**API Layer (GraphQL):**
- Purpose: Exposes data and mutations for frontend/external clients via GraphQL
- Location: `graphql/schema.graphql`
- Contains: Type definitions, queries, mutations
- Depends on: Models, Services, Lighthouse directives
- Used by: Frontend Vue application, external GraphQL clients

**Business Logic Layer (Services):**
- Purpose: Contains domain-specific calculations and state management
- Location: `app/Services/` - 9 services handling different domains
  - `LoanScheduleService` - Generates payment schedules with workday adjustments
  - `LoanPaymentPostingService` - Records payment transactions
  - `CreditCardCycleService` - Manages credit card billing cycles
  - `CreditCardPaymentPostingService` - Processes credit card payments
  - `CreditCardExpenseService` - Records credit card expenses
  - `CreditCardKpiService` - Calculates KPIs (interest, penalties)
  - `AccountBalanceService` - Maintains account balances
  - `FinanceReportService` - Aggregates financial reports
  - `UserService` - User management operations
- Depends on: Models, Repositories, Traits, Database transactions
- Used by: Controllers, GraphQL resolvers, Jobs, Filament resources

**Data Access Layer (Models + Repositories):**
- Purpose: Object-relational mapping and query abstraction
- Location: `app/Models/` (11 models), `app/Repositories/` (repository pattern)
- Contains: Eloquent models with relations, repository classes for complex queries
- Models: User, Account, Loan, LoanPayment, CreditCard, CreditCardCycle, CreditCardPayment, CreditCardExpense, Transaction, TransactionType, TransactionCategory
- Depends on: Database schema, migrations
- Used by: Services, Controllers, GraphQL queries

**Infrastructure Layer:**
- Purpose: Cross-cutting concerns and utilities
- Location: `app/Support/Concerns/`, `app/Traits/`, `app/Providers/`
- Contains: Reusable traits (HasWorkdayCalculation, HasUserScoping), service providers
- Used by: Models and Services

**Policy & Authorization Layer:**
- Purpose: Fine-grained access control
- Location: `app/Policies/` (13 policies - one per model)
- Contains: Authorization rules (who can view/create/update/delete what)
- Used by: Filament resources (gates), GraphQL queries (middleware)

## Data Flow

**Loan Payment Schedule Generation Flow:**

1. Admin creates/edits Loan in Filament (CreateLoan/EditLoan page)
2. Observer (`LoanObserver`) detects model changes
3. Dispatches `GenerateLoanPaymentsJob` to job queue
4. Job instantiates `LoanScheduleService` and calls `generate()`
5. Service uses `HasWorkdayCalculation` trait to adjust due dates to workdays (Italian holidays aware)
6. Payments created as `LoanPayment` models, linked to `Loan`
7. `syncLoan()` updates `Loan` status and totals based on payment states

**Financial Report Generation Flow:**

1. User navigates to FinanceReport page (`app/Filament/Pages/FinanceReport.php`)
2. Page instantiates `FinanceReportService` with date filters
3. Service aggregates: Account balances, Loan totals, Credit card expenses
4. Returns data to Livewire component for reactive display

**GraphQL Query Flow:**

1. Frontend Vue app sends GraphQL query to `/graphql` endpoint
2. Lighthouse processes query against `graphql/schema.graphql`
3. Resolves query to Model/Service methods
4. Applies authorization policies
5. Returns JSON response to frontend

**State Management:**
- Database as source of truth (no in-memory state)
- Account balances recalculated from transactions (denormalized for performance)
- Loan/Credit Card status computed from payment history
- Observers trigger background jobs for expensive calculations

## Key Abstractions

**Loan Domain:**
- Purpose: Models and services for loan management (amortization schedules, payments)
- Examples: `app/Models/Loan`, `app/Models/LoanPayment`, `app/Services/LoanScheduleService`, `app/Services/LoanPaymentPostingService`
- Pattern: Observer pattern triggers schedule generation via job queue; service encapsulates calculation logic

**Credit Card Domain:**
- Purpose: Models and services for credit card lifecycle (cycles, expenses, payments, KPIs)
- Examples: `app/Models/CreditCard`, `app/Models/CreditCardCycle`, `app/Models/CreditCardExpense`, `app/Services/CreditCardCycleService`, `app/Services/CreditCardKpiService`
- Pattern: Cycle management with expense posting; KPI service calculates interest/penalties

**Account/Transaction Domain:**
- Purpose: Generic financial tracking (accounts, transactions, categories)
- Examples: `app/Models/Account`, `app/Models/Transaction`, `app/Services/AccountBalanceService`
- Pattern: Transactions linked to accounts; balance derived from transaction history

**Filament Resources:**
- Purpose: Admin UI for CRUD operations and data inspection
- Examples: `app/Filament/Resources/Loans/`, `app/Filament/Resources/CreditCards/`, `app/Filament/Resources/Accounts/`
- Pattern: Resource classes define tables/forms; relation managers handle nested data; policies gate access

## Entry Points

**Web Dashboard:**
- Location: `app/Providers/Filament/AdminPanelProvider.php`
- Triggers: User login at `/admin`
- Responsibilities: Dashboard page, navigation, theme/plugin registration

**API Gateway:**
- Location: `/graphql` endpoint (Lighthouse configured in `config/graphql.php`)
- Triggers: GraphQL POST requests from frontend
- Responsibilities: Schema validation, query resolution, authorization

**Job Queue:**
- Location: `app/Jobs/` and Observer classes
- Triggers: Model events (create/update/delete)
- Responsibilities: Async processing of expensive calculations

**Database Migrations:**
- Location: `database/migrations/`
- Triggers: `php artisan migrate`
- Responsibilities: Schema versioning and deployment

## Error Handling

**Strategy:** Try-catch in critical paths; validation at request layer; graceful degradation in UI

**Patterns:**
- Database transactions in services prevent partial updates (`DB::transaction()`)
- Form request validation (`app/Http/Requests/`) validates input before processing
- Eloquent exceptions caught in Filament resource pages
- GraphQL validation handled by Lighthouse (type system enforces contracts)

## Cross-Cutting Concerns

**Logging:** Laravel stack driver to `storage/logs/` with named channels per module

**Validation:** Lighthouse `@rules` directives on GraphQL types; Form Request classes for HTTP input; Service-level domain validation

**Authentication:** Laravel Sanctum for API tokens; session-based for Filament dashboard; Spatie Permission middleware for role checking

**Authorization:** Filament policies injected into resource pages; GraphQL middleware restricts queries by role; `HasUserScoping` trait filters queries to current user's accounts

---

*Architecture analysis: 2026-03-23*
