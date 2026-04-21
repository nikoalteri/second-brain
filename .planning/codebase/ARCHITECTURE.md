# Architecture

**Analysis Date:** 2025-04-21

## Pattern Overview

**Overall:** Event-Driven MVC with Domain-Driven Service Layer

**Key Characteristics:**
- Eloquent models with observer pattern for business logic triggering
- Dedicated service layer for complex financial calculations and state management
- Filament 3 admin panel as primary UI (no separate frontend)
- User-scoped multi-tenancy via traits and policies
- Transactional financial operations with balance consistency
- Domain-specific enums for financial states and calculations

## Layers

**Presentation Layer (Filament):**
- Purpose: Admin dashboard and data management UI
- Location: `app/Filament/`
- Contains: Resources (CRUD pages), custom Pages, Widgets (KPI dashboards)
- Depends on: Models, Services, Policies
- Used by: End users via web browser

**HTTP Layer:**
- Purpose: Request handling, validation, middleware
- Location: `app/Http/`
- Contains: Controllers (minimal), Requests (form validation), Middleware
- Depends on: Models, Services
- Used by: Routes and API endpoints

**Domain Model Layer:**
- Purpose: Data representation and relationships
- Location: `app/Models/`
- Contains: 16 core models (Account, Transaction, CreditCard, Loan, Subscription, etc.)
- Depends on: Enums, Traits, Observers (via boot)
- Used by: Services, Observers, Filament Resources

**Service Layer:**
- Purpose: Business logic, financial calculations, state synchronization
- Location: `app/Services/`
- Contains: RevolvingCreditCalculator, CreditCardCycleService, CreditCardExpenseService, etc.
- Depends on: Models, Enums, Support concerns
- Used by: Observers, Filament resources, other services

**Observer Layer (Event Handlers):**
- Purpose: Reactive business logic triggered by model lifecycle events
- Location: `app/Observers/`
- Contains: TransactionObserver, CreditCardExpenseObserver, CreditCardCycleObserver, etc.
- Depends on: Models, Services
- Used by: AppServiceProvider (registered boot)

**Authorization Layer:**
- Purpose: Access control and resource authorization
- Location: `app/Policies/`
- Contains: Policies for each major model
- Depends on: Models, User roles (via Spatie Permission)
- Used by: Filament resources, controllers

**Support Layer:**
- Purpose: Reusable traits and concerns
- Location: `app/Support/`, `app/Traits/`
- Contains: HasWorkdayCalculation (weekend/holiday skipping), HasUserScoping (multi-tenancy)
- Depends on: Carbon for date manipulation
- Used by: Models, Services

**Repository Layer:**
- Purpose: Data access abstraction (minimal usage)
- Location: `app/Repositories/`
- Contains: LoanRepository (basic CRUD, not widely adopted)
- Depends on: Models
- Used by: Services (rarely)

## Data Flow

**Transaction Creation Flow:**

1. User creates Transaction via Filament
2. TransactionResource validates via StoreTransactionRequest
3. Transaction model created (via Eloquent)
4. TransactionObserver::created() fires
5. AccountBalanceService::handleCreated() increments account balance
6. Observer may trigger paired transaction if transfer

**Credit Card Expense Flow:**

1. User creates CreditCardExpense via Filament
2. CreditCardExpenseObserver::creating() validates via CreditCardExpenseService
3. CreditCardExpenseObserver::created() calls syncExpense()
4. CreditCardExpenseService::syncExpense():
   - Determines cycle via resolveCycle()
   - Updates cycle assignment
   - Updates card current_balance via CreditCardBalanceService
   - Recomputes cycle total_spent
5. Card balance updates trigger downstream cycle calculations

**Credit Card Cycle Issuance Flow:**

1. User marks cycle as ISSUED via Filament
2. CreditCardCycleService::issueCycle() called
3. RevolvingCreditCalculator::calculatePaymentBreakdown():
   - Calculates daily balances for interest
   - Applies interest calculation method (daily balance or direct monthly)
   - Splits fixed payment into interest + principal
4. CreditCardPayment created with breakdown
5. Cycle status set to ISSUED
6. CreditCardCycleObserver::updated() may trigger payment sync

**Credit Card Payment Processing Flow:**

1. User marks CreditCardPayment as PAID
2. CreditCardPaymentObserver fires (status change)
3. CreditCardCycleService::syncCycleAndCardFromPayment():
   - Updates cycle paid_amount
   - Determines cycle status (PAID if fully paid, OVERDUE if past due)
   - Applies principal payment to card balance via CreditCardBalanceService
4. Card current_balance decremented
5. Cycle status updated
6. Related cycles refreshed

**State Management:**

- **Account Balance:** Incremented/decremented by Transaction observer
- **Card Current Balance:** Updated by CreditCardExpenseService and CreditCardPaymentObserver
- **Cycle Total Spent:** Sum of related expenses, updated by CreditCardExpenseService
- **Cycle Status:** OPEN → ISSUED → PAID/OVERDUE, managed by CreditCardCycleService
- **Payment Status:** PENDING → PAID, updates card debt via principal payment

## Key Abstractions

**RevolvingCreditCalculator:**
- Purpose: Encapsulates complex interest calculation logic
- Location: `app/Services/RevolvingCreditCalculator.php`
- Examples: `calculateDailyBalances()`, `calculatePaymentBreakdown()`, `calculateChargePaymentBreakdown()`
- Pattern: Accepts cycle/card data, returns calculation arrays
- Used by: CreditCardCycleService::issueCycle()

