# Testing Patterns

**Analysis Date:** 2024-12-19

## Test Framework

**Runner:**
- PHPUnit 11.5.3
- Config: `phpunit.xml` in project root

**Assertion Library:**
- PHPUnit assertions (built-in)
- Mockery 1.6 for mocking objects
- Laravel test helpers: `assertDatabaseHas()`, `assertDatabaseMissing()`, Eloquent assertion methods

**Run Commands:**
```bash
php artisan test                    # Run all tests
php artisan test tests/Unit         # Run unit tests only
php artisan test tests/Feature      # Run feature tests only
php artisan test --filter=TestName  # Run specific test
php artisan test --parallel         # Run tests in parallel
composer test                       # Composer script (runs config:clear then tests)
```

**Coverage:**
```bash
php artisan test --coverage          # Generate coverage report
php artisan test --coverage-html     # HTML coverage report
php artisan test --min-coverage=70   # Enforce minimum coverage
```

## Test File Organization

**Location:**
- Unit tests: `tests/Unit/`
- Feature tests: `tests/Feature/`
- Test files co-located by domain under Feature (not app structure)

**Naming:**
- Pattern: `{Subject}Test.php`
- Examples: `CreditCardCycleServiceTest.php`, `CreditCardLifecycleIntegrationTest.php`

**Structure:**
```
tests/
├── TestCase.php                          # Base test class with helpers
├── Unit/
│   ├── CreditCardBalanceServiceTest.php
│   ├── CreditCardCycleServiceTest.php
│   ├── CreditCardKpiServiceTest.php
│   ├── LoanScheduleServiceTest.php
│   ├── LoanRepositoryTest.php
│   ├── RevolvingCreditCalculatorTest.php
│   ├── SubscriptionServiceTest.php
│   ├── ExampleTest.php
│   └── [12 total unit test files]
├── Feature/
│   ├── CreditCardExpenseIntegrationTest.php
│   ├── CreditCardLifecycleIntegrationTest.php
│   ├── ExampleTest.php
│   ├── LoanAuthorizationTest.php
│   ├── TransactionAuthorizationTest.php
│   ├── AccountAuthorizationTest.php
│   ├── Filament/Widgets/DashboardWidgetsTest.php
│   ├── Health/HealthModuleTest.php
│   ├── Productivity/ProductivityModuleTest.php
│   ├── Relationships/RelationshipsModuleTest.php
│   ├── Settings/SettingsModuleTest.php
│   └── [9 total feature test files]
```

## Test Structure

**Base Class:**
```php
// tests/TestCase.php
class TestCase extends BaseTestCase
{
    use CreatesApplication;
    // Custom helpers available to all tests
}
```

**Unit Test Pattern:**
```php
namespace Tests\Unit;

use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Services\CreditCardCycleService;
use Tests\TestCase;

class CreditCardCycleServiceTest extends TestCase
{
    /** @test */
    public function revolving_breakdown_with_12_percent_rate(): void
    {
        // Arrange
        $service = new CreditCardCycleService();
        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        // Act
        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        // Assert
        $this->assertSame(120.0, $result['interest_amount']);
        $this->assertSame(130.0, $result['principal_amount']);
        $this->assertFalse($result['invalid_installment']);
    }
}
```

**Feature Test Pattern (with Database):**
```php
namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;  // Refresh database for isolation

    /** @test */
    public function charge_cycle_issue_and_payment_sync_everything(): void
    {
        // Arrange
        $account = Account::factory()->create(['balance' => 1000]);
        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            // ... card details
        ]);

        // Act
        $issued = app(CreditCardCycleService::class)->issueCycle($cycle);

        // Assert
        $this->assertTrue($issued);
        $card->refresh();
        $this->assertSame(CreditCardCycleStatus::ISSUED, $card->status);
    }
}
```

**Patterns:**

- **@test annotation**: Marks methods as tests (alternative to `test` prefix)
- **RefreshDatabase trait**: Wraps each test in database transaction (faster than migrate:fresh)
- **Factory usage**: `Model::factory()->create()` for test data generation
- **Model refresh**: `$model->refresh()` to reload from database after changes
- **Assertions**: Mixed PHPUnit native and Laravel-specific helpers
- **Setup**: Arrange-Act-Assert pattern strictly followed

## Mocking

**Framework:** Mockery 1.6

**Patterns:**

```php
// Mocking service in constructor
public function __construct(
    ?RevolvingCreditCalculator $calculator = null,
    ?CreditCardBalanceService $balanceService = null
) {
    $this->calculator = $calculator ?? app(RevolvingCreditCalculator::class);
    $this->balanceService = $balanceService ?? app(CreditCardBalanceService::class);
}

// In test:
$mockCalculator = Mockery::mock(RevolvingCreditCalculator::class);
$mockCalculator->shouldReceive('calculate')->andReturn([...]);
$service = new CreditCardCycleService($mockCalculator);
```

**Example from tests:**
```php
$mock = $this->mock(SomeClass::class, function ($mock) {
    $mock->shouldReceive('method')->andReturn('value');
});
```

**What to Mock:**
- External services (payment gateways, APIs)
- Repository methods in service tests
- Heavy dependencies in unit tests
- Database access via Repository pattern

**What NOT to Mock:**
- Models (use factories instead)
- Services being tested (test real implementation)
- Eloquent relationships (test with actual data)
- Database-backed services (use RefreshDatabase)

## Fixtures and Factories

**Test Data Pattern:**

All 8 factories follow consistent pattern:

```php
// database/factories/CreditCardFactory.php
namespace Database\Factories;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

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

    // State methods for variations
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
            return [
                'credit_limit' => null,
            ];
        });
    }
}

// Usage in tests:
$card = CreditCard::factory()->create();
$chargeCard = CreditCard::factory()->charge()->create();
$unlimitedCard = CreditCard::factory()->unlimited()->create();
```

