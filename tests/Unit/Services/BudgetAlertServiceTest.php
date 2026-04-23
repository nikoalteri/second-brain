<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BudgetAlertService;
use Tests\TestCase;

class BudgetAlertServiceTest extends TestCase
{
    public function test_missing_budgets_never_alert(): void
    {
        $service = new BudgetAlertService();

        $this->assertSame('none', $service->resolveStatus(null, 0.0));
        $this->assertSame('none', $service->resolveStatus(null, 150.0));
    }

    public function test_budget_thresholds_are_fixed_at_80_100_and_120_percent(): void
    {
        $service = new BudgetAlertService();

        $this->assertSame('ok', $service->resolveStatus(100.0, 79.99));
        $this->assertSame('warning', $service->resolveStatus(100.0, 80.00));
        $this->assertSame('warning', $service->resolveStatus(100.0, 99.99));
        $this->assertSame('exceeded', $service->resolveStatus(100.0, 100.00));
        $this->assertSame('exceeded', $service->resolveStatus(100.0, 119.99));
        $this->assertSame('critical', $service->resolveStatus(100.0, 120.00));
    }
}
