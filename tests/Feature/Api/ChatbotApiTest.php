<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatbotApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_ask_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/chatbot/ask', ['intent' => 'account_balances']);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_chatbot_rejects_unsupported_intent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chatbot/ask', ['intent' => 'credit_card_usage']);

        $response->assertStatus(422)
            ->assertJsonPath(
                'errors.intent.0',
                'I can only help with balances, upcoming payments, and monthly spending right now.'
            );
    }

    public function test_chatbot_account_balances_intent_returns_scoped_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id, 'is_active' => true, 'balance' => 1000.00]);
        Account::factory()->create(['user_id' => $user->id, 'is_active' => true, 'balance' => 250.50]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chatbot/ask', ['intent' => 'account_balances']);

        $response->assertOk()
            ->assertJsonPath('data.intent', 'account_balances')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.highlight.value', 1250.5);
    }

    public function test_chatbot_account_balances_intent_is_user_scoped(): void
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id, 'is_active' => true, 'balance' => 500.00]);

        $otherUser = User::factory()->create();
        Account::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
            'balance' => 999.00,
            'name' => 'Foreign Account',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chatbot/ask', ['intent' => 'account_balances']);

        $response->assertOk()
            ->assertDontSee('Foreign Account')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_chatbot_upcoming_payments_matches_dashboard(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'start_date' => now()->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        $loan->payments()->create([
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 250,
            'status' => 'pending',
        ]);

        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => now()->addDays(5)->toDateString(),
            'installment_amount' => 178,
            'interest_amount' => 0,
            'principal_amount' => 178,
            'stamp_duty_amount' => 2,
            'total_amount' => 180,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $dashboard = collect($this->getJson('/api/v1/dashboard/upcoming-payments?days=7')->json('data'))
            ->map(fn (array $row) => [$row['description'], round((float) $row['amount'], 2)])
            ->all();

        $chat = collect($this->postJson('/api/v1/chatbot/ask', ['intent' => 'upcoming_payments', 'params' => ['days' => 7]])->json('data.items'))
            ->map(fn (array $item) => [$item['label'], $item['value']])
            ->all();

        $this->assertSame($dashboard, $chat);
    }

    public function test_chatbot_monthly_spending_intent_returns_correct_totals(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Earnings'],
            ['is_income' => true]
        );
        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false]
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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chatbot/ask', ['intent' => 'monthly_spending']);

        $response->assertOk()
            ->assertJsonPath('data.items.0.label', 'Earnings')
            ->assertJsonPath('data.items.0.value', 2000.0)
            ->assertJsonPath('data.items.1.label', 'Expenses')
            ->assertJsonPath('data.items.1.value', 500.0)
            ->assertJsonPath('data.items.2.label', 'Net')
            ->assertJsonPath('data.items.2.value', 1500.0)
            ->assertJsonPath('data.highlight.value', 1500.0);
    }

    public function test_chatbot_rejects_malformed_month_param(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/chatbot/ask', [
            'intent' => 'monthly_spending',
            'params' => ['month' => 'credit cards'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['params.month']);
    }

    public function test_chatbot_ask_uses_api_read_throttle(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'api/v1/chatbot/ask');

        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('throttle:api-read', $middleware);
    }
}
