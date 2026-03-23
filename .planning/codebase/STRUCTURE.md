# Codebase Structure

**Analysis Date:** 2026-03-23

## Directory Layout

```
second-brain/
├── app/                      # Application source code
│   ├── Enums/               # Domain enums (LoanStatus, CreditCardStatus, etc.)
│   ├── Filament/            # Admin dashboard resources and pages
│   ├── Http/                # HTTP request handling
│   ├── Jobs/                # Background job definitions
│   ├── Livewire/            # Livewire components (interactive server-side components)
│   ├── Models/              # Eloquent models (User, Loan, CreditCard, Account, etc.)
│   ├── Observers/           # Model observers (hooks for model events)
│   ├── Policies/            # Authorization policies (access control)
│   ├── Providers/           # Service provider registrations
│   ├── Repositories/        # Repository pattern for complex queries
│   ├── Services/            # Business logic layer (Loan, CreditCard, Account services)
│   ├── Support/             # Utility code (Concerns, custom helpers)
│   └── Traits/              # Reusable model traits
├── bootstrap/               # Framework bootstrap (cache, app.php)
├── config/                  # Configuration files (database, services, auth, etc.)
├── database/                # Database layer
│   ├── factories/           # Test data factories
│   ├── migrations/          # Schema migrations (versioned changes)
│   └── seeders/             # Database seeders (initial data)
├── graphql/                 # GraphQL schema definition
├── public/                  # Web root (index.php, assets)
├── resources/               # Frontend and view resources
│   ├── css/                 # Global CSS (Tailwind imports)
│   ├── js/                  # Vue components and frontend scripts
│   ├── views/               # Blade templates (Filament layout, fallback views)
│   └── lang/                # Localization files
├── routes/                  # Route definitions
│   ├── api.php              # API routes (GraphQL, REST if any)
│   ├── web.php              # Web routes (Filament, public routes)
│   └── console.php          # Artisan commands
├── storage/                 # Runtime data (logs, cache, sessions)
├── tests/                   # Test suite
│   ├── Feature/             # Integration tests (HTTP, jobs, workflows)
│   └── Unit/                # Unit tests (services, models, business logic)
├── vendor/                  # Composer dependencies (not committed)
├── node_modules/            # npm dependencies (not committed)
├── .env                     # Environment configuration (secrets, not committed)
├── .env.example             # Template for .env
├── composer.json            # PHP dependencies and scripts
├── package.json             # npm dependencies and scripts
├── phpunit.xml              # PHPUnit configuration
├── vite.config.js           # Vite bundler configuration
├── tailwind.config.js       # Tailwind CSS configuration
├── postcss.config.js        # PostCSS transformations
├── jsconfig.json            # JavaScript path aliases
├── ARCHITECTURE.md          # High-level architecture (this project)
├── README.md                # Project overview
└── SECURITY_CHECKLIST.md    # Security guidelines
```

## Directory Purposes

**app/Enums/:**
- Purpose: Domain enums for type-safe status values
- Contains: PHP 8.1+ Enum classes
- Key files: `LoanStatus.php`, `LoanPaymentStatus.php`, `CreditCardStatus.php`, `CreditCardPaymentStatus.php`, `CreditCardCycleStatus.php`, `CreditCardType.php`

**app/Filament/:**
- Purpose: Admin dashboard resource definitions and custom pages
- Contains: CRUD pages (List/Create/Edit), custom pages (reports), relation managers, table/form schemas
- Key files: `app/Filament/Resources/` - 5 resources (Loans, CreditCards, Accounts, Transactions, Roles); `app/Filament/Pages/FinanceReport.php` (custom dashboard page); `app/Filament/Widgets/` - KPI widgets

**app/Models/:**
- Purpose: Eloquent ORM models representing database tables
- Contains: Model classes with relations, casts, computed properties
- Key models: User, Account, Loan, LoanPayment, CreditCard, CreditCardCycle, CreditCardExpense, CreditCardPayment, Transaction, TransactionType, TransactionCategory

