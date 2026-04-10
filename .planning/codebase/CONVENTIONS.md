# Coding Conventions

**Analysis Date:** 2024-12-19

## Naming Patterns

**Files:**
- Models: `PascalCase`, singular → `CreditCard.php`, `User.php`, `Transaction.php`
- Services: `PascalCase` + "Service" suffix → `CreditCardCycleService.php`, `LoanScheduleService.php`
- Policies: `PascalCase` + "Policy" suffix → `CreditCardPolicy.php`, `UserPolicy.php`
- Observers: `PascalCase` + "Observer" suffix → `CreditCardCycleObserver.php`
- Enums: `PascalCase`, singular → `CreditCardStatus.php`, `JournalMood.php`
- Controllers: `PascalCase` + "Controller" suffix (rarely used)
- Requests: `PascalCase` + "Request" suffix → `StoreLoanRequest.php`, `StoreTransactionRequest.php`
- Jobs: `PascalCase` + "Job" suffix → `GenerateLoanPaymentsJob.php`
- Traits: `PascalCase`, starts with "Has" or "Is" → `HasUserScoping.php`
- Repositories: `PascalCase` + "Repository" suffix → `LoanRepository.php`

**Classes (PHP):**
- PSR-12 compliant
- Namespace: `App\Models`, `App\Services`, `App\Policies`, etc.
- Class names match file names exactly
- Properties: `camelCase`
- Methods: `camelCase`
- Constants: `UPPER_SNAKE_CASE`

**Functions & Methods:**
- `camelCase` for instance/static methods
- `camelCase` for function names
- Private methods prefixed with underscore only if needed (not common)
- Helper functions: Flat namespace, `camelCase`
- Eloquent relationships: `camelCase`, singular or plural matching return type
  - Example: `$model->creditCards()` returns HasMany
  - Example: `$model->user()` returns BelongsTo

**Variables:**
- `camelCase` for all variables
- Short, descriptive names
- Boolean variables prefixed with `is`, `has`, `should` → `$isActive`, `$hasBalance`, `$shouldProcess`
- Loop variables: `$i`, `$item`, `$key`, `$value` for simple loops; descriptive for domain loops
- Collections/arrays: Plural names → `$accounts`, `$transactions`, `$payments`

**Constants & Enums:**
- `UPPER_SNAKE_CASE` for class constants
- Enum backing values: lowercase strings or integers → `'revolving'`, `'charge'`, `1`, `2`
- Enum case names: `PascalCase` → `CreditCardType::REVOLVING`, `CreditCardStatus::ACTIVE`

**Directories:**
- Plural for collections: `Models/`, `Services/`, `Policies/`, `Observers/`, `Enums/`
- Singular for concepts: `Http/`, `Jobs/`, `Traits/`, `Support/`
- Domain-grouped under Filament: `Resources/`, `Resources/{Domain}/Pages/`, `Resources/{Domain}/Schemas/`, `Resources/{Domain}/Tables/`
- Test structure mirrors app: `tests/Unit/`, `tests/Feature/`

## Code Style

**Formatting:**
- Tool: Laravel Pint (code style fixer)
- Standard: PSR-12 (PHP Standard Recommendation)
- Indent: 4 spaces (never tabs)
- Line length: 120 characters (soft limit, enforced by EditorConfig)
- Line endings: LF (Unix style)
- Final newline: Required in all files
- Trailing whitespace: Removed

**Linting:**
- Tool: Laravel Pint (included in dev dependencies)
- Run: `php artisan pint` to fix style issues
- No ESLint or Prettier for JavaScript (JavaScript is minimal, mostly CSS)
- Code quality: PHPUnit for test coverage

**Spacing:**
- Between class members: 1 blank line
- Between method groups: 1 blank line
- Inside methods: Sparse use of blank lines to group logical sections
- Array spacing: No space after opening bracket, no space before closing bracket
- Function parameters: `function name($param1, $param2)` - no extra spaces
- Operators: Space around binary operators (`$a + $b`), no space around unary (`-$x`, `!$bool`)

## Import Organization

**Order:**
1. Namespace declaration
2. Blank line
3. Use statements grouped by vendor/package
4. Blank line
5. Class declaration

