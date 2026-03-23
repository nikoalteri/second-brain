<?php

namespace Tests\Unit;

use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Services\CreditCardCycleService;
use Tests\TestCase;

class CreditCardCycleServiceTest extends TestCase
{
    /** @test */
    public function revolving_breakdown_includes_stamp_duty_and_reduces_balance(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        $this->assertSame(10.0, $result['interest_amount']);
        $this->assertSame(240.0, $result['principal_amount']);
        $this->assertSame(252.0, $result['total_due']);
        $this->assertSame(760.0, $result['next_balance']);
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

        $result = $service->calculateRevolvingPaymentBreakdown($card, 5000);

        $this->assertTrue($result['invalid_installment']);
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

        $result = $service->calculateRevolvingPaymentBreakdown($card, 100);

        $this->assertSame(1.0, $result['interest_amount']);
        $this->assertSame(100.0, $result['principal_amount']);
        $this->assertSame(101.0, $result['installment_amount']);
        $this->assertSame(103.0, $result['total_due']);
        $this->assertSame(0.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }
}
