# Codebase Structure

**Analysis Date:** 2025-04-21

## Directory Layout

```
second-brain/
├── app/                          # Core application code
│   ├── Enums/                    # Typed constants (CreditCardStatus, LoanStatus, etc.)
│   ├── Filament/                 # Admin panel resources and pages
│   │   ├── Pages/                # Custom dashboard pages
│   │   ├── Resources/            # CRUD resources (one per model)
│   │   └── Widgets/              # Dashboard widgets (KPIs, charts)
│   ├── Http/                     # HTTP layer
│   │   ├── Controllers/          # Minimal, mostly empty
│   │   ├── Middleware/           # ApiRateLimitMiddleware, auth checks
│   │   └── Requests/             # Form validation requests
│   ├── Jobs/                     # Queued jobs (framework ready)
│   ├── Mail/                     # Mail classes (framework ready)
│   ├── Models/                   # Eloquent models (16 core models)
│   ├── Notifications/            # Notification classes (framework ready)
│   ├── Observers/                # Model event handlers (6 observers)
│   ├── Policies/                 # Authorization policies (13 policies)
│   ├── Providers/                # Service providers
│   │   ├── AppServiceProvider.php      # Model observation registration
│   │   ├── AuthServiceProvider.php     # Authorization and policies
│   │   └── Filament/AdminPanelProvider.php
│   ├── Repositories/             # Data access abstraction (minimal)
│   ├── Services/                 # Business logic services (13 services)
│   ├── Support/                  # Shared utilities
│   │   └── Concerns/HasWorkdayCalculation.php
│   └── Traits/                   # Reusable model traits
├── bootstrap/                    # Framework bootstrap
├── config/                       # Configuration files
├── database/
│   ├── migrations/               # Schema definitions
│   ├── seeders/                  # Database seeders
│   └── factories/                # Model factories
├── resources/                    # Frontend resources (views, CSS)
│   ├── views/                    # Blade templates
│   └── css/                      # Tailwind CSS
├── routes/
│   ├── web.php                   # Web routes (minimal)
│   ├── api.php                   # API routes (framework ready)
│   └── console.php               # Artisan commands
├── public/                       # Web-accessible files
├── storage/                      # Runtime files, logs, cache
├── tests/                        # Test files
├── vendor/                       # Composer dependencies
├── package.json                  # NPM dependencies (Vite, Tailwind)
├── composer.json                 # PHP dependencies
├── vite.config.js                # Vite build config
├── tailwind.config.js            # Tailwind CSS config
├── phpunit.xml                   # Test configuration
├── artisan                       # CLI entry point
└── .env.example                  # Environment template
```

## Directory Purposes

**app/Enums/:**
- Purpose: Type-safe enumerations for financial statuses and methods
- Contains: CreditCardStatus, LoanStatus, SubscriptionStatus, InterestCalculationMethod, CreditCardType, etc.
- Key files: 10 enum files using PHP 8.1+ enum syntax
- Pattern: Each enum has methods like `getLabel()` for UI display

**app/Filament/Resources/:**
- Purpose: Admin CRUD interface configuration
- Contains: One Resource class per model (AccountsResource, TransactionResource, etc.)
- Structure: Each resource has Pages/ (Create/Edit/List/View), Schemas/ (forms), Tables/ (columns)
- Key files: 15 resource directories with ~102 PHP files total
- Pattern: Resources define form fields, table columns, table actions, relationship managers

**app/Filament/Widgets/:**
- Purpose: Dashboard KPI and reporting widgets
- Contains: StatsOverview, CashflowReport, NetWorthWidget, TotalDebtsWidget, etc.
- Key files: ~14 widget classes
- Pattern: Chartable or StatOverview widget types using Chart.js library

**app/Http/Middleware/:**
- Purpose: Request filtering and preprocessing
- Key files: ApiRateLimitMiddleware, CheckModuleEnabled
- Pattern: Laravel middleware stack applied to routes or groups

**app/Http/Requests/:**
- Purpose: Form request validation and authorization
- Key files: StoreTransactionRequest, StoreAccountRequest, StoreLoanRequest, StoreTransactionCategoryRequest
- Pattern: Centralized validation rules with custom messages
- Validation scope: Prevents invalid data from reaching models