**Pattern:**
```php
<?php

namespace App\Services;

use App\Enums\CreditCardStatus;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditCardCycleService
```

**Grouping:**
- App\ imports first (application code)
- Third-party Illuminate\ imports next (Laravel framework)
- Other vendor imports
- Alphabetically within each group

**Path Aliases:**
- No aliases configured in `jsconfig.json` or `composer.json`
- Full namespace paths used throughout

## Error Handling

**Patterns:**
- **Authorization**: Policies return boolean, Laravel throws `AuthorizationException` automatically
- **Validation**: Form requests throw `ValidationException` with field-level messages
- **Database**: Services wrap multi-step operations in `DB::transaction()` for atomicity
- **Silent failures**: Observers do not throw exceptions (logged instead)
- **Method contracts**: Type hints on parameters and return types enforce contracts
  
**Example:**
```php
public function applyPrincipalPayment(CreditCard $card, float $principalAmount): float
{
    $this->validateCreditLimit($card, $principalAmount);
    // Operation...
    return $newBalance;
}

private function validateCreditLimit(CreditCard $card, float $amount): void
{
    if ($card->credit_limit !== null && $amount > $card->available_credit) {
        throw new InvalidArgumentException('Amount exceeds available credit');
    }
}
```

## Logging

**Framework:** Laravel's built-in logging (Monolog)

**Patterns:**
- Lever context: `Log::info('message', ['key' => 'value'])`
- Error logging: `Log::error('error', ['exception' => $e])`
- Observers log to AuditLog model for domain-specific tracking
- Services may log complex operations: `Log::debug()` for development, `Log::info()` for important events
- Config: `config/logging.php` - stack driver (single channel in dev)

**Example:**
```php
Log::info('Credit card cycle issued', [
    'cycle_id' => $cycle->id,
    'card_id' => $cycle->credit_card_id,
    'total_due' => $cycle->total_due,
]);
```

## Comments

**When to Comment:**
- Complex business logic: Interest calculation, payment breakdown
- Non-obvious algorithms: Revolving credit calculator
- Configuration rationale: Why a specific Enum or relationship
- Deprecation: `@deprecated Use X instead, kept for backward compatibility`
- NOT for obvious code: `// increment counter` unnecessary

**JSDoc/TSDoc/PHPDoc:**
- Methods in services have PHPDoc with `@param`, `@return`, `@throws` tags
- Example from `CreditCardBalanceService`:
```php
/**
 * @param CreditCard $card
 * @param float $amount Expense amount (positive)
 * @return float New current balance
 */
public function addExpense(CreditCard $card, float $amount): float
```

- Models rarely documented (self-explanatory via type hints)
- Observers documented if logic is non-obvious
- No inline comments preferred (code should be clear without them)

## Function Design

**Size:** 
- Target: 10-30 lines per method
- Maximum: 50 lines before extracting
- Services: 80-300 lines total (9 services under 200 lines, 2 over 200)
- Complex services: `CreditCardCycleService` (307 lines) legitimately large due to domain complexity

**Parameters:** 
- Maximum 3-4 positional parameters before considering object/array
- Type hints: Always used (PHP 8.2+ requirement)
- Return type: Always declared
- Nullable types: Used sparingly, `?Type` for true optionals

**Return Values:**
- Single type: `public function method(): ReturnType`
- Mixed returns: Rare, usually array with type specification
- Void: Used for side-effect methods
- Example good pattern:
```php
public function issueCycle(CreditCardCycle $cycle): bool
public function calculateRevolvingPaymentBreakdown(CreditCard $card, float $currentBalance): array
public function addExpense(CreditCard $card, float $amount): float
```

**Constructor Injection:**
```php
public function __construct(
    ?RevolvingCreditCalculator $calculator = null,
    ?CreditCardBalanceService $balanceService = null
) {
    $this->calculator = $calculator ?? app(RevolvingCreditCalculator::class);
    $this->balanceService = $balanceService ?? app(CreditCardBalanceService::class);
}
```
- Optional dependencies with null coalescing to container fallback
- Allows testing without service container

## Module Design

