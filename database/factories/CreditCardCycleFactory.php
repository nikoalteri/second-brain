<?php

namespace Database\Factories;

use App\Enums\CreditCardCycleStatus;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardCycleFactory extends Factory
{
    protected $model = CreditCardCycle::class;

    public function definition(): array
    {
        $statementDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $statementDate = Carbon::instance($statementDate);

        $periodStartDate = $statementDate->copy()->startOfMonth();

        return [
            'credit_card_id' => CreditCard::factory(),
            'period_month' => $periodStartDate->format('Y-m'),
            'period_start_date' => $periodStartDate,
            'statement_date' => $statementDate,
            'due_date' => $statementDate->copy()->addDays(5),
            'total_spent' => 0.00,
            'interest_amount' => 0.00,
            'principal_amount' => 0.00,
            'stamp_duty_amount' => 0.00,
            'total_due' => 0.00,
            'paid_amount' => 0.00,
            'status' => CreditCardCycleStatus::OPEN,
        ];
    }

    public function issued(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CreditCardCycleStatus::ISSUED,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CreditCardCycleStatus::PAID,
                'paid_amount' => $attributes['total_due'],
            ];
        });
    }
}