**app/Models/:**
- Purpose: Data representation and relationships
- Core models:
  - Account (user bank accounts, balance tracking)
  - Transaction (income/expense/transfer)
  - TransactionType, TransactionCategory (taxonomies)
  - CreditCard (revolving and charge cards)
  - CreditCardCycle (monthly billing cycles)
  - CreditCardExpense (charges on card)
  - CreditCardPayment (payments/installments)
  - Loan (personal loans)
  - LoanPayment (loan installments)
  - Subscription (recurring expenses)
  - User (authentication, roles/permissions)
  - UserSetting (per-user configuration)
  - AuditLog, Notification, Backup (utility models)
- Common patterns:
  - SoftDeletes for accounts and transactions
  - User-scoped via HasUserScoping trait
  - Decimal casts for monetary fields
  - Status enums for workflow states

**app/Observers/:**
- Purpose: Model lifecycle event handlers
- Registered in: AppServiceProvider::boot()
- Key observers:
  - TransactionObserver (handles account balance sync)
  - CreditCardExpenseObserver (cycle assignment and balance)
  - CreditCardPaymentObserver (status transitions)
  - CreditCardCycleObserver (cycle completion)
  - SubscriptionObserver (renewal calculations)
  - LoanPaymentObserver (loan progress)
- Pattern: Fire services from created/updated/deleted hooks, maintain consistency

**app/Policies/:**
- Purpose: Authorization rules per model
- Structure: One policy per major model
- Key files: AccountPolicy, TransactionPolicy, CreditCardPolicy, LoanPolicy, SubscriptionPolicy, etc.
- Authorization pattern: Checked by Filament resources before CRUD operations
- Superadmin bypass: Configured in AuthServiceProvider::boot()

**app/Services/:**
- Purpose: Business logic encapsulation and reusability
- Core services:
  - RevolvingCreditCalculator (interest calculations, payment breakdowns)
  - CreditCardCycleService (cycle lifecycle, status management)
  - CreditCardExpenseService (expense synchronization, cycle assignment)
  - CreditCardBalanceService (card debt tracking, principal application)
  - AccountBalanceService (transaction-to-account sync)
  - LoanScheduleService (loan payment schedules)
  - SubscriptionService (renewal dates, frequency calculations)
  - CreditCardKpiService (metrics and reporting)
  - FinanceReportService (aggregated reports)
  - PermissionService (role/permission helpers)
- Dependency injection: Constructor injection common, some use app() helper
- Transaction safety: Critical operations wrapped in DB::transaction()

**app/Repositories/:**
- Purpose: Data access abstraction layer
- Current usage: Only LoanRepository present
- Pattern: Not widely adopted; most services access models directly
- Future: Could expand if data access patterns need isolation

**app/Support/Concerns/:**
- Purpose: Reusable trait concerns
- HasWorkdayCalculation: Skips weekends/Italian holidays for date calculations
- Used by: CreditCardCycleService, Loan/LoanPayment models
- Pattern: Shared logic for date manipulation in financial contexts

**app/Traits/:**
- Purpose: Eloquent model augmentation
- HasUserScoping: Global query scope filtering by authenticated user_id
- Applied to: Account, Transaction, CreditCard, Loan, Subscription, TransactionCategory
- Effect: Automatic multi-tenancy without explicit WHERE clauses
- Removal: scopeWithoutUserScope() for admin/reports accessing all users

**database/migrations/:**
- Purpose: Schema version control
- Order: Timestamped filenames control execution order
- Core tables: users, accounts, transactions, credit_cards, cycles, expenses, payments, loans, subscriptions
- Constraints: Foreign keys with CASCADE/SET NULL actions
- Utilities: cache, jobs, permissions/roles (Spatie)

**database/factories/:**
- Purpose: Test and seeding data generation
- Pattern: Laravel factories for creating model instances with realistic data

**resources/views/:**
- Purpose: Blade template views (minimal, mostly handled by Filament)
- Usage: Base layout, custom pages
- Pattern: Filament admin panel is self-rendering; custom views for public pages

**routes/web.php:**
- Purpose: Web route definitions
- Current routes: Only root redirect to Filament login
- Pattern: Minimal; Filament auto-registers admin routes

