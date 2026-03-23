<?php

namespace Tests\Unit;

use App\Models\CreditCard;
use App\Services\CreditCardBalanceService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditCardBalanceServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private CreditCardBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreditCardBalanceService::class);
    }

    /** @test */
    public function add_expense_increases_balance()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 500.00,
            'credit_limit' => 4000.00,
        ]);

        $newBalance = $this->service->addExpense($card, 100.00);

        $this->assertEquals(600.00, $newBalance);
        $this->assertEquals(600.00, $card->fresh()->current_balance);
    }

    /** @test */
    public function add_expense_respects_credit_limit()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 3500.00,
            'credit_limit' => 4000.00,
        ]);

        // Adding 600 would exceed limit (3500 + 600 = 4100 > 4000)
        $this->expectException(ValidationException::class);

        $this->service->addExpense($card, 600.00);
    }

    /** @test */
    public function add_expense_allows_exactly_at_limit()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 3500.00,
            'credit_limit' => 4000.00,
        ]);

        // Adding exactly 500 reaches limit
        $newBalance = $this->service->addExpense($card, 500.00);

        $this->assertEquals(4000.00, $newBalance);
    }

    /** @test */
    public function add_expense_ignores_unlimited_cards()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 10000.00,
            'credit_limit' => null,
        ]);

        // Should not throw even though balance is huge
        $newBalance = $this->service->addExpense($card, 50000.00);

        $this->assertEquals(60000.00, $newBalance);
    }

    /** @test */
    public function remove_expense_decreases_balance()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 600.00,
        ]);

        $newBalance = $this->service->removeExpense($card, 100.00);

        $this->assertEquals(500.00, $newBalance);
        $this->assertEquals(500.00, $card->fresh()->current_balance);
    }

    /** @test */
    public function remove_expense_never_goes_below_zero()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 100.00,
        ]);

        $newBalance = $this->service->removeExpense($card, 200.00);

        $this->assertEquals(0.0, $newBalance);
    }

    /** @test */
    public function apply_principal_payment_decreases_balance()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
        ]);

        $newBalance = $this->service->applyPrincipalPayment($card, 230.28);

        $this->assertEquals(311.72, $newBalance);
        $this->assertEquals(311.72, $card->fresh()->current_balance);
    }

    /** @test */
    public function apply_principal_payment_never_goes_below_zero()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 100.00,
        ]);

        $newBalance = $this->service->applyPrincipalPayment($card, 200.00);

        $this->assertEquals(0.0, $newBalance);
    }

    /** @test */
    public function reverse_principal_payment_increases_balance()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 311.72,
        ]);

        $newBalance = $this->service->reversePrincipalPayment($card, 230.28);

        $this->assertEquals(542.00, $newBalance);
    }

    /** @test */
    public function get_current_debt_returns_balance()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
        ]);

        $debt = $this->service->getCurrentDebt($card);

        $this->assertEquals(542.00, $debt);
    }

    /** @test */
    public function get_available_credit_for_limited_card()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 1500.00,
            'credit_limit' => 4000.00,
        ]);

        $available = $this->service->getAvailableCredit($card);

        // 4000 - 1500 = 2500
        $this->assertEquals(2500.00, $available);
    }

    /** @test */
    public function get_available_credit_for_unlimited_card()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 10000.00,
            'credit_limit' => null,
        ]);

        $available = $this->service->getAvailableCredit($card);

        $this->assertNull($available);
    }

    /** @test */
    public function operations_return_rounded_values()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 100.00,
        ]);

        $newBalance = $this->service->addExpense($card, 66.666666);

        // Should be rounded to 2 decimals
        $this->assertEquals(166.67, $newBalance);
    }

    /** @test */
    public function zero_amount_operations_do_nothing()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 100.00,
        ]);

        $balance1 = $this->service->addExpense($card, 0.0);
        $balance2 = $this->service->removeExpense($card, 0.0);
        $balance3 = $this->service->applyPrincipalPayment($card, 0.0);

        $this->assertEquals(100.00, $balance1);
        $this->assertEquals(100.00, $balance2);
        $this->assertEquals(100.00, $balance3);
    }

    /** @test */
    public function negative_amount_operations_do_nothing()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 100.00,
        ]);

        $balance = $this->service->addExpense($card, -50.0);

        $this->assertEquals(100.00, $balance);
    }
}
