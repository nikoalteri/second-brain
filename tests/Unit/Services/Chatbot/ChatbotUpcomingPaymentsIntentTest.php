<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chatbot;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\User;
use App\Services\Chatbot\Intents\UpcomingPaymentsIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotUpcomingPaymentsIntentTest extends TestCase
{
    use RefreshDatabase;

    private function createLoanPayment(User $user, Account $account, float $amount, int $daysUntilDue, string $name = 'Car loan'): void
    {
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => $name,
            'start_date' => now()->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 2,
            'monthly_payment' => $amount,
        ]);

        $loan->payments()->create([
            'due_date' => now()->addDays($daysUntilDue)->toDateString(),
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    private function createCreditCardPayment(User $user, Account $account, float $totalAmount, int $daysUntilDue): void
    {
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => now()->addDays($daysUntilDue)->toDateString(),
            'installment_amount' => $totalAmount,
            'interest_amount' => 0,
            'principal_amount' => $totalAmount,
            'stamp_duty_amount' => 0,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);
    }

    public function test_it_maps_service_rows_into_answer_items(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);
        $this->createLoanPayment($user, $account, 250.00, 2, 'Car loan');

        $this->actingAs($user);

        $intent = app(UpcomingPaymentsIntent::class);
        $result = $intent->handle($user, []);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('Car loan', $item['label']);
        $this->assertSame(250.0, $item['value']);
        $this->assertSame('EUR', $item['currency']);
        $dueDate = now()->addDays(2)->toDateString();
        $this->assertSame("Loan · due {$dueDate}", $item['detail']);
    }

    public function test_it_totals_all_due_amounts_in_the_highlight(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);
        $this->createLoanPayment($user, $account, 250.00, 1, 'Car loan');
        $this->createCreditCardPayment($user, $account, 100.00, 2);

        $this->actingAs($user);

        $intent = app(UpcomingPaymentsIntent::class);
        $result = $intent->handle($user, []);

        $this->assertSame('Total due', $result['highlight']['label']);
        $this->assertSame(350.0, $result['highlight']['value']);
    }

    public function test_it_honours_the_days_param(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR']);
        $this->createLoanPayment($user, $account, 250.00, 10, 'Far out loan');

        $this->actingAs($user);

        $intent = app(UpcomingPaymentsIntent::class);

        $resultShort = $intent->handle($user, ['days' => 3]);
        $this->assertCount(0, $resultShort['items']);

        $resultLong = $intent->handle($user, ['days' => 14]);
        $this->assertCount(1, $resultLong['items']);
    }

    public function test_it_returns_an_empty_message_when_nothing_is_due(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $intent = app(UpcomingPaymentsIntent::class);
        $result = $intent->handle($user, []);

        $this->assertSame([], $result['items']);
        $this->assertNull($result['highlight']);
        $this->assertSame('Nothing is due in the next 3 days.', $result['empty_message']);
    }
}
