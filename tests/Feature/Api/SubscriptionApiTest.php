<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_subscriptions(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create subscriptions without auth so HasUserScoping doesn't override user_id
        Subscription::factory()->count(2)->create(['user_id' => $userA->id]);
        Subscription::factory()->count(3)->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/subscriptions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_subscriptions_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->count(2)->create([
            'user_id' => $user->id,
            'status'  => SubscriptionStatus::ACTIVE,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status'  => SubscriptionStatus::INACTIVE,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscriptions?filter[status]=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $sub) {
            $this->assertEquals('active', $sub['status']);
        }
    }
}
