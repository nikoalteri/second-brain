# Codebase Structure

**Analysis Date:** 2024-12-19

## Directory Layout

```
second-brain/
├── app/                      # Laravel application code
│   ├── Enums/               # PHP 8.1+ Enums for type-safe values
│   ├── Filament/            # Filament admin panel resources & widgets
│   ├── Http/                # Controllers, Requests, Middleware
│   ├── Jobs/                # Queued jobs (background processing)
│   ├── Models/              # Eloquent ORM models (40 models total)
│   ├── Observers/           # Model lifecycle observers
│   ├── Policies/            # Authorization policies (11 models)
│   ├── Providers/           # Service providers (App, Auth, Filament)
│   ├── Repositories/        # Data access abstraction layer
│   ├── Services/            # Business logic services
│   ├── Support/             # Application support utilities
│   ├── Traits/              # Reusable model/class traits
│   └── Livewire/            # Livewire components (minimal use)
├── database/                # Database infrastructure
│   ├── factories/           # Eloquent model factories for testing
│   ├── migrations/          # Database schema changes
│   ├── seeders/             # Database seeding scripts
│   └── database.sqlite      # Default SQLite database
├── routes/                  # Route definitions
│   ├── api.php              # REST/GraphQL API routes (minimal)
│   ├── web.php              # Web routes (redirects to Filament)
│   └── console.php          # Artisan console commands
├── resources/               # Frontend resources
│   ├── css/                 # Tailwind CSS entry point
│   └── views/               # Blade templates (for emails, etc.)
├── config/                  # Laravel configuration files
│   ├── app.php              # Application settings
│   ├── auth.php             # Authentication providers
│   ├── database.php         # Database connections
│   ├── lighthouse.php       # GraphQL schema config
│   ├── permission.php       # Role-based permissions
│   └── [others]
├── tests/                   # Test suites
│   ├── Unit/                # Unit tests (16 test files)
│   ├── Feature/             # Integration/feature tests (9 test files)
│   └── TestCase.php         # Base test class
├── public/                  # Web server root (CSS, JS bundles)
├── storage/                 # Runtime data (logs, cache)
├── bootstrap/               # Framework bootstrap files
├── vendor/                  # Composer dependencies
├── node_modules/            # NPM dependencies
├── docs/                    # Project documentation
├── graphql/                 # GraphQL schema definition
├── openspec/                # OpenAPI/OpenSpec documentation
├── scripts/                 # Utility scripts
├── .github/                 # GitHub workflows and actions
│   └── get-shit-done/       # GSD framework configuration
├── vite.config.js           # Vite build configuration
├── tailwind.config.js       # Tailwind CSS configuration
├── postcss.config.js        # PostCSS configuration
├── phpunit.xml              # PHPUnit test configuration
├── composer.json            # PHP dependencies manifest
├── package.json             # Node.js dependencies manifest
└── artisan                  # Laravel CLI entry point
```

## Directory Purposes

**app/:**
- Core Laravel application code organized by architectural layer
- 40 Eloquent models representing all domain entities
- Rich business logic in Services layer
- Filament resources for admin interface (36 resources)

**app/Models/:**
- Financial models: `CreditCard`, `Transaction`, `Loan`, `LoanPayment`, `CreditCardCycle`, `CreditCardPayment`, `Subscription`, `Account`, `CreditCardExpense`
- Health models: `HealthRecord`, `BloodTest`, `Medication`, `MedicalRecord`, `Workout`
- Lifestyle models: `Meal`, `Ingredient`, `Recipe`, `Goal`, `Habit`, `JournalEntry`, `Note`
- Travel models: `Trip`, `Flight`, `Hotel`, `Vehicle`, `MaintenanceRecord`
- Administrative models: `User`, `Contact`, `Message`, `Document`, `Notification`, `Event`, `AuditLog`, `Backup`, `UserSetting`, `Project`

