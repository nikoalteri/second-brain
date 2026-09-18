<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginationLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @return list<string> */
    public static function listEndpoints(): array
    {
        return [['accounts'], ['transactions'], ['loans'], ['credit-cards'], ['subscriptions'], ['saving-goals']];
    }

    /** @dataProvider listEndpoints */
    #[DataProvider('listEndpoints')]
    public function test_per_page_is_capped_at_one_hundred(string $endpoint): void
    {
        $this->getJson("/api/v1/{$endpoint}?per_page=100000")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    #[DataProvider('listEndpoints')]
    public function test_per_page_below_one_is_raised_to_one(string $endpoint): void
    {
        $this->getJson("/api/v1/{$endpoint}?per_page=0")->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson("/api/v1/{$endpoint}?per_page=-5")->assertOk()->assertJsonPath('meta.per_page', 1);
    }

    public function test_the_default_page_size_and_reasonable_values_are_unchanged(): void
    {
        $this->getJson('/api/v1/accounts')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->getJson('/api/v1/accounts?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/v1/accounts?per_page=7')->assertOk()->assertJsonPath('meta.per_page', 7);
    }

    public function test_a_page_never_returns_more_than_one_hundred_rows(): void
    {
        Account::factory()->count(105)->create(['user_id' => $this->user->id]);

        $this->getJson('/api/v1/accounts?per_page=1000')->assertOk()->assertJsonCount(100, 'data');
    }

    public function test_graphql_lists_cannot_ask_for_more_than_one_hundred_items(): void
    {
        $response = $this->postJson('/graphql', ['query' => '{ accounts(first: 150) { data { id } } }']);

        $this->assertNotEmpty($response->json('errors'));

        $this->postJson('/graphql', ['query' => '{ accounts(first: 100) { data { id } } }'])->assertJsonMissingPath('errors');
    }
}
