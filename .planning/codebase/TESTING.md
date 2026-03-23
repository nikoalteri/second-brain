# Testing Patterns

**Analysis Date:** 2026-03-23

## Test Framework

**Runner:**
- PHPUnit 11.5.3+ (configured in `phpunit.xml`)
- Config: `phpunit.xml` in project root
- Test bootstrap: `vendor/autoload.php`

**Assertion Library:**
- PHPUnit's built-in assertions (no separate assertion library)

**Run Commands:**
```bash
composer test              # Run all tests (Unit + Feature)
php artisan test           # Alternative: via Laravel artisan
php artisan test tests/Unit/LoanScheduleServiceTest.php  # Run specific test
./vendor/bin/phpunit --filter="it_generates_payments"    # Run by method name
php artisan test --coverage                              # Generate coverage (if enabled)
```

## Test File Organization

**Location:**
- Unit tests: `tests/Unit/` (isolated, fast)
- Feature tests: `tests/Feature/` (integration, HTTP workflows)
- Base test class: `tests/TestCase.php`

**Naming:**
- File: `{Subject}Test.php` (e.g., `LoanScheduleServiceTest.php`)
- Method: `test_{description}()` or `/** @test */ it_{description}()`

**Structure:**
```
tests/
├── Unit/
│   ├── CreditCardKpiServiceTest.php
│   ├── CreditCardCycleServiceTest.php
│   ├── LoanScheduleServiceTest.php
│   ├── LoanRepositoryTest.php
│   └── ExampleTest.php
├── Feature/
│   ├── CreditCardLifecycleIntegrationTest.php
│   ├── LoanAuthorizationTest.php
│   ├── TransactionAuthorizationTest.php
│   └── ExampleTest.php
└── TestCase.php
```

## Test Structure

**Suite Organization:**

```php
class LoanScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_payments_for_a_loan()
    {
        // Arrange: Set up test data
        $loan = Loan::factory()->create([
            'start_date' => Carbon::now()->subMonths(2),
            'total_installments' => 3,
            'withdrawal_day' => 15,
            'monthly_payment' => 100,
            'skip_weekends' => false,
        ]);

        // Act: Execute the behavior
        $service = new LoanScheduleService();
        $service->generate($loan);

        // Assert: Verify the results
        $this->assertCount(3, $loan->payments);
        foreach ($loan->payments as $payment) {
            $this->assertEquals(100, $payment->amount);
            $this->assertEquals(LoanPaymentStatus::PENDING, $payment->status);
        }
    }

    /** @test */
    public function it_syncs_loan_status_and_amount()
    {
        $loan = Loan::factory()->create([
            'total_amount' => 300,
            'monthly_payment' => 100,
            'total_installments' => 3,
        ]);
        $service = new LoanScheduleService();
        $service->generate($loan);

        $payment = $loan->payments()->first();
        $payment->update(['status' => 'paid']);

        $service->syncLoan($loan->fresh());

        $loan->refresh();
        $this->assertEquals(1, $loan->paid_installments);
        $this->assertEquals(200, $loan->remaining_amount);
    }
}
```

**Patterns:**
- Setup: Arrange test data using factories
- Act: Call the method under test
- Assert: Verify expectations with PHPUnit assertions
- Teardown: RefreshDatabase trait automatically rolls back transactions

## Mocking

**Framework:** Mockery (installed as dev dependency `mockery/mockery`)

**Patterns:**
- Not heavily used in observed tests - prefer real database with factories
- Example where mocking might apply: External API calls, time-dependent logic

```php
// Example pattern (not in current codebase but follows convention):
$mockedService = Mockery::mock(ExternalApiService::class);
$mockedService->shouldReceive('call')->andReturn($data);

// Inject mock into service
$service = new MyService($mockedService);
$result = $service->doSomething();
```

**What to Mock:**
- External API calls (not applicable in this self-contained app)
- Current time/date (use Carbon::setTestNow() instead of mocking)
- Heavy computations (not observed in codebase)

**What NOT to Mock:**
- Database interactions (use RefreshDatabase and factories instead)
- Eloquent models (use real models to test relations)
- Laravel services (inject real services unless testing their isolation)

