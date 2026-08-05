<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chatbot;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Chatbot\Intents\MonthlySpendingIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotMonthlySpendingIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_current_month_totals_by_default(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Income'],
            ['is_income' => true],
        );
        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false],
        );

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $incomeType->id,
            'amount' => 2000.00,
            'date' => now()->toDateString(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'amount' => -500.00,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($user);

        $intent = app(MonthlySpendingIntent::class);
        $result = $intent->handle($user, []);

        $items = collect($result['items'])->keyBy('label');
        $this->assertSame(2000.0, $items['Earnings']['value']);
        $this->assertSame(500.0, $items['Expenses']['value']);
        $this->assertSame(1500.0, $items['Net']['value']);
        $this->assertSame(['label' => 'Net', 'value' => 1500.0, 'currency' => 'EUR'], $result['highlight']);
    }

    public function test_it_honours_an_explicit_month_param(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Income'],
            ['is_income' => true],
        );

        $otherMonth = now()->subMonths(2)->startOfMonth();

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $incomeType->id,
            'amount' => 300.00,
            'date' => $otherMonth->toDateString(),
        ]);

        $this->actingAs($user);

        $intent = app(MonthlySpendingIntent::class);

        $defaultResult = $intent->handle($user, []);
        $items = collect($defaultResult['items'])->keyBy('label');
        $this->assertSame(0.0, $items['Earnings']['value']);

        $explicitResult = $intent->handle($user, ['month' => $otherMonth->format('Y-m')]);
        $items = collect($explicitResult['items'])->keyBy('label');
        $this->assertSame(300.0, $items['Earnings']['value']);
    }

    public function test_it_never_returns_the_totale_row(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $intent = app(MonthlySpendingIntent::class);
        $result = $intent->handle($user, []);

        $this->assertCount(3, $result['items']);
        $this->assertNotContains('TOTALE', collect($result['items'])->pluck('label')->all());
    }

    public function test_it_excludes_other_users_transactions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Income'],
            ['is_income' => true],
        );

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'transaction_type_id' => $incomeType->id,
            'amount' => 9999.00,
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($user);

        $intent = app(MonthlySpendingIntent::class);
        $result = $intent->handle($user, []);

        $items = collect($result['items'])->keyBy('label');
        $this->assertSame(0.0, $items['Earnings']['value']);
    }
}