**app/Services/:**
- `CreditCardCycleService` (307 lines) - Manages card billing cycles, payment posting, status transitions
- `RevolvingCreditCalculator` (221 lines) - Complex interest and payment calculations for revolving debt
- `CreditCardBalanceService` (177 lines) - Balance updates, expense tracking, payment application
- `LoanScheduleService` (160 lines) - Amortization schedules and payment planning
- `CreditCardExpenseService` (157 lines) - Expense tracking and cycle synchronization
- `FinanceReportService`, `CreditCardPaymentPostingService`, `LoanPaymentPostingService` - Financial reporting and posting logic
- `SubscriptionService` - Subscription renewal tracking and notifications

**app/Filament/:**
- 36 admin panel resources organized by domain (Accounts, CreditCards, Loans, Transactions, etc.)
- Each resource has: `Resource.php`, `/Pages/`, `/Schemas/`, `/Tables/`, and optionally `/RelationManagers/`
- 14 dashboard widgets for analytics and insights
- 2 custom pages: `Dashboard.php`, `FinanceReport.php` (326 lines - large and complex)

**app/Policies/:**
- Authorization logic for 11 models
- Pattern: User ID matching on `view`, `update`, `delete`, `restore` actions
- Implemented for: User, Account, CreditCard, CreditCardCycle, CreditCardPayment, Loan, LoanPayment, Subscription, Transaction, TransactionCategory, TransactionType

**app/Observers/:**
- Lifecycle hooks for model events: `created`, `updated`, `deleted`
- Used by: CreditCardCycleObserver, CreditCardExpenseObserver, CreditCardPaymentObserver, LoanPaymentObserver, SubscriptionObserver, TransactionObserver

**app/Enums/:**
- 30+ type-safe enums for domain values
- Examples: `CreditCardType`, `CreditCardStatus`, `InterestCalculationMethod`, `JournalMood`, `HabitFrequency`

**routes/:**
- `web.php` - Single route: `GET /` redirects to `filament.admin.auth.login`
- `api.php` - Empty, configured for API middleware and rate limiting
- `console.php` - Artisan command scheduling and registration

**database/:**
- 63 migrations tracked in `/migrations/` directory
- 8 factories for testing: User, Account, CreditCard, CreditCardCycle, CreditCardExpense, Loan, Subscription, Transaction
- 12 seeders for database population
- SQLite database at `database/database.sqlite` for development

**config/:**
- `lighthouse.php` - GraphQL schema config, route `/graphql`, disabled in production
- `permission.php` - Spatie permissions configuration
- Standard Laravel configs: app, auth, cache, database, filesystems, logging, mail, queue, session

**tests/:**
- Unit tests (16 files) - Service logic, calculations, edge cases
- Feature tests (9 files) - Module functionality, authorization, integration
- Test categories: Credit card lifecycle, loan operations, authorization, widgets, settings

## Key File Locations

**Entry Points:**
- `artisan` - Laravel CLI binary
- `public/index.php` - Web entry point (served by Vite in dev)
- `vite.config.js` - Asset build configuration

**Configuration:**
- `.env.example` - Environment template (SQLite, database driver config, mail, queue)
- `composer.json` - PHP 8.2+, Laravel 12, Filament 4, Lighthouse GraphQL 6.65
- `package.json` - Vite 7, Tailwind CSS 3, PostCSS, Laravel Vite Plugin
- `phpunit.xml` - Test runner config, in-memory SQLite for tests

**Core Logic:**
- `app/Services/` - Business logic extracted from models
- `app/Models/` - Data layer with relationships
- `app/Filament/Resources/` - Admin CRUD interface
- `app/Observers/` - Event-driven side effects
- `app/Policies/` - Authorization rules

**Testing:**
- `tests/Unit/` - Service and calculation tests
- `tests/Feature/` - Integration and authorization tests
- `tests/TestCase.php` - Base test class with helper methods
- `database/factories/` - Test data builders

## Naming Conventions

