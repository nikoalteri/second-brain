# Coding Conventions

**Analysis Date:** 2026-03-23

## Naming Patterns

**Files:**
- Classes: PascalCase (e.g., `LoanScheduleService`, `CreditCardCycle`, `CreateLoan`)
- Tests: `{Subject}Test.php` (e.g., `LoanScheduleServiceTest`)
- Migrations: Timestamp + snake_case action (e.g., `2026_03_17_091146_create_loans_table.php`)

**Functions/Methods:**
- camelCase for all methods (e.g., `generatePayments()`, `syncLoan()`, `calculateInterest()`)
- Action verbs first (get, create, update, delete, generate, calculate, sync, adjust)
- Private methods prefixed with underscore optional but not used (just private visibility)

**Variables:**
- camelCase (e.g., `$monthlyPayment`, `$dueDate`, `$existingDates`)
- Constants in UPPER_SNAKE_CASE (within Enums and classes)
- $this->property for model attributes

**Types:**
- Enums for status values: `LoanStatus::ACTIVE`, `PaymentStatus::PENDING`
- Type hints on all parameters and return types (PHP 8.2+ strict typing)
- Collections returned as proper types: `Collection`, `Paginate`, array

**Database:**
- Table names: snake_case plural (loans, credit_cards, loan_payments)
- Column names: snake_case (due_date, monthly_payment, skip_weekends)
- Foreign keys: `{entity}_id` pattern (loan_id, user_id)
- Boolean columns: `is_active`, `skip_weekends` (is_/has_ prefix)

## Code Style

**Formatting:**
- PSR-12 (PHP) with Larvel conventions
- Spaces: 4-space indentation
- Line length: No hard limit, but keep under 120 characters where practical

**Linting:**
- Laravel Pint (PHP linter) - configured via composer scripts
- No external linter configured for JavaScript/Vue
- Run: `composer run pint` (or `php artisan pint`)

**Comments:**
- Minimal comments - code should be self-documenting
- Use comments only for "why", not "what"
- PHPDoc blocks on public methods with @param, @return, @throws

## Import Organization

**Order:**
1. PHP built-in classes (namespace declarations first)
2. Laravel framework imports (Illuminate\*)
3. Package imports (Spatie\*, Nuwave\*, etc.)
4. Application imports (App\*)
5. Traits last (use statements within namespace declaration)

**Example from codebase:**
```php
namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\Concerns\HasWorkdayCalculation;

class LoanScheduleService
{
    use HasWorkdayCalculation;
    // ...
}
```

**Path Aliases:**
- None detected in jsconfig.json (uses standard relative imports for JS)
- Laravel uses PSR-4 autoloading: `App\\` → `app/`, `Database\\` → `database/`, `Tests\\` → `tests/`

## Error Handling

**Patterns:**
- Validation at entry point (Form Requests validate HTTP input)
- Service methods assume valid input from controllers
- Database transactions wrap multi-step operations: `DB::transaction(function() { ... })`
- Eloquent exceptions caught at controller/resource level, returned as validation errors
- GraphQL @rules directive validates types before resolver execution

**Exception Handling:**
- No custom exception classes observed - uses Laravel's built-in exceptions
- Filament resources catch and display Eloquent exceptions as notifications

## Logging

**Framework:** Laravel default logging with stack driver (logs to `storage/logs/`)

**Patterns:**
- Optional pail integration (`laravel/pail`) for CLI log viewing
- No explicit logging calls observed in services; rely on framework defaults
- Query logging in development: `DB::listen()` callbacks
- For debugging: `Log::info()` or `dd()` for local development

## Comments

**When to Comment:**
- Only for non-obvious domain logic (e.g., Italian holiday calculation, workday adjustment)
- Algorithm explanation if not immediately clear from code
- Avoid stating the obvious ("increment counter", "check if active")

**Example pattern observed:**
```php
// Adjust to next workday if due date falls on weekend/holiday
$dueDate = $this->adjustToWorkday($dueDate, (bool) $loan->skip_weekends);
```

**JSDoc/TSDoc:**
- Not observed in JavaScript/Vue files
- PHPDoc used on public methods with @param and @return types

## Function Design

**Size:**
- Aim for small, focused functions (single responsibility)
- Service methods typically 20-50 lines (some exceptions like CreditCardCycleService at 334 lines - complex domain)
- Large methods indicate potential refactoring opportunity

**Parameters:**
- Type hinted (PHP 8.2+ strict types)
- Max 3-4 parameters; use dependency injection for shared services
- Optional boolean flags used for behavior toggles (e.g., `$onlyMissing = true`)

**Return Values:**
- Explicit return types (void, Model, Collection, bool, array, int)
- No implicit null returns - explicitly return null or throw exception
- Service methods return modified models or Collections

## Module Design

**Exports:**
- Each class has single responsibility
- Services export public methods (no private contracts)
- Models export public relations, casts, and attributes

**Barrel Files:**
- Not used (no index.php or __init__.php re-exports)
- Direct imports from specific classes

## Database Interactions

**Pattern:** Eloquent ORM exclusively (no raw SQL)

**Common patterns:**
```php
// With transactions for multi-step operations
DB::transaction(function () use ($loan, $onlyMissing) {
    if (! $onlyMissing) {
        $loan->payments()->where('status', 'pending')->delete();
    }
    // ... create new payments
});

// With Model::withoutEvents() to suppress observers during seeding
LoanPayment::withoutEvents(function () use ($loan) {
    $loan->payments()->create([...]);
});

// Scoped queries with local scopes
$loan->payments()->where('status', 'paid')->count();

// Bulk operations for performance
Model::whereIn('id', $ids)->update(['status' => 'processed']);
```

**Observers:**
- Model lifecycle hooks (created, updating, deleted) used sparingly
- Example: LoanObserver dispatches GenerateLoanPaymentsJob on creation/update

## Filament Resources

**Pattern:**
- Resources define CRUD pages, tables, and forms declaratively
- Schemas separate into form fields (CreateForm, EditForm) and table columns
- Policies checked on resource pages; unauthorized users see 403
- Relation managers handle nested data (e.g., Loan Payments as relation of Loan)

**Example location:** `app/Filament/Resources/Loans/`

## GraphQL Conventions

**Schema Location:** `graphql/schema.graphql`

**Directives used:**
- `@find` - Single model lookup by ID or attribute
- `@paginate` - Paginated model list with cursor/offset
- `@eq` - Equality filter on query parameter
- `@where` - WHERE clause with operator (like for LIKE)
- `@rules` - Validation rules applied before resolver
- Custom scalar: `DateTime` for timestamps

**Example:**
```graphql
user(
  id: ID @eq @rules(apply: ["prohibits:email", "required_without:email"])
  email: String @eq @rules(apply: ["email"])
): User @find
```

## Testing Conventions

**File Structure:**
- `tests/Unit/` - Service and model unit tests with RefreshDatabase
- `tests/Feature/` - Integration tests exercising HTTP/job workflows
- Test factories in `database/factories/`

**Pattern:**
```php
class LoanScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_payments_for_a_loan()
    {
        $loan = Loan::factory()->create([...]);
        $service = new LoanScheduleService();
        $service->generate($loan);
        
        $this->assertCount(3, $loan->payments);
    }
}
```

**Patterns:**
- Factory methods for test data: `Model::factory()->create([])`
- RefreshDatabase trait rolls back each test
- Assertion library: PHPUnit assertions
- Mocking: Mockery for external dependencies
- Setup in test methods (no setUp fixture re-use)

---

*Convention analysis: 2026-03-23*