**app/Services/:**
- Purpose: Business logic layer - encapsulates domain operations
- Contains: 9 service classes (Loan, CreditCard, Account, Finance calculation services)
- Typical pattern: Inject dependencies, use models via constructor, return typed data/models

**app/Observers/:**
- Purpose: React to model lifecycle events (created, updated, deleted)
- Contains: Observer classes that dispatch jobs or update related models
- Example: LoanObserver triggers GenerateLoanPaymentsJob on loan creation/update

**app/Http/:**
- Purpose: HTTP request handling (Controllers, Middleware, FormRequests)
- Contains: Request validation classes, middleware for rate limiting/auth, API controllers
- Structure: `Controllers/` for request handlers, `Middleware/` for cross-cutting concerns, `Requests/` for validation

**app/Policies/:**
- Purpose: Authorization rules for Filament resources and GraphQL queries
- Contains: One policy per model (13 policies total)
- Pattern: Methods like `viewAny()`, `view()`, `create()`, `update()`, `delete()` return true/false

**app/Support/Concerns/:**
- Purpose: Reusable concerns/mixins for shared behavior
- Contains: Traits like `HasWorkdayCalculation` (Italian holidays + weekend logic)

**app/Traits/:**
- Purpose: Model traits for common functionality
- Key files: `HasUserScoping.php` - scopes queries to current user's accounts

**database/migrations/:**
- Purpose: Versioned database schema changes
- Contains: Migration classes that create tables, add columns, modify schema
- Pattern: Migrations are applied in order via `php artisan migrate`

**database/factories/:**
- Purpose: Test data generators
- Contains: Factory classes (UserFactory, LoanFactory, etc.) for seeding test databases

**graphql/:**
- Purpose: GraphQL schema definition
- Contains: `schema.graphql` - Type definitions (Query, Mutation, Types), field directives, custom scalars
- Pattern: Lighthouse processes directives like @find, @paginate, @rules to resolve fields

**resources/css/:**
- Purpose: Global stylesheets
- Contains: `app.css` - Tailwind imports and custom CSS

**resources/js/:**
- Purpose: Vue 3 components and frontend logic
- Contains: `.vue` files, JavaScript utilities
- Pattern: Single-file components with `<template>`, `<script setup>`, `<style scoped>`

**resources/views/:**
- Purpose: Blade templates (mostly for Filament layout, fallback pages)
- Contains: Filament page views, custom Blade components

**tests/Unit/:**
- Purpose: Unit tests for services, models, utilities
- Contains: Test classes with @test methods
- Example: `LoanScheduleServiceTest.php` tests schedule generation logic

**tests/Feature/:**
- Purpose: Integration tests for workflows, auth, API endpoints
- Contains: Test classes that exercise multiple layers
- Example: `CreditCardLifecycleIntegrationTest.php` tests full credit card workflow

## Key File Locations

**Entry Points:**
- `public/index.php` - Laravel entry point
- `app/Providers/Filament/AdminPanelProvider.php` - Filament dashboard registration
- `routes/web.php` - Web routes (dashboard, public pages)
- `routes/api.php` - API routes (GraphQL endpoint)
- `graphql/schema.graphql` - GraphQL API schema

**Configuration:**
- `config/app.php` - Application name, timezone, service providers
- `config/database.php` - Database connection settings
- `config/auth.php` - Authentication guards and providers
- `config/graphql.php` - Lighthouse configuration
- `config/permission.php` - Spatie Permission configuration
- `.env.example` - Required environment variables template

**Core Logic:**
- `app/Services/` - All business logic (loan calculations, credit card management)
- `app/Models/` - All data models and relations
- `app/Observers/` - Event handlers for model lifecycle
- `app/Policies/` - Access control logic

**Testing:**
- `tests/TestCase.php` - Base test class with utilities (RefreshDatabase, etc.)
- `tests/Unit/` - Service and model unit tests
- `tests/Feature/` - Workflow and integration tests

## Naming Conventions

