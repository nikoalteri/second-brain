# Architecture

**Analysis Date:** 2024-12-19

## Pattern Overview

**Overall:** Layered architecture with domain-driven organization and service extraction

**Key Characteristics:**
- **Filament-first UI** - Admin panel is primary interface (not traditional MVC)
- **Service layer abstraction** - Business logic extracted from models into services
- **Observer pattern** - Model lifecycle hooks trigger side effects (balance updates, postings)
- **Policy-based authorization** - User scoping via policies and traits
- **Enum-driven types** - Type-safe enums for domain values (CreditCardStatus, CreditCardType, etc.)
- **Multi-domain application** - Financial, health, lifestyle, travel domains coexist
- **GraphQL-ready** - Lighthouse GraphQL schema defined but minimally used
- **User-scoped data** - Global scope on models ensures user isolation via `HasUserScoping` trait

## Layers

**Models Layer:**
- Purpose: Data persistence and relationship definition
- Location: `app/Models/`
- Contains: 40 Eloquent models with relationships, casts, appended attributes
- Depends on: Eloquent traits, Enums, Carbon for dates
- Used by: Services, Observers, Policies, Filament Resources
- Key pattern: Uses trait `HasUserScoping` to automatically scope queries to authenticated user
- Relationships: Properly typed (BelongsTo, HasMany, HasManyThrough where applicable)

**Service Layer:**
- Purpose: Encapsulate business logic, calculations, and domain operations
- Location: `app/Services/`
- Contains: 15 concrete service classes handling domain operations
- Depends on: Models, Enums, Carbon, Laravel facades (DB, Auth)
- Used by: Observers, Filament Resources, Controllers (when REST API used)
- Key pattern: Constructor dependency injection with optional fallback to container
- Example: `CreditCardCycleService` orchestrates cycle issuance, payment application, balance updates

**Observer Layer:**
- Purpose: Trigger side effects on model lifecycle events
- Location: `app/Observers/`
- Contains: 6 observers registered in `AppServiceProvider`
- Depends on: Models, Services, Enums
- Used by: Eloquent ORM (automatic)
- Key pattern: Each observer hooks `created()`, `updated()`, `deleted()` events
- Example: `CreditCardCycleObserver` creates payment records when cycle marked PAID

**Policy Layer:**
- Purpose: Define authorization rules
- Location: `app/Policies/`
- Contains: 11 policies for user-scoped models
- Depends on: Models, auth() helper
- Used by: Filament, middleware (canViewAny, can, etc.)
- Key pattern: User ID matching - `$user->id === $model->user_id`
- Registered in: `app/Providers/AuthServiceProvider.php`

**Filament Admin Layer:**
- Purpose: CRUD interface for all domain entities
- Location: `app/Filament/`
- Contains: 36 resources with Pages, Forms, Tables, Relation Managers, 14 Widgets
- Depends on: Models, Policies, Services
- Used by: Web browsers (admin users only)
- Key pattern: Resource generates full CRUD, with customization via Schema (Forms) and Tables
- Dashboard: `/app/Filament/Pages/Dashboard.php`, FinanceReport: `/app/Filament/Pages/FinanceReport.php` (326 lines)

**Request Validation Layer:**
- Purpose: Validate incoming HTTP requests
- Location: `app/Http/Requests/`
- Contains: 4 request classes (Account, Transaction, TransactionCategory, Loan)
- Depends on: Illuminate\Foundation\Http\FormRequest
- Used by: Controllers (when REST API endpoints exist)
- Key pattern: `rules()` method defines validation, can reference other fields

**Enum Layer:**
- Purpose: Type-safe enum values for domain concepts
- Location: `app/Enums/`
- Contains: 30+ enums with backed values (string or int)
- Used by: Models (via `$casts`), Services, Forms, Policies
- Example: `CreditCardType::REVOLVING`, `CreditCardStatus::ACTIVE`

## Data Flow

**Credit Card Cycle Issuance (Complex Example):**

1. Scheduled job or manual action triggers `CreditCardCycleService::issueCycle($cycle)`
2. Service validates cycle status is OPEN and card exists
3. Service calculates payments via `RevolvingCreditCalculator` or manual breakdown
4. Service creates `CreditCardPayment` records for principal, interest, stamp duty
5. Service updates `CreditCardCycle::status` to ISSUED
6. Service updates `CreditCard::current_balance` via `CreditCardBalanceService`
7. `CreditCardCycleObserver::updated()` fires, detects status change to ISSUED, may log or trigger notifications
8. `CreditCardPaymentObserver::created()` fires when payment records created

**Payment Posting (Multi-Step):**

1. Filament form receives payment_date and status update to PAID
2. `CreditCardPaymentObserver::updated()` fires, calls `CreditCardPaymentPostingService`
3. Service creates `Transaction` record with debit (-) amount
4. Service updates related `CreditCardCycle::paid_amount` and `status`
5. Service calls `CreditCardBalanceService::applyPrincipalPayment()` to reduce card balance
6. Service updates `Account::balance` to reflect payment received
7. `TransactionObserver::created()` logs transaction and may trigger notifications
8. UI refreshes via Filament, shows updated balances

