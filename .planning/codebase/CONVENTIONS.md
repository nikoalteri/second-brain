# Coding Conventions

**Analysis Date:** 2025-04-22

## Naming Patterns

**Files:**
- Classes: PascalCase (e.g., `CreditCardExpense.php`, `RevolvingCreditCalculator.php`)
- Tests: PascalCase ending with `Test` (e.g., `CreditCardDailyBalanceTest.php`)
- Interfaces/Contracts: No interfaces found in codebase; use standard naming
- Enums: PascalCase (e.g., `CreditCardType.php`, `CreditCardStatus.php`)

**Functions/Methods:**
- camelCase for all public/private methods (e.g., `calculateDailyBalances()`, `issueCycle()`, `ensureCurrentMonthCycle()`)
- Private helper methods: camelCase prefix with `_` not used; all private methods use standard camelCase (e.g., `validateExpenseChange()`)
- Test methods: Two conventions observed
  - Majority (102 tests): `@test` PHPDoc annotation with camelCase `public function calculateDailyBalances()` style
  - Minority (8 tests): `public function test_` prefix style (e.g., `test_user_cannot_access_others_loan()`)
  - Preferred pattern: `@test` annotation with descriptive camelCase names matching test intent

**Variables:**
- Properties: camelCase (e.g., `$creditCardId`, `$currentBalance`, `$dailyBalances`)
- Constants: UPPER_SNAKE_CASE (standard PHP convention, not heavily used in this codebase)
- Collection variables: descriptive plural forms (e.g., `$dailyBalances`, `$expensesByDate`, `$originalPointers`)

**Types:**
- Fully qualified: `\App\Models\CreditCard`, `\App\Services\RevolvingCreditCalculator`
- In namespaced context: unqualified imports via `use` statements (e.g., `use App\Models\CreditCard`)
- Array types: documented in comments where PHPDoc is used (e.g., `@var array<int, array{card_id:int|null,cycle_id:int|null,amount:float|null}>`)

## Code Style

**Formatting:**
- Indentation: 4 spaces (configured in `.editorconfig`)
- Line endings: LF (configured in `.editorconfig`)
- Final newlines: Required (configured in `.editorconfig`)
- Trailing whitespace: Removed (configured in `.editorconfig`)
- Charset: UTF-8

**Linting:**
- Tool: Laravel Pint (`laravel/pint: ^1.24` in composer.json)
- Run command: `composer test` which includes `@php artisan config:clear --ansi` and `@php artisan test`
- No explicit `.pint.json` config found; uses Laravel defaults (PSR-12 standard)

**Formatting Tool:**
- No explicit Prettier/code formatter config for PHP
- Rely on Pint (PSR-12) for PHP code style

## Import Organization

**Order:**
1. PHP declarations (none found in this codebase - `declare(strict_types=1)` not used)
2. Namespace declaration
3. `use` statements (imports) organized alphabetically when multiple
4. No blank line between namespace and first use statement

**Examples from codebase:**
```php
<?php

namespace App\Services;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardCycle;
use App\Support\Concerns\HasWorkdayCalculation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
```

