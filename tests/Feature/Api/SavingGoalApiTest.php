<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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

    public function test_user_can_create_a_saving_goal(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/saving-goals', [
            'name' => 'Emergency fund',
            'target_amount' => 5000.5,
            'target_date' => '2027-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Emergency fund')
            ->assertJsonPath('data.target_amount', 5000.5)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(0.0, (float) $response->json('data.current_amount'));
        $this->assertSame(0.0, (float) $response->json('data.progress_percent'));
    }

    public function test_contribution_updates_progress_and_flips_status_to_achieved(): void
    {
        $user = User::factory()->create();
        $goal = SavingGoal::factory()->create(['user_id' => $user->id, 'target_amount' => 1000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => 600,
            'date' => '2026-08-08',
        ])->assertCreated();

        $goal->refresh();
        $this->assertSame(600.0, (float) $goal->current_amount);
        $this->assertSame('active', $goal->status->value);
        $this->assertSame(60.0, $goal->progress_percent);

        $response = $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => 500,
            'date' => '2026-08-09',
        ]);

        $response->assertCreated();
        $goal->refresh();
        $this->assertSame(1100.0, (float) $goal->current_amount);
        $this->assertSame('achieved', $goal->status->value);
        $this->assertSame(100.0, $goal->progress_percent);
    }

    public function test_deleting_a_contribution_reverses_its_effect(): void
    {
        $user = User::factory()->create();
        $goal = SavingGoal::factory()->create(['user_id' => $user->id, 'target_amount' => 1000]);

        Sanctum::actingAs($user);

        $contribution = $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => 400,
            'date' => '2026-08-08',
        ])->json('data');

        $this->deleteJson("/api/v1/saving-goals/{$goal->id}/contributions/{$contribution['id']}")
            ->assertNoContent();

        $this->assertSame(0.0, (float) $goal->fresh()->current_amount);
    }

    public function test_negative_contribution_withdraws_from_progress(): void
    {
        $user = User::factory()->create();
        $goal = SavingGoal::factory()->create(['user_id' => $user->id, 'target_amount' => 1000, 'current_amount' => 500]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => -200,
            'date' => '2026-08-08',
        ])->assertCreated();

        $this->assertSame(300.0, (float) $goal->fresh()->current_amount);
    }

    public function test_user_cannot_contribute_to_another_users_goal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = SavingGoal::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => 100,
            'date' => '2026-08-08',
        ])->assertStatus(404);
    }

    public function test_archived_goal_status_is_not_overridden_by_contributions(): void
    {
        $user = User::factory()->create();
        $goal = SavingGoal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 1000,
            'current_amount' => 1000,
            'status' => 'archived',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/saving-goals/{$goal->id}/contributions", [
            'amount' => 50,
            'date' => '2026-08-08',
        ])->assertCreated();

        $this->assertSame('archived', $goal->fresh()->status->value);
    }
}
