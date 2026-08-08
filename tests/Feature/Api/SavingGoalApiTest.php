<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\SavingGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavingGoalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_only_own_saving_goals(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        SavingGoal::factory()->count(2)->create(['user_id' => $userA->id]);
        SavingGoal::factory()->count(3)->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/saving-goals');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_user_can_create_a_saving_goal_linked_to_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/saving-goals', [
            'name' => 'Emergency fund',
            'account_id' => $account->id,
            'target_amount' => 5000.5,
            'target_date' => '2027-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Emergency fund')
            ->assertJsonPath('data.account_id', $account->id)
            ->assertJsonPath('data.target_amount', 5000.5)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_progress_tracks_the_linked_account_balance_live(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'balance' => 1200.5]);
        $goal = SavingGoal::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'target_amount' => 5000,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/saving-goals/{$goal->id}");

        $response->assertOk()->assertJsonPath('data.is_achieved', false);
        $this->assertSame(1200.5, (float) $response->json('data.current_amount'));
        $this->assertSame(24.0, (float) $response->json('data.progress_percent'));

        $account->increment('balance', 3799.5);

        $response = $this->getJson("/api/v1/saving-goals/{$goal->id}");

        $response->assertOk()->assertJsonPath('data.is_achieved', true);
        $this->assertSame(5000.0, (float) $response->json('data.current_amount'));
        $this->assertSame(100.0, (float) $response->json('data.progress_percent'));
    }

    public function test_saving_goal_creation_rejects_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/saving-goals', [
            'name' => 'Emergency fund',
            'account_id' => $foreignAccount->id,
            'target_amount' => 1000,
        ])->assertStatus(404);
    }

    public function test_user_cannot_view_another_users_saving_goal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = SavingGoal::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/v1/saving-goals/{$goal->id}")->assertStatus(404);
    }
}