**routes/api.php:**
- Purpose: REST API endpoints
- Current status: Framework in place, no endpoints defined
- Middleware: Rate limiting via ApiRateLimitMiddleware
- Future: GraphQL config exists in config/lighthouse.php

**config/:**
- Purpose: Application configuration
- Key files:
  - app.php (name, timezone, providers)
  - auth.php (authentication providers)
  - database.php (connection config)
  - permission.php (role/permission config for Spatie)
  - lighthouse.php (GraphQL schema)
  - logging.php (log channels)

## Key File Locations

**Entry Points:**
- `routes/web.php`: Web route definitions (minimal)
- `routes/api.php`: API route definitions (framework ready)
- `app/Providers/Filament/AdminPanelProvider.php`: Filament admin configuration
- `bootstrap/app.php`: Laravel bootstrap container

**Configuration:**
- `config/app.php`: Application name, timezone, service providers
- `config/auth.php`: Authentication drivers (Laravel Fortify via Filament)
- `config/permission.php`: Spatie role/permission configuration
- `config/database.php`: Database connection settings
- `.env.example`: Required environment variables template

**Core Logic:**
- `app/Services/RevolvingCreditCalculator.php`: Interest calculation algorithms
- `app/Services/CreditCardCycleService.php`: Cycle lifecycle orchestration
- `app/Services/CreditCardExpenseService.php`: Expense assignment and balance sync
- `app/Models/Account.php`: Primary financial entity
- `app/Models/Transaction.php`: Core transaction model
- `app/Models/CreditCard.php`: Credit card with cycle management
- `app/Observers/TransactionObserver.php`: Account balance synchronization

**Testing:**
- `tests/`: Test suite (PHPUnit)
- `phpunit.xml`: Test configuration
- `database/factories/`: Test data factories

**Admin UI:**
- `app/Filament/Resources/`: Admin CRUD resources (15 resources)
- `app/Filament/Pages/`: Custom dashboard pages
- `app/Filament/Widgets/`: Dashboard widgets and KPI displays

## Naming Conventions

**Files:**
- Model files: PascalCase, singular (Account.php, Transaction.php, CreditCard.php)
- Service files: PascalCase ending with Service (AccountBalanceService.php)
- Observer files: PascalCase ending with Observer (TransactionObserver.php)
- Policy files: PascalCase ending with Policy (AccountPolicy.php)
- Resource files: PascalCase ending with Resource (AccountsResource.php)
- Traits: PascalCase starting with "Has" or "Is" (HasUserScoping.php, HasWorkdayCalculation.php)

**Classes:**
- Models: PascalCase, singular (Account, Transaction, CreditCard)
- Services: PascalCase, descriptive, ending with Service (CreditCardCycleService, RevolvingCreditCalculator)
- Observers: PascalCase, Model + Observer (TransactionObserver)
- Policies: PascalCase, Model + Policy (AccountPolicy)
- Enums: PascalCase (CreditCardStatus, LoanStatus)

**Methods:**
- Model relationships: camelCase, plural or singular matching relation (transactions(), creditCards(), payments())
- Service methods: camelCase, descriptive, verb-noun pattern (handleCreated(), calculatePaymentBreakdown(), syncExpense())
- Observer methods: Model lifecycle names (created, updated, deleted, creating, updating, deleting)
- Query scopes: camelCase, starting with "scope" prefix (scopeActive(), scopeForRenewal())

**Variables:**
- Properties: camelCase (currentBalance, totalSpent, interestRate)
- Database columns: snake_case (current_balance, total_spent, interest_rate)
- Local variables: camelCase (newAmount, oldBalance, principalAmount)

**Types:**
- Eloquent models: Model class names directly (Account, Transaction)
- Collections: Type hints with Illuminate\Database\Eloquent\Collection
- Numeric amounts: float for calculations, decimal:2 casts in models
- Dates: Carbon or date casts

## Where to Add New Code

**New Feature (e.g., Budget Tracking):**
- Model: `app/Models/Budget.php` with relationships to Account and TransactionCategory
- Service: `app/Services/BudgetService.php` for calculations and enforcement
- Observer: `app/Observers/BudgetObserver.php` if tracking transactions against budget
- Filament Resource: `app/Filament/Resources/BudgetsResource.php` with Pages, Schemas, Tables
- Policy: `app/Policies/BudgetPolicy.php` for access control
- Database: `database/migrations/YYYY_MM_DD_HHMMSS_create_budgets_table.php`
- Routes: Filament auto-registers, no manual route needed
- Tests: `tests/Feature/BudgetTest.php` and `tests/Unit/Services/BudgetServiceTest.php`