**Location:**
- Factories: `database/factories/`
- Named matching model: `UserFactory` for `User` model
- Registered automatically via Eloquent factory registration

**Seeders:**
- Location: `database/seeders/`
- Used for development data population
- Not typically used in tests (use factories instead)

## Coverage

**Requirements:** 
- No enforced minimum
- PHPUnit reports coverage via `--coverage` flag
- `phpunit.xml` includes `<source>` section covering `app/` directory

**View Coverage:**
```bash
php artisan test --coverage --min-coverage=70
# Generates coverage report showing line/branch/method coverage
```

**Configuration (phpunit.xml):**
```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
```

Coverage includes all code in `app/` when running tests with `--coverage` flag.

## Test Types

**Unit Tests:**
- Scope: Single service or calculator in isolation
- Approach: No database, no models (or new model instances)
- Files: `tests/Unit/{Service}Test.php`
- Examples: `CreditCardCycleServiceTest`, `RevolvingCreditCalculatorTest`, `LoanScheduleServiceTest`
- Count: 16 unit test files testing calculation logic, financial algorithms

**Integration Tests:**
- Scope: Multiple services interacting, with database
- Approach: Create real models, test full workflows
- Files: `tests/Feature/{Domain}IntegrationTest.php`
- Examples: `CreditCardLifecycleIntegrationTest`, `CreditCardExpenseIntegrationTest`
- Count: 5 integration test files

**Authorization Tests:**
- Scope: Policy enforcement
- Approach: Create users with different permissions, test access
- Files: `tests/Feature/{Model}AuthorizationTest.php`
- Examples: `CreditCardAuthorizationTest`, `LoanAuthorizationTest`, `TransactionAuthorizationTest`, `AccountAuthorizationTest`
- Count: 4 authorization test files

**Module Tests:**
- Scope: Complete feature modules
- Approach: Test entire workflow (Filament resources, forms, tables)
- Files: `tests/Feature/{Module}/{Module}ModuleTest.php`
- Examples: `HealthModuleTest`, `ProductivityModuleTest`, `RelationshipsModuleTest`, `SettingsModuleTest`
- Count: 4 module test files

**Widget Tests:**
- Scope: Dashboard widgets
- Approach: Test widget rendering and data
- Files: `tests/Feature/Filament/Widgets/{Widget}Test.php`
- Example: `DashboardWidgetsTest`
- Count: 1 widget test file

**E2E Tests:**
- Not explicitly used in codebase
- Feature tests with `RefreshDatabase` serve similar purpose
- Manual testing via Filament UI in browser

## Common Patterns

**Async Testing:**
```php
// Not directly tested (queue connection is sync in tests)
// Jobs execute synchronously in phpunit.xml config:
// <env name="QUEUE_CONNECTION" value="sync"/>

// When testing queued operations:
$this->expectsJob(GenerateLoanPaymentsJob::class);
$service->triggerJobAsync();
```

**Error Testing:**
```php
public function test_invalid_installment_is_caught(): void
{
    $service = new CreditCardCycleService();
    
    $card = new CreditCard([
        'type' => CreditCardType::REVOLVING,
        'fixed_payment' => 0,  // Invalid
        'interest_rate' => 12,
    ]);

    $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);
    
    $this->assertTrue($result['invalid_installment']);
}

// Exception testing:
$this->expectException(InvalidArgumentException::class);
$service->methodThatThrows();
```

**Database Assertion:**
```php
$this->assertDatabaseHas('credit_cards', [
    'user_id' => $user->id,
    'status' => 'active',
]);

$this->assertDatabaseMissing('credit_cards', [
    'status' => 'deleted',
]);
```

**Model Relationship Testing:**
```php
$user = User::factory()->create();
$account = Account::factory()->create(['user_id' => $user->id]);

$user->load('accounts');
$this->assertTrue($user->accounts->contains($account));
```

**Observer Behavior Testing:**
```php
$cycle = CreditCardCycle::factory()->create();
$cycle->status = CreditCardCycleStatus::PAID;
$cycle->save();  // Triggers observer

$this->assertDatabaseHas('credit_card_payments', [
    'credit_card_cycle_id' => $cycle->id,
    'status' => CreditCardPaymentStatus::PAID,
]);
```

## Test Environment Configuration

**phpunit.xml settings:**
```xml
<env name="APP_ENV" value="testing"/>
<env name="BCRYPT_ROUNDS" value="4"/>  # Faster hashing for tests
<env name="CACHE_STORE" value="array"/>  # In-memory cache
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>  # In-memory database
<env name="QUEUE_CONNECTION" value="sync"/>  # Synchronous job execution
<env name="SESSION_DRIVER" value="array"/>  # Array session driver
<env name="MAIL_MAILER" value="array"/>  # Array mail driver (no sending)
```

**RefreshDatabase Trait:**
- Automatically runs migrations before each test
- Wraps test in database transaction
- Rolls back transaction after test (fast, no need for migrate:fresh)
- Alternative: `DatabaseMigrations` trait (slower, actually migrates)

**Test Isolation:**
- Each test is independent
- Database state not shared between tests
- Models created in setup are isolated
- No global state pollution

## Debugging Tests

**Run single test:**
```bash
php artisan test tests/Unit/CreditCardCycleServiceTest.php --filter test_name
```

**Print debugging:**
```php
dd($variable);  // Dump and die
dump($variable);  // Dump without dying
ray($variable);  # Ray debugger if installed
```

**PHPUnit options:**
```bash
php artisan test --verbose  # Verbose output
php artisan test --stop-on-failure  # Stop on first failure
php artisan test --order-by=random  # Randomize test order
```

---

*Testing analysis: 2024-12-19*