**Path Aliases:**
- Standard Laravel PSR-4 autoloading:
  - `App\` → `app/`
  - `Database\Factories\` → `database/factories/`
  - `Database\Seeders\` → `database/seeders/`
  - `Tests\` → `tests/`

## Error Handling

**Patterns:**
- Custom exceptions: Validation exceptions used (`Illuminate\Validation\ValidationException`)
- Services throw exceptions for business logic violations
- Example from `CreditCardBalanceService`:
  ```php
  if ($newBalance > $creditLimit && !$card->is_unlimited) {
      throw ValidationException::withMessages([
          'current_balance' => 'Credit limit exceeded'
      ]);
  }
  ```
- Feature tests verify exception handling with `$this->expectException(ValidationException::class)`
- Unit tests validate specific error states rather than exception throwing

**Transaction Management:**
- Database transactions wrapped with `DB::transaction()` for complex operations
- Example from `CreditCardCycleService::issueCycle()`:
  ```php
  return DB::transaction(function () use ($cycle) {
      // Complex multi-step operation
  });
  ```

## Logging

**Framework:** Laravel built-in logging (no explicit log wrapper found)

**Patterns:**
- No explicit logging observed in services; application uses Laravel facades
- Debug/dump statements actively avoided (no `dd()`, `var_dump()`, `dump()` in source files)
- Production code relies on exception handling and status tracking

## Comments

**When to Comment:**
- Used sparingly; code is self-documenting through clear naming
- Inline comments document complex business logic (e.g., interest calculations)
- Example from `RevolvingCreditCalculator`:
  ```php
  // Convert annual percentage to daily rate
  // 14% annual = 0.14 / 365 daily
  $dailyRate = $annualRatePercent / 100 / 365;
  ```

**JSDoc/PHPDoc:**
- Method documentation includes:
  - Parameter type hints (e.g., `@param CreditCardCycle $cycle`)
  - Return type documentation (e.g., `@return array`)
  - Complex array structures documented (e.g., `@var array<int, array{card_id:int|null,...}>`)
- Example from `RevolvingCreditCalculator`:
  ```php
  /**
   * Calculate daily balances for a cycle
   * 
   * @param CreditCardCycle $cycle
   * @return array Key: date string (Y-m-d), Value: balance at end of day
   */
  public function calculateDailyBalances(CreditCardCycle $cycle): array
  ```

## Function Design

**Size:** 
- Most functions stay within 20-40 lines
- Larger operations (>100 lines) are exceptions - see `CreditCardCycleService::issueCycle()` at ~35 lines
- `RevolvingCreditCalculator::calculatePaymentBreakdown()` ~80 lines for complex calculation

**Parameters:** 
- Maximum 2-3 parameters for most functions
- Complex parameters use model injection (`CreditCard $card`)
- Optional parameters use nullsafe operator with default null: `?Carbon $referenceDate = null`
- No parameter objects/data transfer objects pattern observed

**Return Values:** 
- Explicit return types on all public methods (PHP 8.2+)
- Examples: `: array`, `: bool`, `: float`, `: CreditCardCycle`
- Array returns are keyed with semantic keys (e.g., `daily_balance['2026-03-01']`)

## Module Design

**Exports:**
- Single public class per file (standard PHP PSR-4 convention)
- Repositories provide single-model data access layer
- Services provide business logic layer
- Models provide data layer with relationships and attributes

**Barrel Files:** 
- No barrel file pattern (index.php re-exports) found
- Standard composer autoloading handles all imports

**Architecture Layers:**
1. Models - Data entities with Eloquent relations
2. Repositories - Data access abstraction (optional, used for Loans)
3. Services - Business logic orchestration
4. Observers - Event-driven model lifecycle hooks
5. Traits/Concerns - Shared functionality (`HasWorkdayCalculation`, `HasUserScoping`)

**Dependency Injection:**
- Constructor injection preferred
- Services type-hint dependencies: 
  ```php
  public function __construct(
      private readonly RevolvingCreditCalculator $calculator,
      private readonly CreditCardBalanceService $balanceService
  ) {}
  ```
- Service container resolution in `setUp()` methods for tests: `app(CreditCardBalanceService::class)`

## Code Examples

**Typical Service Method:**
```php
public function issueCycle(CreditCardCycle $cycle): bool
{
    $cycle->loadMissing('creditCard');

    if (! $cycle->creditCard || $cycle->status !== CreditCardCycleStatus::OPEN) {
        return false;
    }

    return DB::transaction(function () use ($cycle) {
        $card = $cycle->creditCard;
        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        $cycle->update([
            'interest_amount' => $breakdown['interest_amount'],
            'principal_amount' => $breakdown['principal_amount'],
            'stamp_duty_amount' => $breakdown['stamp_duty_amount'],
            'total_due' => $breakdown['total_due'],
            'status' => CreditCardCycleStatus::ISSUED,
        ]);

        return true;
    });
}
```

**Typical Model:**
```php
protected $fillable = [
    'user_id',
    'account_id',
    'spent_at',
    'posted_at',
    'amount',
    'description',
    'notes',
];

protected $casts = [
    'spent_at' => 'date',
    'posted_at' => 'date',
    'amount' => 'decimal:2',
];

public function creditCard(): BelongsTo
{
    return $this->belongsTo(CreditCard::class);
}
```

---

*Convention analysis: 2025-04-22*