**Files:**
- Models: PascalCase, singular (e.g., `CreditCard.php`, `User.php`)
- Services: PascalCase with "Service" suffix (e.g., `CreditCardCycleService.php`)
- Policies: PascalCase with "Policy" suffix (e.g., `CreditCardPolicy.php`)
- Controllers: PascalCase with "Controller" suffix (rarely used, mostly Filament)
- Enums: PascalCase with ".php" extension (e.g., `CreditCardStatus.php`)
- Observers: PascalCase with "Observer" suffix (e.g., `CreditCardCycleObserver.php`)
- Tests: PascalCase with "Test" suffix (e.g., `CreditCardCycleServiceTest.php`)

**Directories:**
- Plural for collections: `Models/`, `Services/`, `Policies/`, `Observers/`, `Enums/`
- Nested under Filament: `/Resources/{Domain}/Pages/`, `/Resources/{Domain}/Schemas/`, `/Resources/{Domain}/Tables/`, `/Resources/{Domain}/RelationManagers/`
- Tests mirror app structure: `tests/Unit/`, `tests/Feature/`

**Classes:**
- Models extend `Illuminate\Database\Eloquent\Model`
- Services are concrete classes with dependency injection in constructor
- Policies follow pattern: `viewAny()`, `view()`, `create()`, `update()`, `delete()`, `restore()`, `forceDelete()`
- Observers implement hooks: `created()`, `updated()`, `deleted()`

## Where to Add New Code

**New Feature (Finance Domain Example):**
- Primary code: `app/Services/NewFeatureService.php`
- Model: `app/Models/NewFeatureModel.php`
- Authorization: `app/Policies/NewFeatureModelPolicy.php`
- Events: `app/Observers/NewFeatureModelObserver.php`
- Filament UI: `app/Filament/Resources/NewFeatures/NewFeatureResource.php` with Pages/, Schemas/, Tables/
- Tests: `tests/Unit/NewFeatureServiceTest.php` + `tests/Feature/NewFeatureIntegrationTest.php`

**New Component/Module:**
- If domain-specific: Create new directory under appropriate domain (e.g., `app/Finance/`, `app/Health/`)
- If cross-cutting: Add to `app/Services/` or `app/Support/`
- Always pair with Policy if entity is user-scoped

**Utilities:**
- Shared helpers: `app/Support/Helpers/` (create if needed)
- Traits: `app/Traits/` for reusable model/class concerns (e.g., `HasUserScoping`)
- Constants/Enums: `app/Enums/` for type-safe values
- Calculations: `app/Services/` for complex logic (e.g., `RevolvingCreditCalculator`)

**Middleware:**
- API middleware: `app/Http/Middleware/` (currently: `ApiRateLimitMiddleware`, `CheckModuleEnabled`)
- Register in `app/Providers/AppServiceProvider.php` or route groups

**Jobs/Queue:**
- Long-running operations: `app/Jobs/GenerateLoanPaymentsJob.php` (example exists)
- Configure in `routes/console.php` for scheduling
- Queue connection: Database-backed (configured in `.env`)

## Special Directories

**vendor/:**
- Generated by Composer
- Not committed to git
- Key dependencies: `laravel/framework`, `filament/filament`, `nuwave/lighthouse`, `spatie/laravel-permission`

**node_modules/:**
- Generated by npm
- Not committed to git
- Key dev dependencies: Vite, Tailwind, Laravel Vite Plugin

**storage/:**
- Runtime cache, logs, sessions
- Git-ignored
- Sub-directories: `logs/`, `framework/`, `app/`

**public/:**
- Web-accessible assets (CSS, JS compiled by Vite)
- Vite manifest: `public/build/manifest.json`
- Entry point: `public/index.php`

**database/database.sqlite:**
- SQLite development database
- Git-ignored (use migrations for schema)
- Reset via `php artisan migrate:fresh --seed`

**resources/css/ and resources/views/:**
- Tailwind CSS: `resources/css/app.css`
- Blade templates: `resources/views/` (mostly for emails)
- Most UI is in Filament resources, not traditional Blade views

**.github/get-shit-done/:**
- GSD framework configuration
- Custom commands and templates for workflow automation
- Not part of application logic

---

*Structure analysis: 2024-12-19*
