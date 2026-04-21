# Testing Patterns

**Analysis Date:** 2025-04-22

## Test Framework

**Runner:**
- PHPUnit 11.5.3 (`phpunit/phpunit: ^11.5.3` in composer.json)
- Config: `phpunit.xml`
- Configured with two test suites: Unit and Feature (separate directories)

**Assertion Library:**
- Built-in PHPUnit assertions
- `Illuminate\Foundation\Testing\TestCase` extends PHPUnit\Framework\TestCase
- Custom Eloquent assertions available via RefreshDatabase trait

**Run Commands:**
```bash
composer test                           # Run all tests
php artisan test                        # Direct Artisan test runner
php artisan test tests/Unit             # Run only unit tests
php artisan test tests/Feature          # Run only feature tests
```

Command sequence in composer script:
```bash
@php artisan config:clear --ansi       # Clear config cache before testing
@php artisan test                      # Run all tests with Artisan
```

## Test File Organization

**Location:**
- Unit tests: `tests/Unit/` directory
- Feature tests: `tests/Feature/` directory
- Base TestCase: `tests/TestCase.php` (extends Illuminate\Foundation\Testing\TestCase)

**Naming:**
- Pattern: `ClassName` + `Test` suffix (e.g., `CreditCardDailyBalanceTest.php`)
- Reflects the component/service being tested
- Co-located tests: No - separate directory structure from source

**Structure:**
```
tests/
├── Unit/
│   ├── CreditCardDailyBalanceTest.php
│   ├── RevolvingCreditCalculatorTest.php
│   ├── CreditCardBalanceServiceTest.php
│   ├── Models/
│   │   └── MigrationTest.php
│   └── ExampleTest.php
├── Feature/
│   ├── CreditCardExpenseIntegrationTest.php
│   ├── CreditCardLifecycleIntegrationTest.php
│   ├── LoanAuthorizationTest.php
│   ├── AccountAuthorizationTest.php
│   ├── TransactionAuthorizationTest.php
│   ├── Filament/
│   │   └── Widgets/
│   │       └── DashboardWidgetsTest.php
│   ├── Settings/
│   │   └── SettingsModuleTest.php
│   └── ExampleTest.php
└── TestCase.php
```

## Test Structure

**Suite Organization:**
```php
<?php

namespace Tests\Unit;

use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardDailyBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function calculates_daily_balances_from_expenses(): void
    {
        // Arrange: Set up test fixtures
        $calculator = new RevolvingCreditCalculator();
        
        $card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'current_balance' => 100,
        ]);

        // Act: Execute the system under test
        $dailyBalances = $calculator->calculateDailyBalances($cycle);

        // Assert: Verify the outcome
        $this->assertCount(10, $dailyBalances);
        $this->assertSame(100.0, $dailyBalances['2026-03-01']);
    }
}
```

**Patterns:**
- Setup pattern: `@test` PHPDoc annotation (preferred) or `test_` prefix for test method declaration
- Teardown pattern: Automatic via `RefreshDatabase` trait (rolls back database transactions after each test)
- Assertion pattern: Direct PHPUnit assertions chained in test body

**Test Traits:**
- `RefreshDatabase` (102 test files use this): Resets database to initial state after each test
- Most unit tests that touch the database use `RefreshDatabase`

## Mocking

**Framework:** 
- Mockery (`mockery/mockery: ^1.6` in composer.json)
- No explicit mocking patterns found in sample test files
- Services are instantiated directly, not mocked

**Patterns:**
- Direct instantiation preferred for unit tests:
  ```php
  $calculator = new RevolvingCreditCalculator();
  $result = $calculator->calculateDailyBalances($cycle);
  ```
- Service dependencies resolved via container in `setUp()`:
  ```php
  protected function setUp(): void
  {
      parent::setUp();
      $this->service = app(CreditCardBalanceService::class);
  }
  ```
- No explicit mock creation observed; Eloquent factories used for test data

**What to Mock:**
- External services (not present in this codebase)
- Time-dependent operations (using `Carbon::setTestNow()`)

**What NOT to Mock:**
- Domain services (test with real instances)
- Database (use RefreshDatabase trait for isolation)
- Models (use factories and create() calls)

## Fixtures and Factories

**Test Data:**
```php
// Factory usage in tests
$card = CreditCard::factory()->create([
    'type' => CreditCardType::REVOLVING,
    'fixed_payment' => 250,
    'interest_rate' => 14,
    'current_balance' => 100,
]);

// Factory with state methods
$card = CreditCard::factory()->charge()->create();
$card = CreditCard::factory()->unlimited()->create();

// Manual model creation without factory
$card = CreditCard::create([
    'user_id' => $account->user_id,
    'account_id' => $account->id,
    'name' => 'Carta Spese',
    'type' => CreditCardType::CHARGE,
    // ... other attributes
]);
```

**Location:**
- Factories: `database/factories/` directory
- Available factories:
  - `CreditCardFactory.php` - with `charge()` and `unlimited()` states
  - `CreditCardCycleFactory.php`
  - `CreditCardExpenseFactory.php`
  - `AccountFactory.php`
  - `UserFactory.php`
  - `TransactionFactory.php`
  - `LoanFactory.php`
  - `SubscriptionFactory.php`