**New Model:**
- Definition: `app/Models/NewModel.php`
  - Add HasFactory, SoftDeletes if needed
  - Add HasUserScoping for multi-tenancy
  - Define relationships and casts
- Migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_new_models_table.php`
- Policy: `app/Policies/NewModelPolicy.php` in AuthServiceProvider
- Resource: `app/Filament/Resources/NewModelsResource.php` for CRUD UI
- Observer: `app/Observers/NewModelObserver.php` if lifecycle events needed, register in AppServiceProvider
- Request: `app/Http/Requests/StoreNewModelRequest.php` if special validation

**New Service Method:**
- Add to existing service in `app/Services/SomethingService.php`
- Or create new service: `app/Services/NewFeatureService.php`
- Dependency injection via constructor
- Use app(DependentService::class) for lazy resolution
- Wrap in DB::transaction() for multi-table operations
- Use lockForUpdate() for concurrency safety

**New Filament Resource:**
- Create `app/Filament/Resources/ModelsResource.php`
- Add nested structure:
  - `Pages/CreateModels.php`, `EditModels.php`, `ListModels.php`, `ViewModels.php`
  - `Schemas/ModelsSchema.php` (form definition)
  - `Tables/ModelsTable.php` (column definition)
  - `RelationManagers/` if managing relationships
- Register in AdminPanelProvider if not auto-discovered

**New Observer:**
- Create `app/Observers/NewModelObserver.php`
- Implement method per event: created(), updated(), deleted(), creating(), updating(), deleting()
- Register in `app/Providers/AppServiceProvider.php` boot():
  ```php
  NewModel::observe(NewModelObserver::class);
  ```
- Delegate to services for complex logic

**New Utility/Trait:**
- Shared logic: `app/Traits/NameOfTrait.php`
- Shared concerns: `app/Support/Concerns/NameOfConcern.php`
- Add via `use TraitName` in models needing it

**Migration:**
- Create `database/migrations/YYYY_MM_DD_HHMMSS_description.php`
- Use Schema::create() for new tables or Schema::table() for modifications
- Add foreign key constraints with ->cascadeOnDelete() or ->restrictOnDelete()
- Add indexes for commonly queried columns (user_id, status)

**Test:**
- Feature tests: `tests/Feature/[FeatureName]Test.php`
- Unit tests: `tests/Unit/Services/[ServiceName]Test.php`
- Run: `php artisan test` or `php artisan test --filter=TestName`

## Special Directories

**app/Jobs/:**
- Purpose: Queued jobs for async processing
- Generated: Framework-ready, no jobs currently implemented
- Committed: Yes
- Use case: Future subscription renewals, loan payment schedules, data exports

**app/Mail/:**
- Purpose: Mail class definitions
- Generated: Framework-ready, no mailable classes yet
- Committed: Yes
- Use case: Transaction confirmations, payment reminders

**app/Notifications/:**
- Purpose: In-app and external notifications
- Generated: Framework-ready, minimal implementation
- Committed: Yes
- Use case: Payment due notifications, balance alerts

**storage/**:
- Purpose: Runtime files, logs, compiled views, cache
- Generated: Yes, at runtime
- Committed: No (in .gitignore)
- Subdirs: storage/logs/, storage/app/, storage/cache/

**bootstrap/cache/**:
- Purpose: Application cache (config, routes, compiled classes)
- Generated: Yes, via php artisan optimize
- Committed: No (in .gitignore)
- Manual clear: `php artisan cache:clear` or `php artisan config:clear`

**node_modules/:**
- Purpose: NPM package dependencies
- Generated: Yes, via npm install
- Committed: No (in .gitignore)
- Contains: Vite, Tailwind CSS, Laravel Mix, etc.

**vendor/:**
- Purpose: Composer PHP dependencies
- Generated: Yes, via composer install
- Committed: No (in .gitignore)
- Contains: Laravel framework, Filament, Spatie Permission, etc.

---

*Structure analysis: 2025-04-21*
