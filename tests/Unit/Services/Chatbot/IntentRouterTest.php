<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Contracts\ChatIntent;
use App\Services\Chatbot\Exceptions\UnsupportedIntentException;
use App\Services\Chatbot\IntentRouter;
use Tests\TestCase;

class IntentRouterTest extends TestCase
{
    public function test_it_routes_a_known_intent_key_to_its_handler(): void
    {
        $stub = new class implements ChatIntent
        {
            public function key(): string
            {
                return 'account_balances';
            }

            public function handle(User $user, array $params): array
            {
                return [
                    'intent' => 'account_balances',
                    'headline' => 'stub',
                    'highlight' => null,
                    'items' => [],
                    'empty_message' => null,
                ];
            }
        };

        $router = new IntentRouter([$stub]);

        $result = $router->route(new User(), 'account_balances', []);

        $this->assertSame([
            'intent' => 'account_balances',
            'headline' => 'stub',
            'highlight' => null,
            'items' => [],
            'empty_message' => null,
        ], $result);
    }

    public function test_it_passes_params_through_to_the_handler(): void
    {
        $receivedParams = null;

        $stub = new class($receivedParams) implements ChatIntent
        {
            public function __construct(private mixed &$receivedParams)
            {
            }

            public function key(): string
            {
                return 'account_balances';
            }

            public function handle(User $user, array $params): array
            {
                $this->receivedParams = $params;

                return [
                    'intent' => 'account_balances',
                    'headline' => 'stub',
                    'highlight' => null,
                    'items' => [],
                    'empty_message' => null,
                ];
            }
        };

        $router = new IntentRouter([$stub]);

        $router->route(new User(), 'account_balances', ['days' => 7]);

        $this->assertSame(['days' => 7], $receivedParams);
    }

    public function test_it_throws_unsupported_intent_exception_for_unknown_keys(): void
    {
        $router = new IntentRouter([]);

        $this->expectException(UnsupportedIntentException::class);

        $router->route(new User(), 'credit_card_usage', []);
    }

    public function test_unsupported_intent_exception_carries_the_out_of_scope_message(): void
    {
        $router = new IntentRouter([]);

        try {
            $router->route(new User(), 'credit_card_usage', []);
            $this->fail('Expected UnsupportedIntentException was not thrown.');
        } catch (UnsupportedIntentException $exception) {
            $this->assertSame(
                'I can only help with balances, upcoming payments, and monthly spending right now.',
                $exception->getMessage()
            );
            $this->assertSame('credit_card_usage', $exception->intentKey());
        }
    }
}