## Fixtures and Factories

**Test Data:**

Factories located in `database/factories/`:
- `UserFactory.php` - Generates test users
- `LoanFactory.php` - Generates test loans with realistic defaults
- `CreditCardFactory.php` - Generates test credit cards
- `AccountFactory.php` - Generates test accounts
- `TransactionFactory.php` - Generates test transactions

**Usage pattern:**
```php
// Create single instance
$loan = Loan::factory()->create([
    'start_date' => Carbon::now(),
    'total_installments' => 12,
]);

// Create multiple instances
$loans = Loan::factory()->count(5)->create();

// Create with relationships
$user = User::factory()
    ->has(Account::factory()->count(2))
    ->create();

// Create without persisting
$loan = Loan::factory()->make(['status' => 'draft']);
```

**Location:**
- Factories: `database/factories/` (auto-discovered by Laravel)
- Test database: Temporary SQLite in-memory database for tests
- RefreshDatabase trait handles migration/rollback per test

## Coverage

**Requirements:** Not enforced - no coverage threshold configured in `phpunit.xml`

**View Coverage:**
```bash
php artisan test --coverage
# Requires: pcov or xdebug PHP extension
```

## Test Types

**Unit Tests:**
- Scope: Single service or utility function isolated from database
- Approach: Test business logic, calculations, edge cases
- Example: `CreditCardKpiServiceTest` tests KPI calculation logic
- Database: Uses RefreshDatabase for state isolation (even though database is involved)

**Integration Tests:**
- Scope: Multiple layers (HTTP request → Service → Database)
- Approach: Test workflows end-to-end
- Example: `CreditCardLifecycleIntegrationTest` tests credit card creation through payment posting
- Database: Real database with factories to set up state

**E2E Tests:**
- Framework: Not detected - No Dusk, Selenium, or Puppeteer configuration
- Could be added: `laravel/dusk` for browser automation testing

**Authorization Tests:**
- Scope: Test policy enforcement and role-based access
- Example: `LoanAuthorizationTest` verifies only authorized users can access loan data
- Pattern: Create users with roles, assert HTTP response codes (403 for denied)

## Common Patterns

**Async Testing:**

```php
// Example: Testing background jobs
$this->expectsJob(GenerateLoanPaymentsJob::class);

Loan::factory()->create();
// Job should have been dispatched

// Or verify job was processed:
Bus::fake();
Loan::factory()->create();
Bus::assertDispatched(GenerateLoanPaymentsJob::class);
```

**Error Testing:**

```php
// Test validation failures
$this->actingAs($user)
    ->post('/loans', [
        'total_installments' => 0,  // Invalid
        'monthly_payment' => -100,  // Invalid
    ])
    ->assertSessionHasErrors(['total_installments', 'monthly_payment']);

// Test service exceptions
$service = new LoanScheduleService();
$loan = Loan::factory()->make(['total_installments' => -1]);

// Service might throw or return error; test the behavior
```

**Date-Based Testing:**

```php
// Travel through time for date-dependent logic
Carbon::setTestNow('2026-03-23 10:00:00');

$loan = Loan::factory()->create([
    'start_date' => Carbon::now(),
]);

$service = new LoanScheduleService();
$service->generate($loan);

// Assertions based on known date
$this->assertEquals(15, $loan->payments[0]->due_date->day);

// Reset time after test
Carbon::setTestNow(null);
```

## Test Execution

**Base Test Class (tests/TestCase.php):**
- Extends `PHPUnit\Framework\TestCase` via Laravel
- Provides: `RefreshDatabase` trait, `actingAs()` for auth, HTTP testing helpers
- Available in both Unit and Feature tests

**RefreshDatabase Trait:**
- Migrates database schema before each test
- Rolls back to clean state after each test
- Uses transaction rollback for speed (or migration for isolation)
- Ensures test isolation and deterministic behavior

**Parallelization:**
- Not detected - PHPUnit runs tests sequentially
- Could be added with `laravel/paratest` for faster CI

---

*Testing analysis: 2026-03-23*