**Files:**
- Model: `PascalCase.php` (e.g., `Loan.php`, `CreditCard.php`)
- Service: `PascalCaseService.php` (e.g., `LoanScheduleService.php`)
- Job: `PascalCaseJob.php` (e.g., `GenerateLoanPaymentsJob.php`)
- Observer: `PascalCaseObserver.php` (e.g., `LoanObserver.php`)
- Policy: `PascalCasePolicy.php` (e.g., `LoanPolicy.php`)
- Test: `PascalCaseTest.php` (e.g., `LoanScheduleServiceTest.php`)
- Migration: `timestamp_descriptive_action.php` (e.g., `2026_03_17_091146_create_loans_table.php`)

**Directories:**
- Namespace grouping by domain (Loans, CreditCards, Accounts, Transactions)
- Filament resources grouped by entity: `app/Filament/Resources/Loans/`, with subdirectories for Pages, Tables, Schemas, RelationManagers

**Database:**
- Table names: snake_case plural (loans, credit_cards, loan_payments)
- Column names: snake_case (due_date, monthly_payment, interest_rate)
- Foreign keys: `{entity}_id` (loan_id, user_id)
- Timestamp columns: created_at, updated_at, deleted_at

## Where to Add New Code

**New Financial Feature (e.g., new loan type):**
- Models: `app/Models/NewEntity.php` + migration `database/migrations/timestamp_create_new_entities_table.php`
- Service: `app/Services/NewEntityService.php` (business logic)
- Observer: `app/Observers/NewEntityObserver.php` (if model events trigger jobs)
- Filament: `app/Filament/Resources/NewEntity/` with Pages, Schemas, RelationManagers
- GraphQL: Add types and queries to `graphql/schema.graphql`
- Tests: `tests/Unit/NewEntityServiceTest.php` + `tests/Feature/NewEntityWorkflowTest.php`
- Authorization: `app/Policies/NewEntityPolicy.php`

**New Service/Business Logic:**
- Create service in `app/Services/{Domain}Service.php`
- Inject dependencies in constructor
- Use models via constructor injection or Model::query()
- Wrap multi-step logic in `DB::transaction()` for atomicity
- Throw domain exceptions for errors (or validation via Form Requests)

**New Filament Resource:**
- Create resource: `app/Filament/Resources/Entity/` directory
- Define pages: `Pages/CreateEntity.php`, `EditEntity.php`, `ListEntity.php`
- Define form: `Schemas/EntityForm.php` with fields and validations
- Define table: `Tables/EntitiesTable.php` with columns and filters
- Attach policies: `app/Policies/EntityPolicy.php` gates access

**New GraphQL Query/Mutation:**
- Add type definition to `graphql/schema.graphql`
- Use Lighthouse directives: @find, @paginate, @all, @rules, @whereAuth
- Or reference custom resolvers in service classes
- Test with `tests/Feature/GraphQL/` feature tests

**Utilities/Shared Code:**
- Traits: `app/Traits/` for model mixins
- Concerns: `app/Support/Concerns/` for class mixins
- Helpers: `app/Support/` for static utility functions

## Special Directories

**storage/:**
- Purpose: Runtime files (logs, cache, sessions)
- Generated: Yes - created by Laravel at runtime
- Committed: No - directory ignored in .gitignore; `storage/framework/` must exist

**vendor/:**
- Purpose: Composer dependencies
- Generated: Yes - created by `composer install`
- Committed: No - directory in .gitignore; use composer.lock for reproducibility

**node_modules/:**
- Purpose: npm dependencies
- Generated: Yes - created by `npm install`
- Committed: No - directory in .gitignore; use package-lock.json for reproducibility

**bootstrap/cache/:**
- Purpose: Laravel bootstrap cache for performance
- Generated: Yes - created by `php artisan config:cache`, etc.
- Committed: No - regenerated on each deployment

**public/build/:**
- Purpose: Vite-compiled assets (CSS, JS)
- Generated: Yes - created by `npm run build`
- Committed: No - regenerated on deployment

---

*Structure analysis: 2026-03-23*
