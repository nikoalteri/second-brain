<?php

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
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        // 12% of 1000 = 120, principal = 250 - 120 = 130
        $this->assertSame(120.0, $result['interest_amount']);
        $this->assertSame(130.0, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(252.0, $result['total_due']);
        $this->assertSame(870.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    /** @test */
    public function revolving_breakdown_with_14_percent_rate_matches_bank_statement(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
        ]);

        // Real bank data: debt €542, rate 14%, should yield interest €75.88
        $result = $service->calculateRevolvingPaymentBreakdown($card, 542);

        // 14% of 542 = 75.88, principal = 250 - 75.88 = 174.12
        $this->assertSame(75.88, $result['interest_amount']);
        $this->assertSame(174.12, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(252.0, $result['total_due']);
        $this->assertSame(367.88, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    /** @test */
    public function revolving_breakdown_marks_invalid_when_installment_does_not_cover_interest(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 5,
            'interest_rate' => 24,
            'stamp_duty_amount' => 2,
        ]);

        // 24% of 5000 = 1200, interest exceeds installment
        $result = $service->calculateRevolvingPaymentBreakdown($card, 5000);

        $this->assertTrue($result['invalid_installment']);
        $this->assertSame(1200.0, $result['interest_amount']);
        $this->assertSame(2.0, $result['stamp_duty_amount']);
        $this->assertSame(7.0, $result['total_due']);
    }

    /** @test */
    public function revolving_breakdown_is_invalid_when_fixed_installment_is_missing(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 0,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        $this->assertTrue($result['invalid_installment']);
        $this->assertSame(2.0, $result['total_due']);
    }

    /** @test */
    public function revolving_breakdown_caps_installment_when_balance_is_lower_than_max_installment(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        // 12% of 100 = 12, principal = min(100, 250 - 12) = 100
        $result = $service->calculateRevolvingPaymentBreakdown($card, 100);

        $this->assertSame(12.0, $result['interest_amount']);
        $this->assertSame(100.0, $result['principal_amount']);
        $this->assertSame(112.0, $result['installment_amount']);
        $this->assertSame(114.0, $result['total_due']);
        $this->assertSame(0.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    /** @test */
    public function charge_card_breakdown_has_no_interest(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::CHARGE,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 0,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 500);

        $this->assertSame(0.0, $result['interest_amount']);
        $this->assertSame(0.0, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(250.0, $result['total_due']);
        $this->assertSame(500.0, $result['next_balance']);
    }
}
