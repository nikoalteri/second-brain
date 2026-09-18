<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Enums\LoanPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountTransferService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rows written by services must belong to the owner of the record being processed, never to
 * whoever happens to be authenticated (a superadmin acting on another user's data) and never to
 * nobody (scheduler, no session).
 */
class OwnerAttributionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-10 12:00:00');

        $this->owner = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actAsSuperadmin(): User
    {
        Role::findOrCreate('superadmin');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $this->actingAs($admin);

        return $admin;
    }

    /** @return array<string, array{bool}> */
    public static function contexts(): array
    {
        return ['unauthenticated (scheduler)' => [false], 'superadmin acting for the owner' => [true]];
    }

    #[DataProvider('contexts')]
    public function test_loan_installment_posting_belongs_to_the_loan_owner(bool $asSuperadmin): void
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->owner->id,
            'account_id' => $this->account->id,
            'total_amount' => 1000,
            'monthly_payment' => 250,
            'remaining_amount' => 1000,
            'total_installments' => 4,
            'paid_installments' => 0,
        ]);

        $admin = $asSuperadmin ? $this->actAsSuperadmin() : null;

        $loan->payments()->create([
            'due_date' => Carbon::parse('2026-05-10'),
            'actual_date' => Carbon::parse('2026-05-10'),
            'amount' => 250,
            'status' => LoanPaymentStatus::PAID,
        ]);

        $transaction = Transaction::query()->withoutUserScope()->where('account_id', $this->account->id)->sole();

        $this->assertSame($this->owner->id, $transaction->user_id);
        $this->assertNotSame($admin?->id, $transaction->user_id);
    }

    #[DataProvider('contexts')]
    public function test_credit_card_payment_posting_belongs_to_the_card_owner(bool $asSuperadmin): void
    {
        $card = CreditCard::create([
            'user_id' => $this->owner->id,
            'account_id' => $this->account->id,
            'name' => 'Owner card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $admin = $asSuperadmin ? $this->actAsSuperadmin() : null;

        $card->payments()->create([
            'due_date' => Carbon::parse('2026-05-10'),
            'actual_date' => Carbon::parse('2026-05-10'),
            'installment_amount' => 100,
            'interest_amount' => 0,
            'principal_amount' => 100,
            'stamp_duty_amount' => 2,
            'total_amount' => 102,
            'status' => CreditCardPaymentStatus::PAID,
        ]);

        $transaction = Transaction::query()->withoutUserScope()->where('account_id', $this->account->id)->sole();

        $this->assertSame($this->owner->id, $transaction->user_id);
        $this->assertNotSame($admin?->id, $transaction->user_id);
    }

    #[DataProvider('contexts')]
    public function test_subscription_renewal_posting_belongs_to_the_subscription_owner(bool $asSuperadmin): void
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->owner->id,
            'account_id' => $this->account->id,
            'credit_card_id' => null,
            'subscription_frequency_id' => SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail()->id,
            'monthly_cost' => 12,
            'annual_cost' => 144,
            'next_renewal_date' => '2026-05-10',
            'auto_create_transaction' => true,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $admin = $asSuperadmin ? $this->actAsSuperadmin() : null;

        $this->assertTrue(app(SubscriptionService::class)->processRenewal($subscription));

        $transaction = Transaction::query()->withoutUserScope()->where('subscription_id', $subscription->id)->sole();

        $this->assertSame($this->owner->id, $transaction->user_id);
        $this->assertNotSame($admin?->id, $transaction->user_id);
    }

    #[DataProvider('contexts')]
    public function test_transfer_belongs_to_the_owner_of_the_source_account(bool $asSuperadmin): void
    {
        $target = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 0]);

        $admin = $asSuperadmin ? $this->actAsSuperadmin() : null;

        $legs = app(AccountTransferService::class)->transfer($this->account, $target, 50, '2026-05-10');

        $this->assertSame($this->owner->id, $legs['out']->user_id);
        $this->assertSame($this->owner->id, $legs['in']->user_id);
        $this->assertNotSame($admin?->id, $legs['out']->user_id);
    }

    public function test_subscription_totals_can_no_longer_fall_back_to_the_authenticated_user(): void
    {
        foreach (['getMonthlyTotal', 'getUpcomingRenewals'] as $method) {
            $parameters = (new ReflectionMethod(SubscriptionService::class, $method))->getParameters();
            $owner = end($parameters);

            $this->assertSame('userId', $owner->getName());
            $this->assertFalse($owner->isOptional(), "{$method}() must require an explicit owner");
            $this->assertFalse($owner->allowsNull(), "{$method}() must not accept a null owner");
        }
    }
}