**CreditCardCycleService:**
- Purpose: Orchestrates cycle lifecycle and payment synchronization
- Location: `app/Services/CreditCardCycleService.php`
- Examples: `issueCycle()`, `ensureCurrentMonthCycle()`, `syncCycleAndCardFromPayment()`, `refreshCycleStatuses()`
- Pattern: Orchestrator service with transaction boundaries
- Coordinates: RevolvingCreditCalculator, CreditCardBalanceService, database locks

**CreditCardExpenseService:**
- Purpose: Manages expense-to-cycle assignment and balance updates
- Location: `app/Services/CreditCardExpenseService.php`
- Examples: `syncExpense()`, `removeExpense()`, `validateExpenseChange()`
- Pattern: Validates before sync, handles card moves and amount changes
- Uses: CreditCardCycleService::ensureCurrentMonthCycle(), CreditCardBalanceService

**CreditCardBalanceService:**
- Purpose: Encapsulates credit card debt calculation
- Location: `app/Services/CreditCardBalanceService.php`
- Examples: `addExpense()`, `removeExpense()`, `applyPrincipalPayment()`, `reversePrincipalPayment()`
- Pattern: Direct current_balance manipulation with balance checks
- Constraints: Prevents balance going below zero

**AccountBalanceService:**
- Purpose: Simple account balance synchronization
- Location: `app/Services/AccountBalanceService.php`
- Examples: `handleCreated()`, `handleUpdated()`, `handleDeleted()`
- Pattern: Observer callbacks, handles account transfers
- Used by: TransactionObserver

**LoanScheduleService:**
- Purpose: Loan payment schedule generation and calculations
- Location: `app/Services/LoanScheduleService.php`
- Pattern: Calculates monthly installments, interest, remaining balance
- Used by: Loan CRUD operations

**SubscriptionService:**
- Purpose: Subscription frequency and renewal calculations
- Location: `app/Services/SubscriptionService.php`
- Examples: `calculateMonthlyCost()`, `getUpcomingRenewals()`, `processRenewal()`
- Pattern: Frequency-aware calculations (monthly/annual/biennial)
- Used by: SubscriptionObserver, Filament widgets

## Entry Points

**Web Entry:**
- Location: `routes/web.php`
- Triggers: Browser requests to `/` or `/admin`
- Responsibilities: Redirects to Filament login (primary interface)

**Admin Panel:**
- Location: `app/Providers/Filament/AdminPanelProvider.php`
- Triggers: All authenticated requests under `/admin/*`
- Responsibilities: Configures navigation, resources, dashboard, themes

**API Routes:**
- Location: `routes/api.php`
- Triggers: Requests to `/api/*`
- Responsibilities: Rate limiting via ApiRateLimitMiddleware
- Status: Framework in place, no endpoints currently implemented

**Console Commands:**
- Location: `routes/console.php`
- Examples: Potential scheduled jobs for loan payments, subscription renewals
- Status: Framework in place, specific commands to be added

## Error Handling

**Strategy:** Exceptions propagate to Filament UI validation

**Patterns:**

- **Validation Exceptions:** Form request validation in HTTP layer via `app/Http/Requests/*.php`
- **Business Logic Exceptions:** Services throw catchable exceptions (e.g., insufficient credit limit)
- **Transaction Rollback:** DB::transaction() wraps critical operations in CreditCardCycleService, CreditCardExpenseService
- **Observer Failures:** If observer throws during save, transaction rolls back and user sees Filament notification
- **Foreign Key Constraints:** Database-level integrity via foreign key constraints with cascading actions

**Example patterns:**
```php
// In service
if ($amount > $card->available_credit) {
    throw new \Exception('Insufficient credit limit');
}

// In observer
DB::transaction(function () {
    // Locked queries, atomic updates
    $card->lockForUpdate()->find($id);
});
```

## Cross-Cutting Concerns

**Logging:**
- Framework: Laravel's Log facade
- Usage: TransactionObserver logs transaction creation events
- Location: Configured in `config/logging.php`
- Standard: Minimal logging, primarily for audit trail

**Validation:**
- Framework: Laravel Form Requests
- Location: `app/Http/Requests/`
- Examples: StoreTransactionRequest, StoreAccountRequest, StoreLoanRequest
- Pattern: Centralized rules with custom messages

**Authentication:**
- Framework: Laravel Fortify via Filament login
- Location: Built into Filament AdminPanelProvider
- Mechanism: Session-based authentication with remember token

**Authorization:**
- Framework: Spatie Permission + Laravel Policies
- Location: `app/Policies/`, registered in `app/Providers/AuthServiceProvider.php`
- Pattern: Gate::before() for superadmin bypass, policy checks per resource
- User Scoping: HasUserScoping trait enforces global scope for multi-tenancy

**Multi-Tenancy (User Scoping):**
- Mechanism: Global query scope via HasUserScoping trait
- Location: `app/Traits/HasUserScoping.php`
- Applied to: Account, Transaction, Loan, CreditCard, Subscription, etc.
- Effect: All queries automatically filtered by authenticated user_id
- Removal: scopeWithoutUserScope() for admin operations

**Financial Precision:**
- Decimal Casts: All monetary fields use `decimal:2` casts
- Rounding: Explicit round() calls in services (RevolvingCreditCalculator, etc.)
- Transactions: DB::transaction() for atomic multi-table updates
- Locks: lockForUpdate() in critical paths (CreditCardExpenseService)

---

*Architecture analysis: 2025-04-21*
