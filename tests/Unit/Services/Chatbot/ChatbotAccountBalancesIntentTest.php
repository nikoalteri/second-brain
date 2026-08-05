<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chatbot;

use App\Models\Account;
use App\Models\User;
use App\Services\Chatbot\Intents\AccountBalancesIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotAccountBalancesIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_accounts_with_balances(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Main',
            'balance' => 1000.00,
            'is_active' => true,
            'currency' => 'EUR',
        ]);
        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Savings',
            'balance' => 250.50,
            'is_active' => true,
            'currency' => 'EUR',
        ]);

        $this->actingAs($user);

        $intent = app(AccountBalancesIntent::class);
        $result = $intent->handle($user, []);

        $this->assertCount(2, $result['items']);
        $this->assertSame(['Main', 'Savings'], collect($result['items'])->pluck('label')->all());
        $this->assertSame(['label' => 'Total', 'value' => 1250.5, 'currency' => 'EUR'], $result['highlight']);
    }

    public function test_it_excludes_inactive_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Active account',
            'balance' => 500.0,
            'is_active' => true,
        ]);
        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Closed account',
            'balance' => 999.0,
            'is_active' => false,
        ]);

        $this->actingAs($user);

        $intent = app(AccountBalancesIntent::class);
        $result = $intent->handle($user, []);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Active account', $result['items'][0]['label']);
    }

    public function test_it_excludes_other_users_accounts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Account::factory()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
            'balance' => 100.0,
            'is_active' => true,
        ]);
        Account::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Not mine',
            'balance' => 5000.0,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $intent = app(AccountBalancesIntent::class);
        $result = $intent->handle($user, []);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Mine', $result['items'][0]['label']);
        $this->assertSame(100.0, $result['highlight']['value']);
    }

    public function test_it_returns_an_empty_message_when_no_active_accounts_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $intent = app(AccountBalancesIntent::class);
        $result = $intent->handle($user, []);

        $this->assertSame([], $result['items']);
        $this->assertNull($result['highlight']);
        $this->assertSame("You don't have any active accounts yet.", $result['empty_message']);
    }
}