**State Management:**
- **Database transactions** - Complex operations wrapped in `DB::transaction()` to ensure atomicity
- **Model relationships** - Eager loaded via `loadMissing()` to avoid N+1 queries
- **Balance calculations** - Computed via services, cached in model attributes
- **Authorization** - Checked at Filament resource level via policies
- **User scoping** - Applied via global scope in trait, enforced on all queries

## Key Abstractions

**Service Abstractions:**
- `CreditCardCycleService` - Manages cycle lifecycle: creation, issuance, payment posting, status transitions
- `RevolvingCreditCalculator` - Pure calculation logic for payment breakdowns (interest, principal, stamp duty)
- `CreditCardBalanceService` - Updates card balance atomically, validates credit limit
- `LoanScheduleService` - Generates amortization schedules, calculates payment amounts
- `SubscriptionService` - Renewal logic, frequency-based payment generation

**Model Abstractions:**
- `HasUserScoping` trait - Automatically applies user filter to all queries
- Model attributes - Appended calculated fields: `available_credit`, `is_unlimited`, `net_worth`
- Model casts - Type conversion: `'amount' => 'decimal:2'`, `'status' => CreditCardStatus::class`

**Repository Pattern:**
- Minimal use: Only `LoanRepository` exists
- Purpose: Encapsulate complex query logic
- Location: `app/Repositories/LoanRepository.php`

## Entry Points

**Web Application:**
- Location: `routes/web.php`
- Triggers: Any request to `/`
- Responsibilities: Redirects to Filament admin login (`route('filament.admin.auth.login')`)
- No traditional web routes - everything goes through Filament admin panel

**GraphQL API:**
- Location: `/graphql` route (from Lighthouse config)
- Schema: `graphql/schema.graphql` (41 lines, minimal)
- Triggers: POST requests to `/graphql`
- Responsibilities: Query users, paginated user lists (basic queries only)
- Status: Configured but not heavily used

**REST API:**
- Location: `routes/api.php`
- Triggers: Any request to `/api/*`
- Middleware: API rate limiting, CORS
- Responsibilities: Empty - no endpoints defined
- Status: Infrastructure in place but unused

**Console Commands:**
- Location: `routes/console.php`
- Triggers: `php artisan` CLI
- Responsibilities: Job scheduling, command registration
- Example: `GenerateLoanPaymentsJob` - generates loan payment schedules

## Error Handling

**Strategy:** Laravel exception handling with policy/authorization checks

**Patterns:**
- **Authorization errors** - Policies throw `AuthorizationException` when policy methods return false
- **Validation errors** - Form requests throw `ValidationException` with field-level error messages
- **Database errors** - Transactions rolled back on exception, caught and logged
- **Soft deletes** - Models use soft deletes, observers handle cleanup
- **Observable failures** - Observers do not throw; failures logged silently to avoid cascade

## Cross-Cutting Concerns

**Logging:** 
- Framework default: Stack driver (single log file) or multiple channels
- Services log complex operations via `Log::info()`, `Log::error()`
- Observers log model changes via `AuditLog` model
- Config: `config/logging.php`

**Validation:** 
- Form requests: `app/Http/Requests/` define rules for stored models
- Service-level: Validators in services check business logic (e.g., credit limit validation)
- Policy-level: Authorization checks in `app/Policies/`
- Eloquent: Mutators/casts ensure type safety

**Authentication:** 
- Laravel Sanctum for API tokens (configured in `config/sanctum.php`)
- Filament authentication: Built-in user/password via standard Laravel auth
- User model: `Illuminate\Foundation\Auth\User` with Spatie permissions
- Session-based: `SESSION_DRIVER=database` for persistent sessions

**Authorization:**
- Spatie Laravel Permission package: Roles and permissions
- Policies: User-scoped access checks via `$user->id === $model->user_id`
- Gates: Defined in `AuthServiceProvider` via `$policies` mapping
- Filament enforcement: Resource authorization via policy methods

**Permissions:**
- Spatie integration: `spatie/laravel-permission` v7.2
- Config: `config/permission.php`
- Service: `PermissionService` (62 lines) - Role and permission management
- Models use `HasRoles` trait from Spatie

**Queue & Jobs:**
- Driver: Database-backed (`QUEUE_CONNECTION=database`)
- Job example: `GenerateLoanPaymentsJob` - generates scheduled payments
- Execution: Via `php artisan queue:listen` (in dev environment)
- Timeout: Default 0 (unlimited) for long-running jobs

**Database:**
- Connection: SQLite by default, configurable to MySQL, PostgreSQL
- Transactions: Used in services for multi-step operations
- Global scopes: User scoping via `HasUserScoping` trait
- Relationships: Typed with proper return types
- Migrations: 63 migrations in `database/migrations/`

---

*Architecture analysis: 2024-12-19*
