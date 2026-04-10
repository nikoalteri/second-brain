<?php

namespace Tests\Unit\Services;

use App\Models\TripBudget;
use App\Models\TripExpense;
use App\Models\TripParticipant;
use App\Services\TravelBudgetCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelBudgetCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private TravelBudgetCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TravelBudgetCalculator();
    }

    /** @test */
    public function total_expenses_sums_all_expenses(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 1000,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 100,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 50,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 75,
        ]);

        $total = $this->calculator->totalExpenses($budget);

        $this->assertEquals(225.0, $total);
    }

    /** @test */
    public function total_expenses_returns_zero_for_no_expenses(): void
    {
        $budget = TripBudget::factory()->create();

        $total = $this->calculator->totalExpenses($budget);

        $this->assertEquals(0.0, $total);
    }

    /** @test */
    public function remaining_budget_calculates_correctly(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 1000,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 300,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 200,
        ]);

        $remaining = $this->calculator->remainingBudget($budget);

        $this->assertEquals(500.0, $remaining);
    }

    /** @test */
    public function remaining_budget_can_be_negative(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 500,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 600,
        ]);

        $remaining = $this->calculator->remainingBudget($budget);

        $this->assertEquals(-100.0, $remaining);
    }

    /** @test */
    public function budget_percentage_used_calculates_correctly(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 1000,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 500,
        ]);

        $percentage = $this->calculator->budgetPercentageUsed($budget);

        $this->assertEquals(50.0, $percentage);
    }

    /** @test */
    public function budget_percentage_used_with_zero_initial_amount(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 0,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 100,
        ]);

        $percentage = $this->calculator->budgetPercentageUsed($budget);

        $this->assertEquals(0.0, $percentage);
    }

    /** @test */
    public function budget_percentage_used_exceeds_100_over_budget(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 500,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 600,
        ]);

        $percentage = $this->calculator->budgetPercentageUsed($budget);

        $this->assertEquals(120.0, $percentage);
    }

    /** @test */
    public function add_expense_creates_trip_expense(): void
    {
        $budget = TripBudget::factory()->create();
        $participant = TripParticipant::factory()->create([
            'user_id' => $budget->user_id,
            'trip_id' => $budget->trip_id,
        ]);

        $expense = $this->calculator->addExpense(
            $budget,
            $participant,
            100.50,
            'EUR',
            'accommodation'
        );

        $this->assertNotNull($expense->id);
        $this->assertEquals(100.50, $expense->amount);
        $this->assertEquals('EUR', $expense->currency);
        $this->assertEquals('accommodation', $expense->category);
        $this->assertEquals($budget->id, $expense->trip_budget_id);
        $this->assertDatabaseHas('trip_expenses', [
            'trip_budget_id' => $budget->id,
            'amount' => 100.50,
        ]);
    }

    /** @test */
    public function add_expense_with_default_currency(): void
    {
        $budget = TripBudget::factory()->create();
        $participant = TripParticipant::factory()->create([
            'user_id' => $budget->user_id,
            'trip_id' => $budget->trip_id,
        ]);

        $expense = $this->calculator->addExpense($budget, $participant, 50.0);

        $this->assertEquals('USD', $expense->currency);
    }

    /** @test */
    public function remaining_budget_via_model_attribute(): void
    {
        $budget = TripBudget::factory()->create([
            'initial_amount' => 1000,
        ]);

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 300,
        ]);

        // Test the model attribute directly
        $this->assertEquals(700.0, $budget->remaining_budget);
    }

    /** @test */
    public function total_expenses_via_model_attribute(): void
    {
        $budget = TripBudget::factory()->create();

        TripExpense::factory()->create([
            'trip_budget_id' => $budget->id,
            'amount' => 250,
        ]);

        // Test the model attribute directly
        $this->assertEquals(250.0, $budget->total_expenses);
    }
}