**Factory Example:**
```php
class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => $this->faker->word(),
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 5000.00,
            'fixed_payment' => 250.00,
            'interest_rate' => 12.00,
            'stamp_duty_amount' => 2.00,
            'statement_day' => 20,
            'due_day' => 25,
            'skip_weekends' => false,
            'current_balance' => 0.00,
            'status' => CreditCardStatus::ACTIVE,
            'start_date' => now(),
            'interest_calculation_method' => InterestCalculationMethod::DAILY_BALANCE,
        ];
    }

    public function charge(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => CreditCardType::CHARGE,
                'interest_rate' => 0.00,
                'fixed_payment' => 0.00,
            ];
        });
    }

    public function unlimited(): static
    {
        return $this->state(function (array $attributes) {
            return ['credit_limit' => null];
        });
    }
}
```

## Coverage

**Requirements:** Not explicitly enforced
- No coverage report configuration found in `phpunit.xml`
- No minimum coverage threshold configured
- Coverage reports can be generated with `php artisan test --coverage` but not required

**View Coverage:**
```bash
php artisan test --coverage                     # View coverage report
php artisan test --coverage-html coverage/     # Generate HTML report
```

**Current Status:**
- 110 tests passing (as reported)
- Coverage gaps exist but not tracked by CI/CD

## Test Types

**Unit Tests:**
- Scope: Individual service methods and calculators
- Approach: Test logic in isolation using factories for test data
- Examples: `CreditCardDailyBalanceTest`, `RevolvingCreditCalculatorTest`, `CreditCardBalanceServiceTest`
- Typical test file: 5-15 test methods per class
- Database: Uses RefreshDatabase for full isolation; no shared state between tests

**Integration Tests:**
- Scope: Multi-service workflows and model observer chains
- Approach: Test end-to-end flows (expenses → cycles → payments → transactions)
- Examples: `CreditCardExpenseIntegrationTest`, `CreditCardLifecycleIntegrationTest`
- Key tests:
  - `creating_expense_assigns_cycle_and_updates_total_spent`
  - `charge_cycle_issue_and_payment_sync_everything`
  - `revolving_issue_and_payment_reduce_residual_balance_by_principal`
- Database: Uses RefreshDatabase; tests verify model relationships and observer side effects

**Authorization Tests (Feature):**
- Scope: Policy enforcement and access control
- Approach: Direct policy testing or HTTP request testing
- Examples: `LoanAuthorizationTest`, `AccountAuthorizationTest`, `TransactionAuthorizationTest`
- Pattern:
  ```php
  public function test_user_cannot_access_others_loan(): void
  {
      $user = User::factory()->create();
      $otherUser = User::factory()->create();
      $loan = Loan::factory()->create(['user_id' => $otherUser->id]);

      $this->assertFalse((new LoanPolicy())->update($user, $loan));
  }
  ```

**E2E Tests:**
- Not separate category; integration tests serve this purpose
- HTTP-based tests check full request/response cycle

## Common Patterns

**Async Testing:**
- No async testing patterns found
- All tests are synchronous
- Carbon time-freezing used for date-dependent tests:
  ```php
  Carbon::setTestNow(Carbon::parse('2026-03-18'));
  // ... test code ...
  // Time automatically resets after test via RefreshDatabase
  ```

**Error Testing:**
```php
// Exception expectation pattern
public function add_expense_respects_credit_limit()
{
    $card = CreditCard::factory()->create([
        'current_balance' => 3500.00,
        'credit_limit' => 4000.00,
    ]);

    $this->expectException(ValidationException::class);
    $this->service->addExpense($card, 600.00);
}

// Assertion pattern for success/failure
public function test_user_cannot_access_others_loan(): void
{
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $loan = Loan::factory()->create(['user_id' => $otherUser->id]);

    $this->assertFalse((new LoanPolicy())->update($user, $loan));
}
```

**Database Assertions:**
```php
// Eloquent refresh pattern
$expense->refresh();
$this->assertNotNull($expense->credit_card_cycle_id);

// Fresh query pattern
$this->assertEquals(600.00, $card->fresh()->current_balance);

// Collection assertions
$this->assertCount(10, $dailyBalances);

// Model state assertions (in MigrationTest)
$this->assertSoftDeleted('properties', ['id' => $property->id]);
```

**Numeric Precision Testing:**
```php
// For float comparisons with delta tolerance
$this->assertEqualsWithDelta(0.69, $interest, 0.01);

// For exact decimal matches
$this->assertSame(100.0, $dailyBalances['2026-03-01']);

// For float type conversion
$this->assertEquals(642.00, $dailyBalances['2026-03-01']);
```

## Test Configuration (phpunit.xml)

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
```

**Key Configurations:**
- Database: SQLite in-memory (`:memory:`) for fast test isolation
- Mail: Array driver (collects sent mail without sending)
- Queue: Sync driver (executes jobs synchronously)
- Cache: Array store (in-memory, cleared between tests)
- Broadcasting: Disabled
- Pulse/Telescope: Development monitoring tools disabled in tests

## Test Statistics

- Total test files: 24 (13 Unit, 11 Feature)
- Total test methods: 110 passing
- Test annotation style: ~93% use `@test` PHPDoc, ~7% use `test_` prefix
- Database-dependent tests: ~49 files use RefreshDatabase trait
- Longest test class: `RevolvingCreditCalculatorTest.php` (300+ lines, 12+ test methods)
- Longest integration test: `CreditCardLifecycleIntegrationTest.php` (250+ lines)

---

*Testing analysis: 2025-04-22*
