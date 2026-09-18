<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphQLLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const QUERY = '{ accounts { data { id } } }';

    public function test_a_normal_query_still_works(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk()->assertJsonMissingPath('errors');
    }

    public function test_an_oversized_request_is_rejected_before_it_is_parsed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['lighthouse.request_limits.max_bytes' => 2048]);

        $padding = str_repeat('a', 4096);

        $this->postJson('/graphql', ['query' => self::QUERY, 'variables' => ['padding' => $padding]])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.message', 'The GraphQL request is too large.');
    }

    public function test_batched_requests_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/graphql', [['query' => self::QUERY], ['query' => self::QUERY]])
            ->assertStatus(400)
            ->assertJsonPath('errors.0.message', 'Batched GraphQL requests are not supported.');
    }

    public function test_authenticated_requests_are_throttled_per_user(): void
    {
        $user = User::factory()->create();
        config(['lighthouse.request_limits.per_minute_authenticated' => 3]);

        Sanctum::actingAs($user);

        for ($request = 1; $request <= 3; $request++) {
            $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
        }

        $this->postJson('/graphql', ['query' => self::QUERY])->assertStatus(429);
    }

    public function test_the_throttle_of_one_user_does_not_affect_another(): void
    {
        config(['lighthouse.request_limits.per_minute_authenticated' => 2]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
        $this->postJson('/graphql', ['query' => self::QUERY])->assertStatus(429);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
    }

    public function test_unauthenticated_requests_are_throttled_per_ip(): void
    {
        config(['lighthouse.request_limits.per_minute_guest' => 2]);

        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
        $this->postJson('/graphql', ['query' => self::QUERY])->assertOk();
        $this->postJson('/graphql', ['query' => self::QUERY])->assertStatus(429);
    }
}
