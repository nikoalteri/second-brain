<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();

        return array_merge([
            'name' => 'Streaming',
            'subscription_frequency_id' => $frequency->id,
            'billing_amount' => 10,
            'day_of_month' => 15,
            'next_renewal_date' => '2026-05-15',
            'status' => 'active',
        ], $overrides);
    }

    public function test_store_subscription_rejects_foreign_account(): void
    {
        $user = User::factory()->create();
        $foreign = Account::factory()->create(['user_id' => User::factory()->create()->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions', $this->payload(['account_id' => $foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }

    public function test_store_subscription_rejects_foreign_credit_card_and_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownAccount = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);
        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id, 'account_id' => $foreignAccount->id]);
        $foreignCategory = TransactionCategory::create(['user_id' => $other->id, 'name' => 'Private', 'is_active' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/subscriptions', $this->payload(['credit_card_id' => $foreignCard->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('credit_card_id');

        $this->postJson('/api/v1/subscriptions', $this->payload(['account_id' => $ownAccount->id, 'category_id' => $foreignCategory->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->assertSame(0, Subscription::withoutGlobalScopes()->count());
    }

    public function test_update_subscription_rejects_foreign_references(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownAccount = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);
        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id, 'account_id' => $foreignAccount->id]);
        $foreignCategory = TransactionCategory::create(['user_id' => $other->id, 'name' => 'Private', 'is_active' => true]);
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => $ownAccount->id,
            'subscription_frequency_id' => $frequency->id,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/subscriptions/{$subscription->id}", ['account_id' => $foreignAccount->id])
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->putJson("/api/v1/subscriptions/{$subscription->id}", ['credit_card_id' => $foreignCard->id, 'account_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('credit_card_id');
        $this->putJson("/api/v1/subscriptions/{$subscription->id}", ['category_id' => $foreignCategory->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->assertSame($ownAccount->id, $subscription->fresh()->account_id);
    }

    public function test_graphql_subscription_mutations_reject_foreign_account_and_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownAccount = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);
        $foreignCategory = TransactionCategory::create(['user_id' => $other->id, 'name' => 'Private', 'is_active' => true]);
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => $ownAccount->id,
            'subscription_frequency_id' => $frequency->id,
        ]);

        Sanctum::actingAs($user);

        $create = $this->postJson('/graphql', [
            'query' => 'mutation ($a: ID!) { createSubscription(input: {account_id: $a, name: "X", monthly_cost: 5, frequency: "monthly", day_of_month: 5, next_renewal_date: "2026-05-05", status: "active"}) { id } }',
            'variables' => ['a' => $foreignAccount->id],
        ]);
        $this->assertArrayHasKey('input.account_id', $create->json('errors.0.extensions.validation') ?? []);

        $updateAccount = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $a: ID) { updateSubscription(id: $id, input: {account_id: $a}) { id } }',
            'variables' => ['id' => $subscription->id, 'a' => $foreignAccount->id],
        ]);
        $this->assertArrayHasKey('input.account_id', $updateAccount->json('errors.0.extensions.validation') ?? []);

        $updateCategory = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $c: ID) { updateSubscription(id: $id, input: {category_id: $c}) { id } }',
            'variables' => ['id' => $subscription->id, 'c' => $foreignCategory->id],
        ]);
        $this->assertArrayHasKey('input.category_id', $updateCategory->json('errors.0.extensions.validation') ?? []);

        $this->assertSame($ownAccount->id, $subscription->fresh()->account_id);
        $this->assertSame(1, Subscription::withoutGlobalScopes()->count());
    }
}