**Exports:**
- Services: Single public class per file
- Models: Eloquent model only (relationships defined inside)
- Policies: Single policy per class/model pair
- Observers: Single observer per model
- No barrel exports (no index.php re-exporting)

**Barrel Files:**
- Not used in codebase
- Each import explicit from source file

**Model Structure:**
```php
class CreditCard extends Model
{
    // 1. Traits
    use HasFactory, SoftDeletes;
    
    // 2. Appended attributes
    protected $appends = [];
    
    // 3. Fillable
    protected $fillable = [];
    
    // 4. Casts
    protected $casts = [];
    
    // 5. Relationships
    public function user(): BelongsTo { }
    public function cycles(): HasMany { }
    
    // 6. Attributes (accessors)
    public function getAvailableCreditAttribute(): ?float { }
    
    // 7. Scopes (if any)
    public function scopeActive(Builder $query) { }
}
```

## Database Naming

**Tables:**
- Plural: `users`, `credit_cards`, `transactions`
- Snake case: `credit_card_cycles`, `transaction_categories`
- Singular model to plural table (automatic in Eloquent)

**Columns:**
- Snake case: `user_id`, `current_balance`, `is_active`
- Foreign keys: `{model}_id` → `user_id`, `credit_card_id`
- Timestamps: `created_at`, `updated_at` (added automatically)
- Soft deletes: `deleted_at` column (SoftDeletes trait)
- Boolean: `is_*` prefix → `is_active`, `is_unlimited`
- Amounts: `decimal:2` cast for financial data

**Relationships:**
- Implicit naming: `user()` method creates Foreign key constraint `user_id`
- Custom foreign keys: Declared explicitly → `belongsTo(Account::class, 'to_account_id')`

## Test Naming

**Test Files:**
- Pattern: `{Subject}Test.php`
- Location: `tests/Unit/` or `tests/Feature/`
- Examples: `CreditCardCycleServiceTest.php`, `CreditCardLifecycleIntegrationTest.php`

**Test Methods:**
- Pattern: `test_{scenario}` or `/** @test */ public function {scenario}()`
- Descriptive names: `test_revolving_breakdown_with_14_percent_rate_matches_bank_statement`
- Arrange-Act-Assert (AAA) pattern: Setup, execute, verify

## API/Endpoint Naming

**Route names:**
- Plural resource names: `routes/api.php` for REST endpoints
- RESTful: `GET /items`, `POST /items`, `GET /items/{id}`, `PUT /items/{id}`, `DELETE /items/{id}`
- Nested: `GET /users/{id}/accounts`, `GET /credit-cards/{id}/cycles`
- Filament uses `filament.{panel}.resources.{resource}.index` naming convention

## Eloquent Model Conventions

**Relationships:**
- Method name matches logical concept: `$user->creditCards()` for HasMany
- Eager loading: `with()` in queries to avoid N+1
- Type hints on relationships: `public function creditCards(): HasMany`

**Accessors/Mutators:**
- Appended attributes via `$appends` array
- Getters: `public function getAttributeAttribute(): Type`
- Never setters used (use Mutator instead)
- Computed attributes: `available_credit`, `is_unlimited`

**Scopes:**
- Local scopes: `public function scopeName(Builder $query)`
- Global scopes via traits: `HasUserScoping` adds user filtering
- Usage: `Model::name()->get()` or `$query->name()`

## Filament Conventions

**Resource Structure:**
```
Resources/
├── {Resource}Resource.php
├── Pages/
│   ├── List{Resource}s.php
│   ├── Create{Resource}.php
│   ├── Edit{Resource}.php
├── Schemas/
│   └── {Resource}Form.php
├── Tables/
│   └── {Resource}sTable.php
└── RelationManagers/
    └── {Relation}RelationManager.php
```

**Form Schemas:**
- Single form definition in `Schemas/` directory
- Used by Create and Edit pages
- Field definitions: `TextInput`, `Select`, `DatePicker`, etc.

**Table Definitions:**
- Single table definition in `Tables/` directory
- Columns defined in fluent API
- Actions: Edit, Delete buttons built-in

**Pagination:**
- Default: 10 items per page
- Configurable per resource

---

*Conventions analysis: 2024-12-19*
