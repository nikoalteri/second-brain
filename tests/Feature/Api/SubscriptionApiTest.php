<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_subscriptions(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Subscription::factory()->count(2)->create([
            'user_id' => $userA->id,
            'subscription_frequency_id' => $frequency->id,
        ]);
        Subscription::factory()->count(3)->create([
            'user_id' => $userB->id,
            'subscription_frequency_id' => $frequency->id,
        ]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/subscriptions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_subscriptions_can_be_filtered_by_status(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $user = User::factory()->create();

        Subscription::factory()->count(2)->create([
            'user_id' => $user->id,
            'subscription_frequency_id' => $frequency->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'subscription_frequency_id' => $frequency->id,
            'status' => SubscriptionStatus::INACTIVE,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscriptions?filter[status]=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_credit_card_backed_subscription(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'annual')->firstOrFail();
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscriptions', [
            'name' => 'Adobe Creative Cloud',
            'credit_card_id' => $card->id,
            'subscription_frequency_id' => $frequency->id,
            'billing_amount' => 120,
            'day_of_month' => 15,
            'next_renewal_date' => '2026-05-15',
            'auto_create_transaction' => true,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.credit_card_id', $card->id)
            ->assertJsonPath('data.account_id', null)
            ->assertJsonPath('data.frequency', 'annual')
            ->assertJsonPath('data.billing_amount', 120);

        $this->assertDatabaseHas('subscriptions', [
            'name' => 'Adobe Creative Cloud',
            'credit_card_id' => $card->id,
            'subscription_frequency_id' => $frequency->id,
        ]);
    }

    public function test_subscription_show_includes_frequency_and_payment_source_metadata(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'annual')->firstOrFail();
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => null,
            'credit_card_id' => $card->id,
            'subscription_frequency_id' => $frequency->id,
            'annual_cost' => 120,
            'monthly_cost' => 10,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/subscriptions/{$subscription->id}");

        $response->assertOk()
            ->assertJsonPath('data.frequency', 'annual')
            ->assertJsonPath('data.frequency_option.months_interval', 12)
            ->assertJsonPath('data.payment_source_type', 'credit-card')
            ->assertJsonPath('data.credit_card.id', $card->id);
    }

    public function test_frequency_index_returns_active_frequency_options(): void
    {
        SubscriptionFrequency::query()->where('slug', 'biennial')->firstOrFail()->update(['is_active' => false]);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription-frequencies');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'monthly');
    }
}
